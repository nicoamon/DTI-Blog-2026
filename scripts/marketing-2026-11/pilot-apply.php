<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** Pilot humanizer (ACC Nico 22 Nov): ganti post_content post tayang dengan versi yang sudah lolos
 *  pilot-cek.py + reviewer + Mode-2. Judul, slug, meta Rank Math, H1 TIDAK disentuh.
 *  wp eval-file pilot-apply.php <ID> <dir> [dry] [force] --user=nico
 *    <dir> berisi asli.html (isi yang direview) dan baru.html (versi baru).
 *    Tanpa `force`: menolak bila isi saat ini ≠ asli.html (ada suntingan setelah review).
 *    `force` hanya untuk staging (salinan pratinjau).
 *  Rollback: post_content ← meta _dti_prev_content_pilot; hapus meta humanizer & _dti_pilot. */
[$id, $dir] = [(int) ($args[0] ?? 0), rtrim($args[1] ?? '', '/')];
$dry = in_array('dry', $args, true); $force = in_array('force', $args, true);
if (!$id || !is_readable("$dir/baru.html") || !is_readable("$dir/asli.html")) WP_CLI::error('Usage: <ID> <dir-with-asli.html-baru.html> [dry] [force]');
if (!$dry && !current_user_can('unfiltered_html')) WP_CLI::error('Run with --user=nico — nothing changed.');
$p = get_post($id); if (!$p || $p->post_type !== 'post') WP_CLI::error("$id bukan post");
$asli = file_get_contents("$dir/asli.html"); $baru = file_get_contents("$dir/baru.html");
$ver = trim(@file_get_contents('/tmp/humanizer-amon.VERSION') ?: '1.0.0');
if (rtrim($p->post_content) === rtrim($baru)) { WP_CLI::log("$id: sudah versi baru"); return; }
if (rtrim($p->post_content) !== rtrim($asli) && !$force) WP_CLI::error("$id: isi saat ini berbeda dari asli.html (ada suntingan setelah review) — tidak ditulis.");
WP_CLI::log(($dry ? '[dry] ' : '') . "$id {$p->post_name}: " . strlen($p->post_content) . ' → ' . strlen($baru) . ' byte');
if ($dry) return;
if (!metadata_exists('post', $id, '_dti_prev_content_pilot')) update_post_meta($id, '_dti_prev_content_pilot', wp_slash($p->post_content));
$r = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $baru]), true);
if (is_wp_error($r) || !$r) WP_CLI::error("$id: simpan gagal");
update_post_meta($id, 'humanizer', "humanizer-amon@$ver");
update_post_meta($id, '_dti_pilot', current_time('mysql'));
$ok = rtrim(get_post($id)->post_content) === rtrim($baru);
WP_CLI::success("$id diganti (humanizer-amon@$ver)" . ($ok ? '' : ' — PERINGATAN: isi tersimpan berbeda dari baru.html'));
