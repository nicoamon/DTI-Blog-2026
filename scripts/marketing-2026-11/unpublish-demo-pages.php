<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only — never runnable over HTTP if scripts/ is ever copied to a webroot
/** Unpublish leftover theme-demo pages (Gridlove/King) + Rank Math 301 → /blog/. Idempotent, reversible. [dry]
 *  Only the 30 page IDs in $allow are ever touched (audited 13 Nov 2026); the front page, the posts page and the
 *  privacy page are always protected. Any other published page is skipped and reported, never unpublished. Rollback: for every page with meta _dti_demo_unpublished, set it back to
 *  publish and delete the Rank Math redirection whose ID is in its _dti_demo_redirect_id meta (absent = redirect pre-existed). */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
// wp_update_post() re-saves the WHOLE post through kses (content_save_pre) unless
// the user has unfiltered_html — a status-only change would still strip embeds,
// and a later rollback would restore the stripped content. Run with --user=nico.
if (!$dry && !current_user_can('unfiltered_html')) { WP_CLI::error('Run with --user=<admin> (needs unfiltered_html) — refusing to write through kses.'); }
$front = (int) get_option('page_on_front');
// Explicit allow-list (finding fc2407a8d8): 26 Gridlove/King demo pages + 4 empty 2022 module pages
// (knowledge, produk, event, trending) whose menus are not assigned to any location. Nothing else.
// ID => slug (post_name) as audited on prod 13 Nov 2026. A page is touched only when BOTH match:
// on an imported/restored DB or a non-identical staging clone the same ID can be another page.
$allow = [
  2 => 'sample-page', 22 => 'hot', 25 => 'trend', 28 => 'users', 95 => 'categories', 98 => 'nsfw',
  105 => 'sample-page-2', 469 => 'reactions', 1965 => 'simple-layout-a3', 1999 => 'combo-layouts',
  2005 => 'slider-layouts', 2011 => 'cover-layout-1', 2014 => 'cover-layout-2', 2016 => 'cover-layout-3',
  2018 => 'cover-layout-4', 2027 => 'styleguide', 2032 => 'shortcodes', 2035 => 'contact',
  2043 => 'custom-widgets', 2049 => 'page-right-sidebar', 2055 => 'page-left-sidebar', 2059 => 'page-no-sidebar',
  2139 => 'home-example-2-2', 2142 => 'home-example-4', 2145 => 'highlight-posts-example', 2157 => 'home-example-5',
  4282 => 'knowledge', 4289 => 'produk', 4291 => 'event', 4298 => 'trending',
];
$protect = array_filter([$front, (int) get_option('page_for_posts'), (int) get_option('wp_page_for_privacy_policy')]);
$skipped = 0;
$dest = trailingslashit(home_url());
$n = 0;
// Check the Rank Math API before touching anything: a missing class mid-loop
// would leave pages drafted with no redirect (404s).
if (!$dry && (!class_exists('\\RankMath\\Redirections\\Redirection') || !class_exists('\\RankMath\\Redirections\\DB'))) {
  WP_CLI::error('Rank Math Redirections module not available — nothing changed.');
}
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
  if (in_array($p->ID, $protect, true)) { $log("keep protected page {$p->ID} {$p->post_name}"); continue; }
  if (!isset($allow[$p->ID])) { $skipped++; $log("skip {$p->ID} {$p->post_name}: not a listed demo page"); continue; }
  if ($p->post_name !== $allow[$p->ID]) { $skipped++; WP_CLI::warning("skip {$p->ID} '{$p->post_name}': listed ID but the audited slug is '{$allow[$p->ID]}' — different database? Not touched."); continue; }
  $src = get_page_uri($p); $n++; // full path: child pages are parent/child, not just the last slug
  $log("{$p->ID} {$src} → draft + 301 to $dest");
  if ($dry) continue;
  // Redirect FIRST, then unpublish: a failure leaves the page live, never a 404.
  if (!$has_exact($src)) {
    $rid = \RankMath\Redirections\Redirection::from([
      'sources' => [['pattern' => $src, 'comparison' => 'exact']],
      'url_to' => $dest, 'header_code' => 301, 'status' => 'active',
    ])->save();
    if (!$rid) WP_CLI::error("{$p->ID} {$src}: redirect not saved — stopped, page left published.");
    update_post_meta($p->ID, '_dti_demo_redirect_id', $rid); $log("  redirect #$rid: $src -> $dest");
    $created++;
  } else { $existing++; }
  $r = wp_update_post(['ID' => $p->ID, 'post_status' => 'draft'], true);
  if (is_wp_error($r) || !$r) WP_CLI::error("{$p->ID} {$src}: unpublish failed — " . (is_wp_error($r) ? $r->get_error_message() : 'returned 0'));
  update_post_meta($p->ID, '_dti_demo_unpublished', '2026-11');
}
$log("pages drafted: $n · redirects created: $created · already existed: $existing · skipped (not listed): $skipped" . ($dry ? " (dry: nothing written)" : ""));
