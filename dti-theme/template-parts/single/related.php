<?php
/**
 * Child override of Gridlove's "related posts" block — a thin wrapper, NOT a copy:
 * the parent template is rendered unchanged, then only its heading tags are fixed.
 *  1. Related-card titles: <h2 class="entry-title …"> → <h3>. They sat after the article's
 *     own H2 sections and diluted the page outline for search engines (agentic-marketing,
 *     15 Nov 2026, post 5189). Visual size is unchanged — the cards carry class "h3".
 *  2. Parent bug: the "Related" heading opens <h4 class="h2"> but closes with </h2>.
 * Archive/home grids are not affected (they do not use this template).
 */
// Throttled log: a Gridlove change would otherwise write one line on EVERY single-post view
// (container logs are not rotated). One line per hour per message is enough to notice.
$dti_related_log = function ($msg) {
  $k = 'dti_related_log_' . md5($msg);
  if (get_transient($k)) return;
  set_transient($k, 1, HOUR_IN_SECONDS);
  error_log('dti-blog related.php: ' . $msg);
};
$dti_parent_tpl = get_template_directory() . '/template-parts/single/related.php';
if (!is_readable($dti_parent_tpl)) {
  // Parent theme changed or moved the file: render nothing (as before) but leave a trace instead of a PHP warning.
  $dti_related_log('parent template not found at ' . $dti_parent_tpl . ' — related block not rendered');
  return;
}
// No Gridlove parent (this theme active on its own) → the "parent" path is THIS file: including it
// would recurse until PHP dies. Render nothing and log instead.
if (realpath($dti_parent_tpl) === realpath(__FILE__)) {
  $dti_related_log('parent template resolves to this child file (Gridlove not the parent theme?) — related block not rendered');
  return;
}
ob_start();
include $dti_parent_tpl; // prefixed names: this scope is shared with the parent template
$orig = ob_get_clean();
// Attribute order/extra attributes tolerated: any <h2 …> whose class list contains entry-title.
$html = preg_replace('~<h2(\s[^>]*(?<=\s)class="(?:[^"]*\s)?entry-title(?=[\s"])[^"]*"[^>]*)>(.*?)</h2>~s', '<h3$1>$2</h3>', $orig, -1, $n_cards);
$re_err = $html === null ? preg_last_error_msg() : ''; // PCRE limit etc.: not a markup change
$html = $html ?? $orig;
$tmp = preg_replace('~(<h4\s+class="h2">)([^<]*)</h2>~', '$1$2</h4>', $html, -1, $n_head); // [^<]: never reaches a later h2
if ($tmp === null) { $re_err = $re_err !== '' ? $re_err : preg_last_error_msg(); } else { $html = $tmp; }
// Smoke check: if Gridlove changes its markup the fix silently stops working — leave a trace
// when the parent output still has entry-title h2s or a mismatched heading after the fix.
if ($re_err !== '') {
  $dti_related_log('heading fix regex failed (' . $re_err . ') — headings left as Gridlove renders them');
} elseif (preg_match('~<h2\s[^>]*(?<=\s)class="(?:[^"]*\s)?entry-title(?=[\s"])~', $html) || preg_match('~<h4\s+class="h2">[^<]*</h2>~', $html)) {
  $dti_related_log('heading fix did not apply (Gridlove markup changed?) — cards fixed: ' . (int) $n_cards . ', heading fixed: ' . (int) $n_head);
}
echo $html;
