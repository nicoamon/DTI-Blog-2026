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
ob_start();
include get_template_directory() . '/template-parts/single/related.php';
$orig = ob_get_clean();
// Attribute order/extra attributes tolerated: any <h2 …> whose class list contains entry-title.
$html = preg_replace('~<h2(\s[^>]*\bclass="(?:[^"]*\s)?entry-title(?=[\s"])[^"]*"[^>]*)>(.*?)</h2>~s', '<h3$1>$2</h3>', $orig, -1, $n_cards) ?? $orig;
$html = preg_replace('~(<h4\s+class="h2">)([^<]*)</h2>~', '$1$2</h4>', $html, -1, $n_head) ?? $html; // [^<]: never reaches a later h2
// Smoke check: if Gridlove changes its markup the fix silently stops working — leave a trace
// when the parent output still has entry-title h2s or a mismatched heading after the fix.
if (preg_match('~<h2\s[^>]*\bclass="(?:[^"]*\s)?entry-title(?=[\s"])~', $html) || preg_match('~<h4\s+class="h2">[^<]*</h2>~', $html) || ($orig !== '' && strpos($orig, 'entry-title') !== false && !$n_cards)) {
  $dti_related_log('heading fix did not apply (Gridlove markup changed?) — cards fixed: ' . (int) $n_cards . ', heading fixed: ' . (int) $n_head);
}
echo $html;
