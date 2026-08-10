#!/usr/bin/env bash
# 2026-08-01 — Comprehensive audit remediation (security + database + perf + deps).
# Reproducible record of the actions taken after the 4-dimension audit
# (security / performance / scripts / queries). Read before re-running: several
# steps are one-time or destructive and are gated behind a confirmation.
#
# Usage:
#   ./2026-08-01-audit-remediation.sh <wp-container> <db-container> <db-name>
#   e.g. prod:    ./2026-08-01-audit-remediation.sh dti-blog-wp dti-blog-db wordpress
#
# Raw SQL runs through the DB container (the WP image has no mysql client).
# ALWAYS take a backup first (this script does).
#
# 2026-08-10 hardening (Night Shift audit): SQL now goes via STDIN (no `sh -c`
# re-parsing of query text → closes the backtick command-substitution bug + RCE
# vector), errexit on, fail-safe backup verification, fail-CLOSED plugin check,
# escaped LIKE underscores, MYSQL_PWD instead of -p argv.
set -euo pipefail

C="${1:?Usage: $0 <wp-container> <db-container> <db-name>}"
DBC="${2:?db-container required}"
DBN="${3:?db-name required}"
PFX="$(sudo docker exec "$C" sh -c "wp config get table_prefix --allow-root" 2>/dev/null | tr -d '[:space:]')"
[ -z "$PFX" ] && { echo "Cannot determine table_prefix"; exit 1; }

