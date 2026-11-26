<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** amon-dti-marketing b61428bcef — lead-magnet CTA box in post 5527 (SIPNAP), same box as 5663/5165
 *  (apply-leadmagnet-cta.php). Nico chose option A via choices on 25 Nov 2026 in amon-dti-marketing;
 *  prod needs Nico's ACC in dti-website. ONE box, inserted right before the H2 that follows
 *  "Titik Rawan yang Membuat Angka Meleset" (both headings are checked). Box title is a <strong>,
 *  so the outline is unchanged. Relative link, no UTM. Idempotent (marker class dti-leadmagnet-cta).
 *  humanizer: humanizer-amon@1.0.0
 *  wp eval-file <this file> --user=<admin> [dry] | rollback [dry] */
$dry = in_array('dry', $args ?? [], true);
$rollback = in_array('rollback', $args ?? [], true);
$unknown = array_diff($args ?? [], ['dry', 'rollback']); if ($unknown) WP_CLI::error('Unknown argument(s): ' . implode(', ', $unknown) . ' — nothing written. Allowed: dry, rollback.');
if (!$dry && !current_user_can('unfiltered_html')) WP_CLI::error('Run with --user=<admin> (needs unfiltered_html). Nothing written.');
$id = 5527; $slug = 'pelaporan-sipnap-software-apotek-klinik';
$after = 'Titik Rawan yang Membuat Angka Meleset';
$before = 'Peran Software Apotek: dari Kartu Stok Elektronik ke Rekap Siap Kirim';
$box = '<div class="dti-leadmagnet-cta" style="margin:28px 0;padding:18px 20px;border-left:4px solid #2C4A7E;background:#f4f6fa;border-radius:6px">'
  . '<strong>' . esc_html('Checklist kepatuhan apotek untuk SIPNAP') . '</strong><br>'
  . esc_html('Panduan ini merinci pelaporan SIPNAP bulanan, kartu stok narkotika dan psikotropika, serta stok opname per area, dengan acuan Permenkes 5/2023, Permenkes 73/2016, dan PP 51/2009.')
  . '<br><a href="' . esc_url('/id/resource/dhealthcare-apotek-sipnap') . '"><strong>' . esc_html('Dapatkan checklist') . '</strong></a></div>';
$marker = '/<div\b[^>]*\bdti-leadmagnet-cta\b[^>]*>.*?<\/div>/s';
$markerRb = '/<div\b[^>]*\bdti-leadmagnet-cta\b[^>]*>.*?<\/div>(?:\n\n)?/s';
$p = get_post($id);
if (!$p || $p->post_name !== $slug) WP_CLI::error("$id missing or slug mismatch — nothing written");
if (!$rollback && ($p->post_type !== 'post' || $p->post_status !== 'publish')) WP_CLI::error("$id is {$p->post_type}/{$p->post_status}, not a published post — nothing written");
$c = $p->post_content;
if ($rollback) {
  if (!preg_match($marker, $c)) { WP_CLI::log("$id: no lead-magnet box — nothing to roll back"); return; }
  $new = preg_replace($markerRb, '', $c, 1);
} else {
  if (preg_match($marker, $c)) { WP_CLI::log("$id: box already present"); return; }
  preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/si', $c, $m, PREG_OFFSET_CAPTURE);
  $texts = array_map(static fn($t) => trim(html_entity_decode(wp_strip_all_tags($t[0]))), $m[1]);
  $i = array_search($before, $texts, true);
  if ($i === false || $i === 0 || $texts[$i - 1] !== $after) WP_CLI::error("$id: expected H2 '$after' followed by '$before' not found — nothing written");
  $pos = $m[0][$i][1];
  $new = substr($c, 0, $pos) . $box . "\n\n" . substr($c, $pos);
}
$hb = preg_match_all('/<h[1-6]\b/i', $c); $ha = preg_match_all('/<h[1-6]\b/i', $new);
if ($hb !== $ha) WP_CLI::error("$id: heading count changed ($hb -> $ha) — nothing written");
if (!$dry) {
  $r = wp_update_post(['ID' => $id, 'post_content' => wp_slash($new)], true);
  if (is_wp_error($r) || !$r) WP_CLI::error("$id: " . (is_wp_error($r) ? $r->get_error_message() : 'update returned 0'));
}
WP_CLI::success(($dry ? '[dry] ' : '') . "$id " . ($rollback ? 'box removed' : "box inserted before H2 '$before'") . " (headings $ha)");
