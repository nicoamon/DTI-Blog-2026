<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** agentic-marketing 840cba5603 — title/meta test post 5189 (Nico chose option A, 15 Nov 2026).
 *  Title/meta only: H1, slug and body are NOT touched. Idempotent. Original values backed up once in
 *  _dti_prev_rank_math_title / _dti_prev_rank_math_description. wp eval-file … [dry] | rollback [force] */
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
  // Only undo OUR values: if the team has since edited title/meta in Rank Math, a rollback would
  // silently overwrite their work. Append `force` to roll back anyway.
  if (($ot !== $t || $od !== $d) && !in_array('force', $args ?? [], true)) {
    WP_CLI::error("$id title/meta were changed after this script ran — not rolled back (would overwrite those edits). Current title: '$ot'. Append `force` to roll back anyway.");
  }
  if (!$dry) {
    // An empty backup means Rank Math's default was in use: delete the meta instead of storing ''.
    foreach (['rank_math_title', 'rank_math_description'] as $k) {
      $prev = get_post_meta($id, "_dti_prev_$k", true);
      if ($prev === '') delete_post_meta($id, $k); else update_post_meta($id, $k, wp_slash($prev));
      delete_post_meta($id, "_dti_prev_$k"); // backup consumed — a later run takes a fresh one
    }
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
