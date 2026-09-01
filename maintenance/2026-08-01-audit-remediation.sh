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
# 2026-09-02 CONSOLIDATION (Night Shift): merges BOTH hardening rounds that had
# diverged across branches — a4d2d21 (staging: STDIN sql closes the backtick
# command-substitution RCE + silent-DROP bug, MYSQL_PWD, backup verification,
# fail-closed plugin check, identifier allowlist) and 5fc5d41 (main, F5: umask
# 077 dump, wp "$@", prefix-anchored LIKE, PFX guard, capture-to-var listing).
# Deliberate choice: NO global `set -e` (benign non-zero constructs exist);
# every destructive step instead has an explicit fail-closed guard.
set -uo pipefail

C="${1:?Usage: $0 <wp-container> <db-container> <db-name>}"
DBC="${2:?db-container required}"
DBN="${3:?db-name required}"
# PFX pipeline wrapped (|| true) so a docker/wp failure still reaches the
# friendly message below instead of aborting mid-pipeline; no sh -c splice
# (findings F5-6 and F5-4, queue/2026-08-11.md).
PFX="$(sudo docker exec "$C" wp config get table_prefix --allow-root 2>/dev/null | tr -d '[:space:]')" || true
[ -z "$PFX" ] && { echo "Cannot determine table_prefix"; exit 1; }

# Pass args separately (no "$*"/sh -c string splicing) — finding F5-4.
wp(){ sudo docker exec "$C" wp "$@" --allow-root; }
# SQL is piped to the client via STDIN — NOT interpolated into a `sh -c` string
# and NOT passed via `-e`. Query text (incl. backtick-quoted identifiers) can
# never be re-parsed by a shell inside the container: closes the DROP-TABLE
# silent-fail bug and the command-substitution RCE vector (CRITICAL, audit 9/8).
# Password via MYSQL_PWD (container env), never on argv. DB name is a positional.
sql(){ printf '%s\n' "$1" | sudo docker exec -i "$DBC" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb -u root -N "$1"' _ "$DBN"; }
purge_cache(){ sudo docker exec "$C" sh -c 'find /var/www/html/wp-content/cache/wpo-cache -mindepth 1 -maxdepth 1 -type d ! -name config -exec rm -rf {} + 2>/dev/null; echo purged'; }

echo "Target: WP=$C  DB=$DBC/$DBN  prefix=$PFX"
echo "This performs DESTRUCTIVE database cleanup. Type YES to proceed:"
read -r ok; [ "$ok" = "YES" ] || { echo "Aborted."; exit 1; }

echo "==> [0] Backup DB first"
# Absolute path (independent of cwd) + mkdir -p so the redirect can't silently
# fail into nowhere. Dump contains WP password hashes + plugin secrets and this
# host has a history of compromise → owner-only artifact: umask 077 + chmod 600
# (ops-standard AMON #4, finding F5-3). Then HARD-VERIFY the dump before ANY
# destructive step: exists, valid gzip, sane size (fail-closed).
BACKUP_DIR="$(cd "$(dirname "$0")" && pwd)/backups"
mkdir -p "$BACKUP_DIR"
BK="$BACKUP_DIR/${DBN}-preaudit-$(date +%F-%H%M).sql.gz"
( umask 077
  sudo docker exec "$DBC" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb-dump --single-transaction --quick --routines -u root "$1"' _ "$DBN" \
    | gzip > "$BK" )
chmod 600 "$BK"
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
IDS="$(wp comment list --status=spam --field=ID | tr '\n' ' ')"
if [ -n "${IDS// }" ]; then
  echo "$IDS" | xargs -r sudo docker exec "$C" wp --allow-root comment delete --force
fi

echo "==> [3] Autoload trim + stale options"
sql "UPDATE ${PFX}options SET autoload='no' WHERE option_name='ws_menu_editor_pro';"     # admin-only, 108 KB
sql "DELETE FROM ${PFX}options WHERE option_name='rank_math_old_schema_data';"

echo "==> [4] Expired transients"
wp transient delete --expired

echo "==> [5] Drop orphaned SEO tables + options (Yoast + AIOSEO fully uninstalled; only Rank Math active)"
# Fail-CLOSED: POSITIVELY confirm the plugin folders are gone. If the exec/ls
# itself fails (container down, typo), abort this block — never fall through
# to DROP on an inconclusive check (finding audit 9/8 MEDIUM).
if ! plugin_list="$(sudo docker exec "$C" sh -c 'ls /var/www/html/wp-content/plugins/')"; then
  echo "    SKIPPED — cannot list plugins (exec failed); not touching tables." >&2
elif printf '%s\n' "$plugin_list" | grep -qiE 'yoast|aioseo|wordpress-seo|all-in-one-seo'; then
  echo "    SKIPPED — a Yoast/AIOSEO plugin folder is still present."
else
  # Capture the listing so a failed query (DB down/auth) aborts instead of
  # silently iterating zero tables (finding F5-1); LIKE anchored to this
  # install's prefix (finding F5-5); every name validated against a strict
  # identifier allowlist before touching SQL — this DB was historically
  # backdoored (finding audit 9/8 CRITICAL).
  tables="$(sql "SELECT table_name FROM information_schema.tables WHERE table_schema='$DBN' AND (table_name LIKE '${PFX}aioseo%' OR table_name LIKE '${PFX}yoast%');")" \
    || { echo "    ERROR: failed to list orphaned SEO tables — aborting drop." >&2; exit 1; }
  while IFS= read -r t; do
    [ -z "$t" ] && continue
    case "$t" in
      *[!A-Za-z0-9_]*) echo "    WARN skip unsafe table name: $t" >&2; continue ;;
    esac
    sql "DROP TABLE IF EXISTS \`$t\`;"
  done <<< "$tables"
  # Escaped underscores ('_' is a single-char LIKE wildcard) + prefix-anchored
  # patterns so unrelated plugins' options are never deleted (audit 9/8 MEDIUM).
  sql "DELETE FROM ${PFX}options WHERE option_name LIKE 'aioseo%' OR option_name LIKE '\\_aioseo%' OR option_name LIKE 'wpseo%' OR option_name LIKE 'wp\\_yoast%' OR option_name LIKE 'yoast%' OR option_name LIKE '\\_yoast%';"
fi

# ---- Security hardening (WordPress + AIOS settings) ----
echo "==> [6] AIOS protections ON + disable public registration"
for k in aiowps_enable_login_lockdown aiowps_enable_404_IP_lockout aiowps_prevent_users_enumeration aiowps_disable_xmlrpc_pingback_methods; do
  wp option patch update aio_wp_security_configs "$k" 1
done
wp option update users_can_register 0

echo "==> [7] Block dashboard file editor (defense-in-depth vs RCE)"
wp config set DISALLOW_FILE_EDIT true --raw --type=constant || true

echo "==> [8] Update all plugins"
wp plugin update --all

purge_cache
echo "==> Done. Web-layer changes (Caddy: security headers + full xmlrpc.php 403)"
echo "    are in /etc/caddy/Caddyfile (dtisolution.id -> handle /blog*), not here."
echo "    Remember to purge Cloudflare cache if any front-end asset changed."
