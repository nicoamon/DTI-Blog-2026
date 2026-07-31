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
set -uo pipefail

C="${1:?Usage: $0 <wp-container> <db-container> <db-name>}"
DBC="${2:?db-container required}"
DBN="${3:?db-name required}"
PFX="$(sudo docker exec "$C" sh -c "wp config get table_prefix --allow-root" 2>/dev/null | tr -d '[:space:]')"
[ -z "$PFX" ] && { echo "Cannot determine table_prefix"; exit 1; }

wp(){ sudo docker exec "$C" sh -c "wp $* --allow-root"; }
sql(){ sudo docker exec "$DBC" sh -c "mariadb -u root -p\"\$MYSQL_ROOT_PASSWORD\" $DBN -N -e \"$1\""; }
purge_cache(){ sudo docker exec "$C" sh -c 'find /var/www/html/wp-content/cache/wpo-cache -mindepth 1 -maxdepth 1 -type d ! -name config -exec rm -rf {} + 2>/dev/null; echo purged'; }

echo "Target: WP=$C  DB=$DBC/$DBN  prefix=$PFX"
echo "This performs DESTRUCTIVE database cleanup. Type YES to proceed:"
read -r ok; [ "$ok" = "YES" ] || { echo "Aborted."; exit 1; }

echo "==> [0] Backup DB first"
sudo docker exec "$DBC" sh -c "mariadb-dump --single-transaction --quick --routines -u root -p\"\$MYSQL_ROOT_PASSWORD\" $DBN" \
  | gzip > "backups/${DBN}-preaudit-$(date +%F-%H%M).sql.gz"

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
# Safety: only if the plugin folders are truly gone.
if ! sudo docker exec "$C" sh -c "ls /var/www/html/wp-content/plugins/ | grep -qiE 'yoast|aioseo|wordpress-seo|all-in-one-seo'"; then
  for t in $(sql "SELECT table_name FROM information_schema.tables WHERE table_schema='$DBN' AND (table_name LIKE '%aioseo%' OR table_name LIKE '%yoast%');"); do
    sql "DROP TABLE IF EXISTS \`$t\`;"
  done
  sql "DELETE FROM ${PFX}options WHERE option_name LIKE 'aioseo%' OR option_name LIKE '_aioseo%' OR option_name LIKE 'wpseo%' OR option_name LIKE 'wp_yoast%' OR option_name LIKE 'yoast%' OR option_name LIKE '%_yoast%';"
else
  echo "    SKIPPED — a Yoast/AIOSEO plugin folder is still present."
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
