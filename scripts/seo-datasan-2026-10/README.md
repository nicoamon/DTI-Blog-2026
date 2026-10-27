# SEO-DATASAN-01 — blog part (27 Okt 2026)

Idempotent content migration for the 15 Sanitasi Data articles. Rehearsed on staging 27 Oct.
**Prod only after Nico's go, and only AFTER the website promotion** (the boxes link to the new LPs `panduan-pemusnahan-data` and `it-asset-disposal`).

What it does:
1. Adds a "Baca juga di DTI" box (3 relevant LP/catalog links) before the product CTA of 15 posts.
2. Rewrites internal links that point at the two merged thin posts.
3. Replaces post 5165 (kewajiban-pemusnahan-data-pribadi-uu-pdp, 334 → ~1,000 words) with `5165-new.html`.
4. Merges the thin posts:
   - 5164 → 5223 and 5166 → 5225: set to draft plus a Rank Math 301.

Run (prod):
```bash
set -a; source /home/nico/dti-blog/.env; set +a
sudo docker exec -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" dti-blog-db sh -c "mariadb-dump -u root --single-transaction wordpress > /tmp/bk.sql"
sudo docker cp dti-blog-db:/tmp/bk.sql /home/nico/dti-blog/backups/wordpress-preSEO-datasan-$(date +%Y%m%d-%H%M).sql
sudo docker exec dti-blog-wp mkdir -p /tmp/seo
sudo docker cp scripts/seo-datasan-2026-10/. dti-blog-wp:/tmp/seo/
sudo docker exec dti-blog-wp wp --allow-root eval-file /tmp/seo/apply-seo-datasan.php dry
sudo docker exec dti-blog-wp wp --allow-root eval-file /tmp/seo/apply-seo-datasan.php
sudo docker exec dti-blog-wp sh -c "wp cache flush --allow-root; rm -rf /var/www/html/wp-content/cache/wpo-cache/*"
```

Rollback:
- Restore the DB dump, or delete the 2 Rank Math redirections and republish 5164/5166.
- Post revisions also keep each previous version.
