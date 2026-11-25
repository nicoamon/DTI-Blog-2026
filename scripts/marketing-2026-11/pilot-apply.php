<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** Pilot humanizer (ACC Nico 22 Nov): ganti post_content post tayang dengan versi yang sudah lolos
 *  gate + reviewer + Mode-2. Poles (batch 1): judul/meta/H1 TIDAK disentuh. Tulis ulang (batch 2; Nico: boleh
 *  ganti judul): baru-meta.json ikut menetapkan post_title + Rank Math. Slug TIDAK pernah diubah.
 *  wp eval-file pilot-apply.php <ID> <dir> humanizer=<x.y.z> [dry] [force] --user=nico
 *    <dir> berisi asli.html (isi yang direview) dan baru.html (versi baru).
 *    Tanpa `force`: menolak bila isi saat ini ≠ asli.html (ada suntingan setelah review).
 *    `force` hanya untuk staging (salinan pratinjau).
 *  Rollback: pilot-rollback.php <ID> [dry] --user=nico. Cadangan: _dti_prev_content_pilot (isi) dan, bila tulis ulang,
 *  _dti_pilot_prev_meta (JSON post_title + Rank Math; null = meta itu belum ada). Kunci khusus pilot, terpisah dari
 *  _dti_prev_rank_math_* milik skrip uji judul (apply-title-*.php). */
[$id, $dir] = [(int) ($args[0] ?? 0), rtrim($args[1] ?? '', '/')];
$dry = in_array('dry', $args, true); $force = in_array('force', $args, true);
$unknown = array_filter(array_slice($args, 2), fn($a) => !in_array($a, ['dry', 'force'], true) && !preg_match('/^humanizer=/', $a));
if ($unknown) WP_CLI::error('Argumen tidak dikenal: ' . implode(', ', $unknown) . ' — tidak ada yang ditulis. Boleh: dry, force, humanizer=<x.y.z>.');
// `force` overwrites whatever is in the post, so it is refused outside staging (prod must match the reviewed asli.html).
if ($force && strpos(home_url(), 'dti.amon.id') === false) WP_CLI::error('force hanya untuk staging — tidak ada yang ditulis.');
if (!$id || !is_readable("$dir/baru.html") || !is_readable("$dir/asli.html")) WP_CLI::error('Usage: <ID> <dir-with-asli.html-baru.html> humanizer=<x.y.z> [dry] [force]');
if (!$dry && !current_user_can('unfiltered_html')) WP_CLI::error('Run with --user=nico — nothing changed.');
$p = get_post($id); if (!$p || $p->post_type !== 'post') WP_CLI::error("$id bukan post");
$asli = file_get_contents("$dir/asli.html"); $baru = file_get_contents("$dir/baru.html");
// baru-meta.json (rewrites only) is validated BEFORE anything is written: a broken file must not leave new content under the old title.
$meta = null;
if (is_readable("$dir/baru-meta.json")) {
  $meta = json_decode(file_get_contents("$dir/baru-meta.json"), true);
  foreach (['post_title', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'] as $k)
    if (!is_array($meta) || !isset($meta[$k]) || !is_string($meta[$k]) || trim($meta[$k]) === '') WP_CLI::error("$id: baru-meta.json rusak atau '$k' kosong — tidak ada yang ditulis.");
}
// The stamp humanizer-amon@<versi> is a publication requirement, so the version must be passed explicitly
// (humanizer=<x.y.z>, read by the caller from the skill's VERSION file), never taken from a world-writable /tmp file.
$ver = ''; foreach ($args as $a) if (preg_match('/^humanizer=(\d+\.\d+\.\d+)$/', $a, $mm)) $ver = $mm[1];
if ($ver === '') WP_CLI::error('Wajib: humanizer=<x.y.z> (isi 00-Core/skills/humanizer-amon/VERSION) — tidak ada yang ditulis.');
// Content already new: stop only when there is no meta to (re)apply, so a run that failed half-way can be repeated.
// WordPress may add rel="noopener" to target="_blank" links on save (wp_targeted_link_rel): that is not a mismatch.
$norm = fn($s) => rtrim(function_exists('wp_targeted_link_rel') ? wp_targeted_link_rel((string) $s) : (string) $s);
$same = $norm($p->post_content) === $norm($baru);
if ($same && !is_readable("$dir/baru-meta.json")) { WP_CLI::log("$id: sudah versi baru"); return; }
if (!$same && rtrim($p->post_content) !== rtrim($asli) && !$force) WP_CLI::error("$id: isi saat ini berbeda dari asli.html (ada suntingan setelah review) — tidak ditulis.");
WP_CLI::log(($dry ? '[dry] ' : '') . "$id {$p->post_name}: " . strlen($p->post_content) . ' → ' . strlen($baru) . ' byte');
if ($dry) return;
// Never store the NEW content as the backup: when the post already holds baru.html there is no original left to save here.
if (!$same && !metadata_exists('post', $id, '_dti_prev_content_pilot')) update_post_meta($id, '_dti_prev_content_pilot', wp_slash($p->post_content));
// Rewrites (TULIS ULANG) also carry baru-meta.json: new H1/post_title + Rank Math title/description/focus keyword.
$upd = ['ID' => $id, 'post_content' => $baru];
if ($meta) {
  // One backup per post, taken once (never from an already-applied post): null marks "meta did not exist".
  if (!metadata_exists('post', $id, '_dti_pilot_prev_meta') && !metadata_exists('post', $id, '_dti_prev_post_title')) {
    $prev = ['post_title' => $p->post_title];
    foreach (['rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'] as $k) $prev[$k] = metadata_exists('post', $id, $k) ? get_post_meta($id, $k, true) : null;
    update_post_meta($id, '_dti_pilot_prev_meta', wp_slash(wp_json_encode($prev, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
  }
  $upd['post_title'] = $meta['post_title'];
}
$r = wp_update_post(wp_slash($upd), true);
if ($meta && !is_wp_error($r) && $r) foreach (['rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'] as $k) update_post_meta($id, $k, wp_slash($meta[$k]));
if (is_wp_error($r) || !$r) WP_CLI::error("$id: simpan gagal");
// Verify BEFORE stamping: the humanizer stamp is a publication requirement, so a post whose stored
// content differs from the reviewed baru.html must not carry it.
clean_post_cache($id);
if ($norm(get_post($id)->post_content) !== $norm($baru)) WP_CLI::error("$id: isi tersimpan BERBEDA dari baru.html — stempel humanizer TIDAK ditulis; periksa post; isi lama ada di meta _dti_prev_content_pilot.");
update_post_meta($id, 'humanizer', "humanizer-amon@$ver");
update_post_meta($id, '_dti_pilot', current_time('mysql'));
WP_CLI::success("$id diganti (humanizer-amon@$ver)");
