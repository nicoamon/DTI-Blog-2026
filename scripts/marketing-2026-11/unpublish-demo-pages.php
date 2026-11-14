<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only — never runnable over HTTP if scripts/ is ever copied to a webroot
/** Unpublish leftover theme-demo pages (Gridlove/King) + Rank Math 301 → /blog/. Idempotent, reversible. [dry]
 *  Only the 30 page IDs in $allow are ever touched (audited 13 Nov 2026); the front page, the posts page and the
 *  privacy page are always protected. Any other published page is skipped and reported, never unpublished. Rollback: for every page
 *  with meta _dti_demo_unpublished OR _dti_demo_redirect_id, set it back to publish and delete the Rank Math redirection whose ID is
 *  in its _dti_demo_redirect_id meta (absent = redirect pre-existed). */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
// wp_update_post() re-saves the WHOLE post through kses (content_save_pre) unless
// the user has unfiltered_html — a status-only change would still strip embeds,
// and a later rollback would restore the stripped content. Run with --user=nico.
if (!$dry && !current_user_can('unfiltered_html')) { WP_CLI::error('Run with --user=<admin> (needs unfiltered_html) — refusing to write through kses.'); }
$front = (int) get_option('page_on_front');
// Explicit allow-list (finding fc2407a8d8): 26 Gridlove/King demo pages + 4 empty 2022 module pages
// (knowledge, produk, event, trending) whose menus are not assigned to any location. Nothing else.
$allow = [2, 22, 25, 28, 95, 98, 105, 469, 1965, 1999, 2005, 2011, 2014, 2016, 2018, 2027, 2032, 2035,
          2043, 2049, 2055, 2059, 2139, 2142, 2145, 2157, 4282, 4289, 4291, 4298];
$protect = array_filter([$front, (int) get_option('page_for_posts'), (int) get_option('wp_page_for_privacy_policy')]);
$skipped = 0;
$dest = trailingslashit(home_url());
$n = 0;
// Check the Rank Math API before touching anything: a missing class mid-loop
// would leave pages drafted with no redirect (404s).
if (!$dry && (!class_exists('\\RankMath\\Redirections\\Redirection') || !class_exists('\\RankMath\\Redirections\\DB'))) {
  WP_CLI::error('Rank Math Redirections module not available — nothing changed.');
}
// Look for an ACTIVE redirect whose pattern is exactly $src. Not match_redirections_source():
// it returns only ONE row (LIMIT 1, newest first) of a LIKE %source% search, so for 'trend'
// it can return the 'trending' row and miss the exact one. A missed match then makes save()
// (DB::update_iff) UPDATE that existing row and return its ID, which we would record as ours
// and a rollback would delete. Read every LIKE match and compare the pattern exactly.
$has_exact = function ($src) {
  $res = \RankMath\Redirections\DB::get_redirections(['search' => $src, 'status' => 'active', 'limit' => 1000]);
  foreach ((array) ($res['redirections'] ?? []) as $row) {
    if (($row['status'] ?? '') !== 'active') continue;
    foreach ((array) maybe_unserialize($row['sources'] ?? []) as $one) { if (($one['pattern'] ?? null) === $src) return true; }
  }
  return false;
};
$limit = 500; $created = 0; $existing = 0;
$pages = get_pages(['post_status' => 'publish', 'number' => $limit]);
if (count($pages) >= $limit) WP_CLI::warning("Hit the $limit-page limit — the list may be truncated; run again after this batch.");
foreach ($pages as $p) {
  if (in_array($p->ID, $protect, true)) { $log("keep protected page {$p->ID} {$p->post_name}"); continue; }
  if (!in_array($p->ID, $allow, true)) { $skipped++; $log("skip {$p->ID} {$p->post_name}: not a listed demo page"); continue; }
  $src = get_page_uri($p); $n++; // full path: child pages are parent/child, not just the last slug
  $log("{$p->ID} {$src} → draft + 301 to $dest");
  if ($dry) continue;
  // Redirect FIRST, then unpublish: no window where the page is gone without a redirect (404).
  // If the unpublish then fails, the redirect created here is removed again (below).
  $rid = 0;
  if (!$has_exact($src)) {
    $rid = \RankMath\Redirections\Redirection::from([
      'sources' => [['pattern' => $src, 'comparison' => 'exact']],
      'url_to' => $dest, 'header_code' => 301, 'status' => 'active',
    ])->save();
    // Rank Math 1.0.275: save() returns false (no sources), the insert ID (int, 0 on a failed
    // insert) or an existing row's ID as a numeric string. Store only a positive integer.
    if (!is_numeric($rid) || (int) $rid <= 0) WP_CLI::error("{$p->ID} {$src}: redirect not saved (save() returned " . var_export($rid, true) . ") — stopped, page left published.");
    $rid = (int) $rid;
    update_post_meta($p->ID, '_dti_demo_redirect_id', $rid); $log("  redirect #$rid: $src -> $dest");
    $created++;
  } else { $existing++; }
  $r = wp_update_post(['ID' => $p->ID, 'post_status' => 'draft'], true);
  if (is_wp_error($r) || !$r) {
    $why = is_wp_error($r) ? $r->get_error_message() : 'returned 0';
    // Otherwise an active 301 would hide a page that is still published, with no
    // _dti_demo_unpublished marker for the rollback to find it.
    if ($rid) {
      $gone = \RankMath\Redirections\DB::delete([$rid]);
      delete_post_meta($p->ID, '_dti_demo_redirect_id');
      WP_CLI::error("{$p->ID} {$src}: unpublish failed — $why. " . ($gone ? "Redirect #$rid removed again; page left published, no redirect." : "Redirect #$rid could NOT be removed — delete it in Rank Math → Redirections."));
    }
    WP_CLI::error("{$p->ID} {$src}: unpublish failed — $why. Page left published (its redirect pre-existed).");
  }
  update_post_meta($p->ID, '_dti_demo_unpublished', '2026-11');
}
$log("pages drafted: $n · redirects created: $created · already existed: $existing · skipped (not listed): $skipped" . ($dry ? " (dry: nothing written)" : ""));
