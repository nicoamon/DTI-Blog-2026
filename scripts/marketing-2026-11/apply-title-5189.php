<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** agentic-marketing 840cba5603 — title/meta test post 5189 (Nico chose option A, 15 Nov 2026).
 *  Title/meta only: H1, slug and body are NOT touched. Idempotent. Original values backed up once in
 *  _dti_prev_rank_math_title / _dti_prev_rank_math_description. wp eval-file … [dry] | rollback */
$dry = in_array('dry', $args ?? [], true);
$rollback = in_array('rollback', $args ?? [], true);
$id = 5189;
$p = get_post($id);
if (!$p || $p->post_name !== 'pemusnahan-data-perbankan-pojk-padk') WP_CLI::error("$id missing or slug mismatch — wrong DB; nothing changed");
$t = 'Pemusnahan Data Bank: Kewajiban POJK, PADK & Sertifikat %sep% %sitename%'; // keeps " - Blog DTI"
$d = 'Kewajiban bank memusnahkan data menurut POJK 11/2022 & PADK: metode NIST 800-88, langkah audit-ready, dan sertifikat pemusnahan per nomor seri perangkat.';
$ot = get_post_meta($id, 'rank_math_title', true); $od = get_post_meta($id, 'rank_math_description', true);
if ($rollback) {
  if (!metadata_exists('post', $id, '_dti_prev_rank_math_title')) WP_CLI::error('no backup — nothing to roll back');
  if (!$dry) {
    update_post_meta($id, 'rank_math_title', wp_slash(get_post_meta($id, '_dti_prev_rank_math_title', true)));
    update_post_meta($id, 'rank_math_description', wp_slash(get_post_meta($id, '_dti_prev_rank_math_description', true)));
  }
  WP_CLI::success(($dry ? '[dry] ' : '') . "$id rolled back"); return;
}
if ($ot === $t && $od === $d) { WP_CLI::log("$id title/meta already set"); return; }
if (!$dry) {
  if (!metadata_exists('post', $id, '_dti_prev_rank_math_title')) update_post_meta($id, '_dti_prev_rank_math_title', wp_slash($ot));
  if (!metadata_exists('post', $id, '_dti_prev_rank_math_description')) update_post_meta($id, '_dti_prev_rank_math_description', wp_slash($od));
  update_post_meta($id, 'rank_math_title', $t); update_post_meta($id, 'rank_math_description', $d);
}
WP_CLI::success(($dry ? '[dry] ' : '') . "$id title '$ot' -> '$t' | meta " . mb_strlen($d) . ' chars');
