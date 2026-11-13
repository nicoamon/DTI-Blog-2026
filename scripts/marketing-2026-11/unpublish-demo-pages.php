<?php
/** Unpublish leftover theme-demo pages (Gridlove/King) + Rank Math 301 → /blog/. Idempotent, reversible. [dry]
 *  Keeps the front page (page_on_front). Rollback: set the pages back to publish and delete the redirections tagged in this run. */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
$front = (int) get_option('page_on_front');
$dest = trailingslashit(home_url());
$n = 0;
// Check the Rank Math API before touching anything: a missing class mid-loop
// would leave pages drafted with no redirect (404s).
if (!$dry && (!class_exists('\\RankMath\\Redirections\\Redirection') || !class_exists('\\RankMath\\Redirections\\DB'))) {
  WP_CLI::error('Rank Math Redirections module not available — nothing changed.');
}
foreach (get_pages(['post_status' => 'publish', 'number' => 500]) as $p) {
  if ($p->ID === $front) { $log("keep front page {$p->ID} {$p->post_name}"); continue; }
  $src = $p->post_name; $n++;
  $log("{$p->ID} {$src} → draft + 301 to $dest");
  if ($dry) continue;
  // Redirect FIRST, then unpublish: a failure leaves the page live, never a 404.
  if (!\RankMath\Redirections\DB::match_redirections_source($src)) {
    $rid = \RankMath\Redirections\Redirection::from([
      'sources' => [['pattern' => $src, 'comparison' => 'exact']],
      'url_to' => $dest, 'header_code' => 301, 'status' => 'active',
    ])->save();
    if (!$rid) WP_CLI::error("{$p->ID} {$src}: redirect not saved — stopped, page left published.");
  }
  $r = wp_update_post(['ID' => $p->ID, 'post_status' => 'draft'], true);
  if (is_wp_error($r) || !$r) WP_CLI::error("{$p->ID} {$src}: unpublish failed — " . (is_wp_error($r) ? $r->get_error_message() : 'returned 0'));
  update_post_meta($p->ID, '_dti_demo_unpublished', '2026-11');
}
$log("pages processed: $n");
