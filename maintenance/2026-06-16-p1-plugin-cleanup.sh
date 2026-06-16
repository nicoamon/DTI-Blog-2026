#!/usr/bin/env bash
# P1 — plugin & security cleanup for the DTI blog (WordPress).
# Idempotent-ish: re-running just reports "not found" for already-removed plugins.
#
# Usage:  ./2026-06-16-p1-plugin-cleanup.sh <wp-container>
#   staging:  dti-blog-wp-staging
#   prod:     dti-blog-wp
#
# Requires wp-cli at /usr/local/bin/wp inside the container.
set -uo pipefail
C="${1:?Usage: $0 <wp-container>}"
wp() { sudo docker exec "$C" sh -c "wp $* --allow-root"; }

echo "==> [$C] 1/4 Delete INACTIVE / redundant plugins (not running)"
# redundant SEO (kept: Rank Math) · static-export experiments · very old / sample
wp plugin delete \
  advanced-custom-fields-pro \
  all-in-one-seo-pack \
  hello \
  re-add-underline-justify \
  simply-static \
  wp-custom-admin-dashboard2 \
  static-html-output-plugin \
  wordpress-seo

echo "==> [$C] 2/4 Delete unused legacy Meks widget plugins"
# All instances live in widget areas the DTI theme no longer renders
# (gridlove footer sidebars + header side panel). Footer social icons are
# hardcoded in the DTI footer, so the social widget is redundant too.
wp plugin delete \
  meks-easy-ads-widget \
  meks-simple-flickr-widget \
  meks-themeforest-smart-widget \
  meks-smart-social-widget \
  meks-smart-author-widget

echo "==> [$C] 3/4 Update all remaining plugins to latest"
wp plugin update --all

echo "==> [$C] 4/4 Deactivate one-time-use importer (migration complete)"
wp plugin deactivate wordpress-importer

echo "==> Purge WP-Optimize page cache"
sudo docker exec "$C" sh -c 'find /var/www/html/wp-content/cache/wpo-cache -mindepth 1 -maxdepth 1 -type d ! -name config -exec rm -rf {} + 2>/dev/null; echo "   purged"'

echo "==> Done. Remaining plugins:"
wp plugin list --fields=name,status,version
