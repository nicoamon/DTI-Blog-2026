<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** Rollback pilot humanizer (pasangan pilot-apply.php).
 *  wp eval-file pilot-rollback.php <ID> [dry] [force] --user=nico
 *  Memulihkan post_content ← _dti_prev_content_pilot (isi SEBELUM pilot; diagram yang ditambahkan sesudahnya ikut hilang)
 *  dan, untuk tulis ulang, post_title + Rank Math ← _dti_pilot_prev_meta (JSON; null = hapus meta).
 *  Post batch 2 yang diterapkan sebelum 25 Nov memakai kunci lama _dti_prev_<k> ('' = hapus meta Rank Math).
 *  Menolak bila post tidak berstempel _dti_pilot, atau bila judul sudah disunting setelah pilot (tambahkan `force`).
 *  Cadangan yang dipakai dihapus, jadi pilot-apply berikutnya mengambil cadangan baru. */
$id = (int) ($args[0] ?? 0); $dry = in_array('dry', $args, true); $force = in_array('force', $args, true);
$unknown = array_diff(array_slice($args, 1), ['dry', 'force']);
if (!$id || $unknown) WP_CLI::error('Usage: <ID> [dry] [force] — tidak ada yang ditulis.');
if (!$dry && !current_user_can('unfiltered_html')) WP_CLI::error('Run with --user=nico — nothing changed.');
$p = get_post($id); if (!$p || $p->post_type !== 'post') WP_CLI::error("$id bukan post");
if (!metadata_exists('post', $id, '_dti_pilot')) WP_CLI::error("$id: tidak berstempel _dti_pilot — bukan post pilot, tidak ada yang ditulis.");
if (!metadata_exists('post', $id, '_dti_prev_content_pilot')) WP_CLI::error("$id: cadangan isi _dti_prev_content_pilot tidak ada — tidak ada yang ditulis.");
$keys = ['post_title', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'];
$prev = null; $legacy = false;
if (metadata_exists('post', $id, '_dti_pilot_prev_meta')) {
  $prev = json_decode(get_post_meta($id, '_dti_pilot_prev_meta', true), true);
  if (!is_array($prev) || !array_key_exists('post_title', $prev)) WP_CLI::error("$id: _dti_pilot_prev_meta rusak — tidak ada yang ditulis.");
} elseif (metadata_exists('post', $id, '_dti_prev_post_title')) {
  $legacy = true; $prev = [];
  foreach ($keys as $k) $prev[$k] = metadata_exists('post', $id, "_dti_prev_$k") ? (string) get_post_meta($id, "_dti_prev_$k", true) : false;
  foreach (['rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'] as $k) if ($prev[$k] === '') $prev[$k] = null; // '' = default Rank Math
}
if ($prev && is_string($prev['post_title'] ?? null) && trim($prev['post_title']) === '') WP_CLI::error("$id: cadangan judul kosong — tidak ada yang ditulis.");
// Defined once $prev is final: arrow functions capture variables by value.
$has = fn($k) => is_array($prev) && array_key_exists($k, $prev) && $prev[$k] !== false; // null = delete the meta, false = no backup
$tag = $dry ? '[dry] ' : '';
WP_CLI::log($tag . "$id: isi " . strlen($p->post_content) . ' → ' . strlen(get_post_meta($id, '_dti_prev_content_pilot', true)) . ' byte' . ($prev ? ' + judul/Rank Math' . ($legacy ? ' (kunci lama)' : '') : ''));
if ($prev) foreach ($keys as $k) if ($has($k)) WP_CLI::log($tag . "  $k ← " . ($prev[$k] === null ? '(hapus, kembali ke bawaan)' : $prev[$k]));
if ($dry) return;
$upd = ['ID' => $id, 'post_content' => get_post_meta($id, '_dti_prev_content_pilot', true)];
if ($prev && is_string($prev['post_title'] ?? null)) $upd['post_title'] = $prev['post_title'];
$r = wp_update_post(wp_slash($upd), true);
if (is_wp_error($r) || !$r) WP_CLI::error("$id: simpan gagal — tidak ada meta yang diubah");
if ($prev) foreach (['rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'] as $k) {
  if (!$has($k)) continue; // no backup for this key: leave it
  if ($prev[$k] === null) delete_post_meta($id, $k); else update_post_meta($id, $k, wp_slash($prev[$k]));
}
foreach (['_dti_prev_content_pilot', '_dti_pilot_prev_meta', 'humanizer', '_dti_pilot'] as $k) delete_post_meta($id, $k);
if ($legacy) foreach ($keys as $k) delete_post_meta($id, "_dti_prev_$k");
WP_CLI::success("$id dipulihkan ke isi sebelum pilot");
