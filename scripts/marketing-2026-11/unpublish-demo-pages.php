<?php
/** Unpublish leftover theme-demo pages (Gridlove/King) + Rank Math 301 → /blog/. Idempotent, reversible. [dry]
 *  Keeps the front page (page_on_front). Rollback: for every page with meta _dti_demo_unpublished, set it back to
 *  publish and delete the Rank Math redirection whose ID is in its _dti_demo_redirect_id meta (absent = redirect pre-existed). */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
// wp_update_post() re-saves the WHOLE post through kses (content_save_pre) unless
// the user has unfiltered_html — a status-only change would still strip embeds,
// and a later rollback would restore the stripped content. Run with --user=nico.
if (!$dry && !current_user_can('unfiltered_html')) { WP_CLI::error('Run with --user=<admin> (needs unfiltered_html) — refusing to write through kses.'); }
$front = (int) get_option('page_on_front');
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
  if ($p->ID === $front) { $log("keep front page {$p->ID} {$p->post_name}"); continue; }
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
$log("pages drafted: $n · redirects created: $created · already existed: $existing" . ($dry ? " (dry: nothing written)" : ""));
