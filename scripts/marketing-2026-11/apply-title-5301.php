<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** amon-dti-marketing 7422669e16 — title/meta test post 5301 (Nico chose option A, 27 Nov 2026, in amon-dti-marketing).
 *  Draft: /home/nico/dti-growth-pagi-7422/content/drafts/7422669e16-title-meta-tte-vs-basah-5301.md (humanizer-amon@1.0.0).
 *  Title/meta only: H1, slug and body are NOT touched. Idempotent. Original values backed up once in
 *  _dti_prev_rank_math_title / _dti_prev_rank_math_description. wp eval-file … [dry] | rollback [force] */
$dry = in_array('dry', $args ?? [], true);
$rollback = in_array('rollback', $args ?? [], true);
$id = 5301;
$p = get_post($id);
if (!$p || $p->post_name !== 'tanda-tangan-elektronik-vs-tanda-tangan-basah') WP_CLI::error("$id missing or slug mismatch — wrong DB; nothing changed");
$t = 'Tanda Tangan Elektronik vs Basah: Keabsahan dan Pembuktian'; // same form as the current value (no %sep% suffix)
$d = 'Tanda tangan elektronik sah menurut UU ITE dan PP 71/2019, tetapi kekuatan pembuktiannya bergantung pada jenisnya. Kapan perlu TTE tersertifikasi PSrE?';
$ot = get_post_meta($id, 'rank_math_title', true); $od = get_post_meta($id, 'rank_math_description', true);
if ($rollback) {
  // At least one backup must exist; the loop below restores each key only from its own backup.
  if (!metadata_exists('post', $id, '_dti_prev_rank_math_title') && !metadata_exists('post', $id, '_dti_prev_rank_math_description')) WP_CLI::error('no backup — nothing to roll back');
  // Only undo OUR values: if the team has since edited title/meta in Rank Math, a rollback would
  // silently overwrite their work. Append `force` to roll back anyway.
  if (($ot !== $t || $od !== $d) && !in_array('force', $args ?? [], true)) {
    WP_CLI::error("$id title/meta were changed after this script ran — not rolled back (would overwrite those edits). Current title: '$ot'. Append `force` to roll back anyway.");
  }
  $skipped = [];
  // An empty backup means Rank Math's default was in use: delete the meta instead of storing ''.
  foreach (['rank_math_title', 'rank_math_description'] as $k) {
    // No backup for this key (partial earlier run, manual cleanup…): get_post_meta() would return ''
    // and the live value would be DELETED. Leave it untouched instead.
    if (!metadata_exists('post', $id, "_dti_prev_$k")) { WP_CLI::warning("$id: no backup for $k — left as is"); $skipped[] = $k; continue; }
    if ($dry) continue; // the backup check above also runs in dry mode, so a dry run predicts a partial rollback
    $prev = get_post_meta($id, "_dti_prev_$k", true);
    if ($prev === '') delete_post_meta($id, $k); else update_post_meta($id, $k, wp_slash($prev));
    delete_post_meta($id, "_dti_prev_$k"); // backup consumed — a later run takes a fresh one
  }
  if ($skipped) { WP_CLI::warning(($dry ? '[dry] ' : '') . "$id: rollback PARSIAL — tidak dipulihkan: " . implode(', ', $skipped)); return; }
  WP_CLI::success(($dry ? '[dry] ' : '') . "$id rolled back"); return;
}
if ($ot === $t && $od === $d) { WP_CLI::log("$id title/meta already set"); return; }
if (!$dry) {
  if (!metadata_exists('post', $id, '_dti_prev_rank_math_title')) update_post_meta($id, '_dti_prev_rank_math_title', wp_slash($ot));
  if (!metadata_exists('post', $id, '_dti_prev_rank_math_description')) update_post_meta($id, '_dti_prev_rank_math_description', wp_slash($od));
  update_post_meta($id, 'rank_math_title', $t); update_post_meta($id, 'rank_math_description', $d);
}
WP_CLI::success(($dry ? '[dry] ' : '') . "$id title '$ot' -> '$t' | meta " . mb_strlen($d) . ' chars');
