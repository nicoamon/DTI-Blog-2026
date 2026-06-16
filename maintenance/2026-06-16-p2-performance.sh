#!/usr/bin/env bash
# P2 — performance. Documents the server/infra steps (not WordPress code; the
# theme-side perf changes live in dti-theme/functions.php under git).
#
# Two layers:
#   1) Cloudflare zone settings (production edge) — applied via API.
#   2) EWWW Image Optimizer (WebP) — WordPress plugin, applied per WP container.
#
# Cloudflare: requires an API token with Zone Settings:Edit + Cache Purge.
#   export CF_TOKEN=...   ; ZONE for dtisolution.id = 1b5e9657e4484845dda08fe5f35a5fe5
# NOTE: zone is on the FREE plan → Polish (WebP/AVIF) + Mirage are NOT available,
# so WebP is handled at the origin by EWWW instead. Auto-Minify was retired by
# Cloudflare (2024). Settings we DID enable on Free:
#   brotli=on, early_hints=on, tiered_caching=on, rocket_loader=OFF
#   (rocket_loader gave no measured benefit and risks the grid/jQuery JS).
cf(){ # $1=setting $2=value   (uses $CF_TOKEN, $ZONE)
  curl -s -X PATCH "https://api.cloudflare.com/client/v4/zones/$ZONE/settings/$1" \
    -H "Authorization: Bearer $CF_TOKEN" -H "Content-Type: application/json" \
    --data "{\"value\":\"$2\"}" | grep -o '"success":[a-z]*'
}
# ZONE=1b5e9657e4484845dda08fe5f35a5fe5
# cf brotli on ; cf early_hints on ; cf rocket_loader off
# curl -s -X PATCH ".../zones/$ZONE/argo/tiered_caching" -H "Authorization: Bearer $CF_TOKEN" --data '{"value":"on"}'
# Purge:  curl -s -X POST ".../zones/$ZONE/purge_cache" -H "Authorization: Bearer $CF_TOKEN" --data '{"purge_everything":true}'

# ---- EWWW (WebP at origin) — run inside a WP container ----
# Usage: ./2026-06-16-p2-performance.sh <wp-container>
C="${1:-}"
[ -z "$C" ] && { echo "Cloudflare section is reference-only; pass a wp-container to run EWWW setup."; exit 0; }
wp(){ sudo docker exec "$C" sh -c "wp $* --allow-root"; }

echo "==> [$C] Install + activate EWWW Image Optimizer"
wp "plugin install ewww-image-optimizer --activate"

echo "==> [$C] Configure: WebP (JS/data-webp method — safe behind CDN), lazy-load, resize, strip metadata"
for kv in \
  "ewww_image_optimizer_webp 1" \
  "ewww_image_optimizer_webp_for_cdn 1" \
  "ewww_image_optimizer_lazy_load 1" \
  "ewww_image_optimizer_maxmediawidth 1920" \
  "ewww_image_optimizer_maxmediaheight 1920" \
  "ewww_image_optimizer_metadata_remove 1" \
  "ewww_image_optimizer_jpg_quality 82"; do
  wp "option update $kv"
done

echo "==> [$C] Generate WebP for the whole media library (background-friendly; hours for a large library)"
wp "ewwwio optimize media --webp-only --noprompt"

echo "==> [$C] Purge WP-Optimize page cache"
sudo docker exec "$C" sh -c 'find /var/www/html/wp-content/cache/wpo-cache -mindepth 1 -maxdepth 1 -type d ! -name config -exec rm -rf {} + 2>/dev/null; echo purged'
echo "==> Done. Also purge Cloudflare cache afterwards."
