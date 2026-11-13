<?php
/** Unpublish leftover theme-demo pages (Gridlove/King) + Rank Math 301 → /blog/. Idempotent, reversible. [dry]
 *  Keeps the front page (page_on_front). Rollback: set the pages back to publish and delete the redirections tagged in this run. */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
$front = (int) get_option('page_on_front');
$dest = trailingslashit(home_url());
$n = 0;
// match_redirections_source() is a LIKE %source% search, so 'contact' would also
// match an existing 'contact-us' redirect and wrongly skip. Require an exact pattern.
$has_exact = function ($src) {
  foreach ((array) \RankMath\Redirections\DB::match_redirections_source($src) as $row) {
    foreach ((array) maybe_unserialize($row['sources'] ?? []) as $one) { if (($one['pattern'] ?? null) === $src) return true; }
  }
  return false;
};
$limit = 500; $created = 0; $existing = 0;
$pages = get_pages(['post_status' => 'publish', 'number' => $limit]);
if (count($pages) >= $limit) WP_CLI::warning("Hit the $limit-page limit — the list may be truncated; run again after this batch.");
foreach ($pages as $p) {
  if ($p->ID === $front) { $log("keep front page {$p->ID} {$p->post_name}"); continue; }
  $src = get_page_uri($p); $n++; // full path: child pages are parent/child, not just the last slug
  $log("{$p->ID} {$src} → draft + 301 to $dest");
  if ($dry) continue;
  wp_update_post(['ID' => $p->ID, 'post_status' => 'draft']);
  update_post_meta($p->ID, '_dti_demo_unpublished', '2026-11');
  if (!$has_exact($src)) {
    \RankMath\Redirections\Redirection::from([
      'sources' => [['pattern' => $src, 'comparison' => 'exact']],
      'url_to' => $dest, 'header_code' => 301, 'status' => 'active',
    ])->save();
    $created++;
  } else { $existing++; }
}
$log("pages drafted: $n · redirects created: $created · already existed: $existing" . ($dry ? " (dry: nothing written)" : ""));
