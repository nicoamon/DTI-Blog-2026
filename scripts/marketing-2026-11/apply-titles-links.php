<?php
/** agentic-marketing 9434b24cc1 (title/meta test) + 453da9ed4f (internal link). Idempotent. wp eval-file … [dry] */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
// Write mode must run as a user with unfiltered_html, otherwise kses
// (content_save_pre) silently strips embeds/attributes from the WHOLE post:
//   wp eval-file <this file> --user=nico          (dry run: append `dry`)
if (!$dry && !current_user_can('unfiltered_html')) { WP_CLI::error('Run with --user=<admin> (needs unfiltered_html) — refusing to write through kses.'); }
$titles = [
  5023 => ['Tanda Tangan Elektronik Sah? Ini Pasal 11 UU ITE & Syaratnya',
           'TTE sah bila memenuhi syarat Pasal 11 UU ITE. Pahami beda TTE tersertifikasi vs tidak, dasar PP 71/2019, dan cara memverifikasinya.'],
  5233 => ['SIMRS Rumah Sakit 2026: Panduan Modul, BPJS & SatuSehat',
           'Checklist memilih SIMRS: modul inti, bridging BPJS V-Klaim/E-Klaim, RME Permenkes 24/2022, SatuSehat, dan risiko migrasi. Panduan praktis untuk RS.'],
];
foreach ($titles as $id => [$t, $d]) {
  $ot = get_post_meta($id, 'rank_math_title', true); $od = get_post_meta($id, 'rank_math_description', true);
  if ($ot === $t && $od === $d) { $log("$id title/meta already set"); continue; }
  if (!get_post_meta($id, '_dti_prev_rank_math_title', true)) { if (!$dry) { update_post_meta($id, '_dti_prev_rank_math_title', $ot); update_post_meta($id, '_dti_prev_rank_math_description', $od); } }
  $log("$id title: '$ot' -> '$t' (" . mb_strlen($t) . " chars) | meta " . mb_strlen($d) . " chars");
  if (!$dry) { update_post_meta($id, 'rank_math_title', $t); update_post_meta($id, 'rank_math_description', $d); }
}
// 453da9ed4f — internal link in 4805
$p = get_post(4805); $c = $p->post_content;
if (strpos($c, '/id/produk/data-sanitization') !== false) { $log('4805 already links to data-sanitization'); }
elseif (has_blocks($c)) { WP_CLI::warning('4805: block (Gutenberg) content — skipped, insert manually in the editor'); }
else {
  // Insert after the FIRST paragraph that follows the "Pentingnya Penghapusan…" heading.
  // Content may use \r\n line endings, so match with a regex instead of byte offsets.
  if (!preg_match('/Pentingnya Penghapusan Data Karyawan yang Aman<\/h4>\R+([^\r\n]+)/u', $c, $m, PREG_OFFSET_CAPTURE)) { WP_CLI::error('anchor paragraph not found'); }
  $endPara = $m[1][1] + strlen($m[1][0]);
  $ins = "\r\n\r\nMenghapus file atau memformat drive tidak menghilangkan datanya — sisa magnetik dan blok SSD masih bisa dipulihkan. Untuk perangkat yang akan dibuang, dijual, atau dikembalikan ke vendor, organisasi biasanya memakai <strong><a href=\"https://dtisolution.id/id/produk/data-sanitization\">layanan pemusnahan data tersertifikasi</a></strong> — degaussing, penghancuran fisik, atau crypto-erase — lengkap dengan berita acara dan sertifikat pemusnahan sebagai bukti audit.";
  $log('4805 insert after paragraph: ' . substr($m[1][0], 0, 90) . '…');
  // wp_slash: wp_update_post() unslashes its input — without it every backslash in the post is lost.
  if (!$dry) wp_update_post(wp_slash(['ID' => 4805, 'post_content' => substr($c, 0, $endPara) . $ins . substr($c, $endPara)]));
}
