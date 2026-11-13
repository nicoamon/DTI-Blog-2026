<?php
/** Unpublish leftover theme-demo pages (Gridlove/King) + Rank Math 301 → /blog/. Idempotent, reversible. [dry]
 *  Keeps the front page (page_on_front). Rollback: for every page with meta _dti_demo_unpublished, set it back to
 *  publish and delete the Rank Math redirection whose ID is in its _dti_demo_redirect_id meta (absent = redirect pre-existed). */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
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
    $rid = \RankMath\Redirections\Redirection::from([
      'sources' => [['pattern' => $src, 'comparison' => 'exact']],
      'url_to' => $dest, 'header_code' => 301, 'status' => 'active',
    ])->save();
    if ($rid) { update_post_meta($p->ID, '_dti_demo_redirect_id', $rid); $log("  redirect #$rid: $src -> $dest"); }
  }
}
$log("pages processed: $n");
