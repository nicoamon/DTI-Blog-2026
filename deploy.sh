#!/usr/bin/env bash
# Deploy the DTI Blog child theme into a WordPress container and purge caches.
#
# The theme lives at ./dti-theme/ in git but WordPress knows it by the folder
# name **dti-blog** (the active child theme, Template: gridlove). This script is
# the single source of truth for that copy step — do not copy by hand.
#
# Usage:
#   ./deploy.sh staging     # -> dti-blog-wp-staging
#   ./deploy.sh prod        # -> dti-blog-wp   (production; asks for confirmation)
#
# Standing workflow (see memory: workflow-staging-before-prod):
#   edit -> ./deploy.sh staging -> Nico checks -> commit+push (staging branch)
#   -> ./deploy.sh prod -> commit+push (main branch).
set -uo pipefail

SRC_DIR="$(cd "$(dirname "$0")" && pwd)/dti-theme"
DEST_SLUG="dti-blog"   # folder name inside wp-content/themes on the server
DEST_PATH="/var/www/html/wp-content/themes/${DEST_SLUG}"

case "${1:-}" in
  staging) C="dti-blog-wp-staging" ;;
  prod)    C="dti-blog-wp" ;;
  *) echo "Usage: $0 <staging|prod>"; exit 2 ;;
esac

[ -d "$SRC_DIR" ] || { echo "Source theme not found: $SRC_DIR"; exit 1; }

if [ "$1" = "prod" ]; then
  echo "About to deploy the theme to PRODUCTION ($C). Type YES to proceed:"
  read -r ok
  [ "$ok" = "YES" ] || { echo "Aborted."; exit 1; }
fi

echo "==> [$C] Deploying $(basename "$SRC_DIR")/ -> $DEST_PATH"
# Copy each tracked theme file (php/css + assets). docker cp of the dir contents
# keeps the container's folder name (dti-blog) regardless of the repo name.
sudo docker cp "$SRC_DIR/." "$C:$DEST_PATH/" || { echo "copy failed"; exit 1; }
sudo docker exec "$C" sh -c "chown -R www-data:www-data '$DEST_PATH'"

echo "==> [$C] Verifying PHP syntax of deployed files"
for f in functions.php header.php footer.php; do
  sudo docker exec "$C" php -l "$DEST_PATH/$f" || { echo "PHP lint failed on $f"; exit 1; }
done

echo "==> [$C] Purging WP-Optimize page cache"
sudo docker exec "$C" sh -c 'find /var/www/html/wp-content/cache/wpo-cache -mindepth 1 -maxdepth 1 -type d ! -name config -exec rm -rf {} + 2>/dev/null; echo purged'

echo "==> Done. If this was prod, remember to purge Cloudflare cache too."
