<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** agentic-marketing Night Shift da93a9e941 — lead-magnet CTA box in posts 5663 + 5165
 *  (Nico chose "Kedua artikel", 17 Nov 2026, in agentic-marketing; prod needs Nico's ACC in dti-website).
 *  Inserts ONE box right before the post's 2nd H2 (expected heading text is checked). Box title is a
 *  <strong>, not a heading, so the article outline is unchanged. Existing product CTA untouched.
 *  Internal links are relative and carry no UTM. Idempotent (marker class dti-leadmagnet-cta).
 *  Post 5189 is never touched (title test until 13 Dec).
 *  wp eval-file <this file> --user=<admin> [dry] | rollback [dry] */
$dry = in_array('dry', $args ?? [], true);
$rollback = in_array('rollback', $args ?? [], true);
$unknown = array_diff($args ?? [], ['dry', 'rollback']); if ($unknown) WP_CLI::error('Unknown argument(s): ' . implode(', ', $unknown) . ' — nothing written. Allowed: dry, rollback.');
if (!$dry && !current_user_can('unfiltered_html')) WP_CLI::error('Run with --user=<admin> (needs unfiltered_html): without it wp_update_post re-filters the whole post through kses. Nothing written.');
$box = static function (string $title, string $text, string $href, string $label): string {
  return '<div class="dti-leadmagnet-cta" style="margin:28px 0;padding:18px 20px;border-left:4px solid #2C4A7E;background:#f4f6fa;border-radius:6px">'
    . '<strong>' . esc_html($title) . '</strong><br>' . esc_html($text)
    . '<br><a href="' . esc_url($href) . '"><strong>' . esc_html($label) . '</strong></a></div>';
};
$posts = [
  5663 => ['slug' => 'tanda-tangan-digital-rumah-sakit-rekam-medis',
           'h2' => 'Dokumen mana yang perlu TTE tersertifikasi?',
           'box' => $box('Panduan gratis: Keabsahan Hukum Tanda Tangan Elektronik & Peran PSrE',
             'Ringkasan dasar hukum TTE (UU ITE, PP 71/2019) dan cara memastikan TTE rumah sakit Anda sah di pengadilan. Siap dipakai untuk rapat dengan tim legal dan komite rekam medis.',
             '/id/resource/tilaka-legal-validity', 'Unduh panduannya →')],
  5165 => ['slug' => 'kewajiban-pemusnahan-data-pribadi-uu-pdp',
           'h2' => 'Apa beda penghapusan dan pemusnahan?',
           'box' => $box('Checklist gratis: Kepatuhan Pemusnahan Data — UU PDP & ISO 27001',
             'Daftar periksa yang bisa langsung dipakai tim IT dan kepatuhan sebelum memusnahkan perangkat, termasuk bukti yang perlu disimpan untuk audit.',
             '/id/resource/datasan-disposal-compliance', 'Unduh checklist-nya →')],
];
// Any attribute order (the box may have been touched in the editor); the box holds no nested <div>.
$marker = '/<div\b[^>]*\bdti-leadmagnet-cta\b[^>]*>.*?<\/div>/s';
// Rollback removes exactly what the insert added (box + "\n\n"), so the content returns byte-identical.
$markerRb = '/<div\b[^>]*\bdti-leadmagnet-cta\b[^>]*>.*?<\/div>(?:\n\n)?/s';
foreach ($posts as $id => $cfg) {
  $p = get_post($id);
  if (!$p || $p->post_name !== $cfg['slug']) { WP_CLI::warning("$id missing or slug mismatch — skipped"); continue; }
  if ($p->post_type !== 'post' || $p->post_status !== 'publish') { WP_CLI::warning("$id is {$p->post_type}/{$p->post_status}, not a published post — skipped"); continue; }
  $c = $p->post_content;
  if ($rollback) {
    if (!preg_match($marker, $c)) { WP_CLI::log("$id: no lead-magnet box — nothing to roll back"); continue; }
    $new = preg_replace($markerRb, '', $c, 1);
  } else {
    if (preg_match($marker, $c)) { WP_CLI::log("$id: box already present"); continue; }
    preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/si', $c, $m, PREG_OFFSET_CAPTURE);
    if (count($m[0]) < 2) { WP_CLI::warning("$id: fewer than 2 H2 — skipped"); continue; }
    $h2text = trim(wp_strip_all_tags($m[1][1][0]));
    if ($h2text !== $cfg['h2']) { WP_CLI::warning("$id: 2nd H2 is '$h2text', expected '{$cfg['h2']}' — skipped"); continue; }
    $pos = $m[0][1][1];
    $new = substr($c, 0, $pos) . $cfg['box'] . "\n\n" . substr($c, $pos);
  }
  $headingsBefore = preg_match_all('/<h[1-6]\b/i', $c); $headingsAfter = preg_match_all('/<h[1-6]\b/i', $new);
  if ($headingsBefore !== $headingsAfter) WP_CLI::error("$id: heading count changed ($headingsBefore -> $headingsAfter) — aborted before any write, nothing written");
  $pending[$id] = [$new, $headingsAfter, $cfg['h2']];
}
// Write only after every post passed its checks, so a failed check can never leave a half-applied run.
$written = [];
foreach ($pending ?? [] as $id => [$new, $headings, $h2]) {
  if (!$dry) {
    $r = wp_update_post(['ID' => $id, 'post_content' => wp_slash($new)], true);
    if (is_wp_error($r) || !$r) WP_CLI::error("$id: " . (is_wp_error($r) ? $r->get_error_message() : 'update returned 0') . ' — ' . ($written ? 'already written: ' . implode(', ', $written) : 'nothing written'));
    $written[] = $id;
  }
  WP_CLI::success(($dry ? '[dry] ' : '') . "$id " . ($rollback ? 'box removed' : "box inserted before H2 '$h2'") . " (headings $headings)");
}

