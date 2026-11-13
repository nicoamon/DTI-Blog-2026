<?php
/** Unpublish leftover theme-demo pages (Gridlove/King) + Rank Math 301 → /blog/. Idempotent, reversible. [dry]
 *  Keeps the front page (page_on_front). Rollback: set the pages back to publish and delete the redirections tagged in this run. */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
// wp_update_post() re-saves the WHOLE post through kses (content_save_pre) unless
// the user has unfiltered_html — a status-only change would still strip embeds,
// and a later rollback would restore the stripped content. Run with --user=nico.
if (!$dry && !current_user_can('unfiltered_html')) { WP_CLI::error('Run with --user=<admin> (needs unfiltered_html) — refusing to write through kses.'); }
$front = (int) get_option('page_on_front');
$dest = trailingslashit(home_url());
$n = 0;
foreach (get_pages(['post_status' => 'publish', 'number' => 500]) as $p) {
  if ($p->ID === $front) { $log("keep front page {$p->ID} {$p->post_name}"); continue; }
  $src = $p->post_name; $n++;
  $log("{$p->ID} {$src} → draft + 301 to $dest");
  if ($dry) continue;
  wp_update_post(['ID' => $p->ID, 'post_status' => 'draft']);
  update_post_meta($p->ID, '_dti_demo_unpublished', '2026-11');
  if (!\RankMath\Redirections\DB::match_redirections_source($src)) {
    \RankMath\Redirections\Redirection::from([
      'sources' => [['pattern' => $src, 'comparison' => 'exact']],
      'url_to' => $dest, 'header_code' => 301, 'status' => 'active',
    ])->save();
  }
}
$log("pages processed: $n");
