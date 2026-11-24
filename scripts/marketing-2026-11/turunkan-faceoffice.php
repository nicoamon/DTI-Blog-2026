<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** FaceOffice tidak dijual lagi (Nico 22 Nov, "Turunkan 2, biarkan 3"): dua artikel promosi diturunkan.
 *  3752 → draft + 301 ke beranda blog; 2632 → draft + 301 ke artikel Tata Kelola (2610).
 *  wp eval-file turunkan-faceoffice.php [dry] --user=nico   · Idempoten.
 *  Rollback: post_status ← publish; hapus redirect Rank Math di meta _dti_fo_redirect_id (dibuat skrip ini).
 *  Redirect yang sudah ada sebelumnya (tujuan sama) dipakai ulang, dicatat di _dti_fo_redirect_reused dan TIDAK dihapus
 *  skrip; saat rollback nonaktifkan manual bila perlu. Redirect lama ke tujuan lain → post dilewati dengan peringatan. */
$unknown = array_diff($args ?? [], ['dry']); if ($unknown) WP_CLI::error('Unknown argument(s): ' . implode(', ', $unknown) . ' — nothing written.');
$dry = in_array('dry', $args ?? [], true);
if (!$dry && !current_user_can('unfiltered_html')) WP_CLI::error('Run with --user=nico — nothing written.');
if (!class_exists('\\RankMath\\Redirections\\Redirection') || !class_exists('\\RankMath\\Redirections\\DB')) WP_CLI::error('Rank Math Redirections tidak tersedia — nothing written.');
$targets = [3752 => null, 2632 => 2610];
$exists = function ($src) {
  foreach ((array) (\RankMath\Redirections\DB::get_redirections(['search' => $src, 'status' => 'active', 'limit' => 200])['redirections'] ?? []) as $row)
    foreach ((array) maybe_unserialize($row['sources'] ?? []) as $one) if (($one['pattern'] ?? '') === $src) return $row;
  return null;
};
// Redirect lama dipakai ulang hanya bila tujuannya sama (URL relatif dibaca terhadap host situs; skema & garis miring akhir diabaikan).
$sameDest = function ($a, $b) {
  $host = preg_replace('#^(https?://[^/]+).*$#i', '$1', home_url());
  $n = fn($u) => untrailingslashit(strtolower(preg_replace('#^https?://#i', '', preg_match('#^https?://#i', (string) $u) ? (string) $u : $host . '/' . ltrim((string) $u, '/'))));
  return $n($a) === $n($b);
};
foreach ($targets as $id => $to) {
  $p = get_post($id);
  if (!$p || $p->post_type !== 'post') { WP_CLI::warning("$id: tidak ada — dilewati"); continue; }
  if (stripos($p->post_content . $p->post_title, 'faceoffice') === false) { WP_CLI::warning("$id: tidak menyebut FaceOffice — database lain? dilewati"); continue; }
  if ($p->post_status !== 'publish') { WP_CLI::log("$id: sudah {$p->post_status}"); continue; }
  if ($to !== null) { $t = get_post($to); if (!$t || $t->post_status !== 'publish') { WP_CLI::warning("$id: tujuan $to tidak tayang — dilewati"); continue; } }
  $src = $p->post_name; $dest = $to === null ? trailingslashit(home_url()) : get_permalink($to);
  WP_CLI::log(($dry ? '[dry] ' : '') . "$id $src → draft + 301 ke $dest");
  if ($dry) continue;
  $row = $exists($src); $created = false;
  if ($row) {
    $rid = (int) $row['id'];
    if (!$sameDest($row['url_to'] ?? '', $dest)) { WP_CLI::warning("$id: redirect lama #$rid untuk $src menunjuk ke " . ($row['url_to'] ?? '?') . ", bukan $dest — dilewati, post tetap tayang; periksa manual"); continue; }
    update_post_meta($id, '_dti_fo_redirect_reused', $rid);
  } else {
    $rid = (int) \RankMath\Redirections\Redirection::from(['sources' => [['pattern' => $src, 'comparison' => 'exact']], 'url_to' => $dest, 'header_code' => 301, 'status' => 'active'])->save();
    if ($rid <= 0) { WP_CLI::warning("$id: redirect gagal — post tetap tayang"); continue; }
    $created = true;
    update_post_meta($id, '_dti_fo_redirect_id', $rid);
  }
  $r = wp_update_post(['ID' => $id, 'post_status' => 'draft'], true);
  if (is_wp_error($r) || !$r) {
    if ($created) { \RankMath\Redirections\DB::delete([$rid]); delete_post_meta($id, '_dti_fo_redirect_id'); WP_CLI::warning("$id: unpublish gagal — redirect yang baru dibuat dihapus lagi, post tetap tayang"); }
    else { delete_post_meta($id, '_dti_fo_redirect_reused'); WP_CLI::warning("$id: unpublish gagal — post tetap tayang; redirect lama #$rid (bukan buatan skrip ini) dibiarkan, periksa manual"); }
    continue;
  }
  update_post_meta($id, '_dti_fo', current_time('mysql'));
  WP_CLI::success("$id diturunkan");
}