wp(){ sudo docker exec "$C" sh -c "wp $* --allow-root"; }
# SQL is piped to the client via STDIN — NOT interpolated into a `sh -c` string
# and NOT passed via `-e`. This means query text (incl. backtick-quoted table
# identifiers) can never be re-parsed by a shell inside the container, closing
# the DROP-TABLE silent-fail bug and the command-substitution RCE vector.
# Password via MYSQL_PWD (read from the container's own env), never on argv.
# DB name is passed as a positional to the inner shell, not string-spliced.
sql(){ printf '%s\n' "$1" | sudo docker exec -i "$DBC" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb -u root -N "$1"' _ "$DBN"; }
purge_cache(){ sudo docker exec "$C" sh -c 'find /var/www/html/wp-content/cache/wpo-cache -mindepth 1 -maxdepth 1 -type d ! -name config -exec rm -rf {} + 2>/dev/null; echo purged'; }

echo "Target: WP=$C  DB=$DBC/$DBN  prefix=$PFX"
echo "This performs DESTRUCTIVE database cleanup. Type YES to proceed:"
read -r ok; [ "$ok" = "YES" ] || { echo "Aborted."; exit 1; }

echo "==> [0] Backup DB first"
# Absolute path (independent of cwd) + mkdir -p so the redirect can't silently
# fail into nowhere. Then HARD-VERIFY the dump before any destructive step:
# valid gzip AND a sane size. With errexit+pipefail a failed dump already aborts;
# this also catches a "successful" gzip wrapping an empty/truncated dump.
BACKUP_DIR="$(cd "$(dirname "$0")" && pwd)/backups"
mkdir -p "$BACKUP_DIR"
BK="$BACKUP_DIR/${DBN}-preaudit-$(date +%F-%H%M).sql.gz"
sudo docker exec "$DBC" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb-dump --single-transaction --quick --routines -u root "$1"' _ "$DBN" \
  | gzip > "$BK"
if [ ! -s "$BK" ] || ! gzip -t "$BK" 2>/dev/null || [ "$(stat -c%s "$BK")" -lt 1024 ]; then
  echo "FATAL: backup missing/corrupt ($BK) — aborting before ANY destructive step." >&2
  exit 1
fi
echo "    backup OK: $BK ($(du -h "$BK" | cut -f1))"

# ---- C1: backdoor removal (security) — already done manually; documented here.
# A 2019 web shell lived at wp-content/.../phpinfo.php (dir literally named "...").
# Forensic copy preserved off-host, then:
#   sudo docker exec "$C" sh -c "rm -rf '/var/www/html/wp-content/.../'"

echo "==> [1] Prune AIOWPS audit log (was 1.34 GB / 276k rows of failed_login)"
# Keep ALL non-failed_login events (admin actions) + last 7 days of failed_login.
sql "DELETE FROM ${PFX}aiowps_audit_log WHERE event_type='failed_login' AND created < UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY);"
sql "OPTIMIZE TABLE ${PFX}aiowps_audit_log;"

echo "==> [2] Delete spam comments"
IDS="$(wp 'comment list --status=spam --field=ID' | tr '\n' ' ')"
[ -n "${IDS// }" ] && echo "$IDS" | xargs -r sudo docker exec "$C" wp --allow-root comment delete --force

echo "==> [3] Autoload trim + stale options"
sql "UPDATE ${PFX}options SET autoload='no' WHERE option_name='ws_menu_editor_pro';"     # admin-only, 108 KB
sql "DELETE FROM ${PFX}options WHERE option_name='rank_math_old_schema_data';"

echo "==> [4] Expired transients"
wp "transient delete --expired"

echo "==> [5] Drop orphaned SEO tables + options (Yoast + AIOSEO fully uninstalled; only Rank Math active)"
# Fail-CLOSED: we must POSITIVELY confirm the plugin folders are gone. If the
# `docker exec`/`ls` itself fails (container down, typo), abort this block —
# never fall through to DROP on an inconclusive check.
if ! plugin_list="$(sudo docker exec "$C" sh -c 'ls /var/www/html/wp-content/plugins/')"; then
  echo "    SKIPPED — cannot list plugins (exec failed); not touching tables." >&2
elif printf '%s\n' "$plugin_list" | grep -qiE 'yoast|aioseo|wordpress-seo|all-in-one-seo'; then
  echo "    SKIPPED — a Yoast/AIOSEO plugin folder is still present."
else
  # Table names come from a DB that was historically backdoored — validate each
  # against a strict identifier allowlist before dropping. Anything with unusual
  # characters is skipped + reported, never interpolated into SQL.
  while IFS= read -r t; do
    [ -z "$t" ] && continue
    case "$t" in
      *[!A-Za-z0-9_]*) echo "    WARN skip unsafe table name: $t" >&2; continue ;;
    esac
    sql "DROP TABLE IF EXISTS \`$t\`;"
  done < <(sql "SELECT table_name FROM information_schema.tables WHERE table_schema='$DBN' AND (table_name LIKE '%aioseo%' OR table_name LIKE '%yoast%');")
  # Escaped underscores so '_' is matched literally (it is a single-char wildcard
  # in LIKE). Prefix-anchored to avoid deleting unrelated plugins' options.
  sql "DELETE FROM ${PFX}options WHERE option_name LIKE 'aioseo%' OR option_name LIKE '\\_aioseo%' OR option_name LIKE 'wpseo%' OR option_name LIKE 'wp\\_yoast%' OR option_name LIKE 'yoast%' OR option_name LIKE '\\_yoast%';"
fi

# ---- Security hardening (WordPress + AIOS settings) ----
echo "==> [6] AIOS protections ON + disable public registration"
for k in aiowps_enable_login_lockdown aiowps_enable_404_IP_lockout aiowps_prevent_users_enumeration aiowps_disable_xmlrpc_pingback_methods; do
  wp "option patch update aio_wp_security_configs $k 1"
done
wp "option update users_can_register 0"

echo "==> [7] Block dashboard file editor (defense-in-depth vs RCE)"
wp "config set DISALLOW_FILE_EDIT true --raw --type=constant" || true

echo "==> [8] Update all plugins"
wp "plugin update --all"

purge_cache
echo "==> Done. Web-layer changes (Caddy: security headers + full xmlrpc.php 403)"
echo "    are in /etc/caddy/Caddyfile (dtisolution.id -> handle /blog*), not here."
echo "    Remember to purge Cloudflare cache if any front-end asset changed."
