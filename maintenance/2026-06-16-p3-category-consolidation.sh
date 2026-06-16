#!/usr/bin/env bash
# P3 — consolidate the blog's 25 categories down to 7 core topics + delete the
# "Project" category and its posts (decision by Nico). Posts are TRASHED (not
# force-deleted) so they're recoverable until trash is emptied.
#
# Usage:  ./2026-06-16-p3-category-consolidation.sh <wp-container>
#   staging:  dti-blog-wp-staging   |   prod: dti-blog-wp
# Requires wp-cli in the container. TAKE A DB BACKUP FIRST.
set -uo pipefail
C="${1:?Usage: $0 <wp-container> <db-name>}"
DBN="${2:?Usage: $0 <wp-container> <db-name>  (staging: wp_blogstaging, prod: wordpress)}"
DBC="dti-blog-db"
PFX="bl0gd0c_"
wp(){ sudo docker exec "$C" sh -c "wp $* --allow-root"; }
# raw SQL runs against the DB container (the WP image has no mysql client binary,
# so `wp db query` fails — this is the fix).
sql(){ sudo docker exec "$DBC" sh -c "mariadb -uroot -p\"\$MYSQL_ROOT_PASSWORD\" $DBN -N -e \"$1\""; }
ttid(){ sql "SELECT tt.term_taxonomy_id FROM ${PFX}terms t JOIN ${PFX}term_taxonomy tt ON t.term_id=tt.term_id WHERE t.slug='$1' AND tt.taxonomy='category';" | tr -d '[:space:]'; }
tid(){  sql "SELECT t.term_id FROM ${PFX}terms t JOIN ${PFX}term_taxonomy tt ON t.term_id=tt.term_id WHERE t.slug='$1' AND tt.taxonomy='category';" | tr -d '[:space:]'; }
ensure(){ # $1=name $2=slug -> echo term_id (create if missing)
  local id; id=$(tid "$2"); if [ -z "$id" ]; then id=$(wp "term create category \"$1\" --slug=$2 --porcelain"); fi; echo "$id" | tr -d '[:space:]'; }

echo "==> [$C] 1/6 Trash all posts in 'Project' (content deletion — authorized)"
PIDS=$(wp "post list --category_name=project --post_type=post --post_status=any --format=ids" | tr -d '\r')
if [ -n "$PIDS" ]; then wp "post delete $PIDS"; else echo "   (no project posts)"; fi

echo "==> [$C] 2/6 Ensure 7 core categories exist"
KN=$(tid knowledge); DS=$(tid digital-signature); HIS=$(tid health-information-system)
AID=$(ensure "AI & Data" ai-data)
SEC=$(ensure "Security" security)
PRD=$(ensure "Produk & Solusi" produk-solusi)
NWS=$(ensure "Perusahaan & Berita" perusahaan-berita)
KN_T=$(ttid knowledge); AID_T=$(ttid ai-data); SEC_T=$(ttid security); PRD_T=$(ttid produk-solusi); NWS_T=$(ttid perusahaan-berita)
echo "   Knowledge=$KN/$KN_T  AI&Data=$AID/$AID_T  Security=$SEC/$SEC_T  Produk&Solusi=$PRD/$PRD_T  Perusahaan&Berita=$NWS/$NWS_T"

# dedup-safe merge: move relationships source -> target, drop leftover dupes
merge(){ # $1=source_slug $2=target_ttid
  local s; s=$(ttid "$1"); [ -z "$s" ] && { echo "   skip $1 (missing)"; return 0; }
  [ "$s" = "$2" ] && { echo "   skip $1 (already target)"; return 0; }
  sql "UPDATE IGNORE ${PFX}term_relationships SET term_taxonomy_id=$2 WHERE term_taxonomy_id=$s; DELETE FROM ${PFX}term_relationships WHERE term_taxonomy_id=$s;"
  echo "   merged $1 -> ttid $2"
}

echo "==> [$C] 3/6 Merge source categories into targets"
merge tips "$KN_T"; merge trending "$KN_T"; merge uncategorized "$KN_T"
merge artificial-intelligence "$AID_T"; merge big-data "$AID_T"; merge iot "$AID_T"; merge rpa "$AID_T"
merge security-key "$SEC_T"; merge software "$SEC_T"; merge hardware "$SEC_T"
merge product "$PRD_T"; merge financial-technology "$PRD_T"; merge fds "$PRD_T"; merge case-study "$PRD_T"
merge events "$NWS_T"; merge office-life "$NWS_T"; merge news-docotel "$NWS_T"; merge company "$NWS_T"

echo "==> [$C] 4/6 Set default category to Knowledge + recount"
wp "option update default_category $KN"
wp "term recount category"

echo "==> [$C] 5/6 Delete emptied old categories, Project, and empty cats"
for s in tips trending uncategorized artificial-intelligence big-data iot rpa \
         security-key software hardware product financial-technology fds case-study \
         events office-life news-docotel company project infographic fsi graph-database; do
  id=$(tid "$s"); [ -n "$id" ] && wp "term delete category $id" >/dev/null 2>&1 && echo "   deleted $s"
done

echo "==> [$C] 6/6 Final category list"
wp "term list category --fields=term_id,slug,name,count --format=table"
echo "==> Purge page cache"
sudo docker exec "$C" sh -c 'find /var/www/html/wp-content/cache/wpo-cache -mindepth 1 -maxdepth 1 -type d ! -name config -exec rm -rf {} + 2>/dev/null; echo "   purged"'
