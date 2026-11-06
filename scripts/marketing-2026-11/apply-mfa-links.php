<?php
/** agentic-marketing P6 (5 Nov 2026): one contextual link per post to /id/solusi/mfa-tahan-phishing-bank. Idempotent. [dry] */
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
$url = 'https://dtisolution.id/id/solusi/mfa-tahan-phishing-bank';
$a = fn($t) => '<a href="' . $url . '">' . $t . '</a>';
$plan = [
  5237 => ['after_first_p_matching' => '/bank|perbankan/i',
           'html' => '<p>Untuk bank yang sedang menyiapkan program ini, ringkasan pendekatan DTI ada di halaman ' . $a('MFA tahan phishing untuk perbankan') . '.</p>'],
  5217 => ['before_heading' => 'Batas Klaim',
           'html' => '<p>Contoh penerapan bertahap untuk lingkungan bank dirangkum di halaman ' . $a('solusi MFA tahan phishing untuk bank') . '.</p>'],
  5299 => ['before_heading' => 'Pertanyaan yang sering diajukan',
           'html' => '<p>Gambaran arsitektur dan cakupan implementasinya ada di halaman ' . $a('MFA tahan phishing berbasis FIDO2 untuk bank') . '.</p>'],
  5213 => ['before_heading' => 'Apa yang benar-benar dikatakan regulasi',
           'html' => '<p>Bila perbandingan ini dipakai untuk evaluasi di lingkungan bank, lihat ' . $a('panduan MFA tahan phishing untuk perbankan') . ' dari DTI.</p>'],
];
foreach ($plan as $id => $cfg) {
  $p = get_post($id); if (!$p) { $log("$id missing"); continue; }
  $c = $p->post_content;
  if (strpos($c, '/id/solusi/mfa-tahan-phishing-bank') !== false) { $log("$id already links"); continue; }
  $pos = false;
  if (isset($cfg['before_heading'])) {
    if (preg_match('/<h[23][^>]*>\s*' . preg_quote($cfg['before_heading'], '/') . '/u', $c, $m, PREG_OFFSET_CAPTURE)) $pos = $m[0][1];
  } else {
    if (preg_match_all('/<p[^>]*>.*?<\/p>/su', $c, $ms, PREG_OFFSET_CAPTURE)) {
      foreach ($ms[0] as [$txt, $off]) { if (preg_match($cfg['after_first_p_matching'], wp_strip_all_tags($txt))) { $pos = $off + strlen($txt); break; } }
    }
  }
  if ($pos === false) { WP_CLI::warning("$id: insertion point not found — skipped"); continue; }
  // Strip tags on the whole prefix ($pos is always a tag boundary), then cut by
  // characters — byte substr() could split a UTF-8 char or land inside a tag.
  $ctx = trim(wp_strip_all_tags(substr($c, 0, $pos)));
  $log("$id {$p->post_name}: insert at …" . mb_substr($ctx, -70));
  if (!$dry) wp_update_post(['ID' => $id, 'post_content' => substr($c, 0, $pos) . "\n" . $cfg['html'] . "\n" . substr($c, $pos)]);
}
