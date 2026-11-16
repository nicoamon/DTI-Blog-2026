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
$parent = get_template_directory() . '/template-parts/single/related.php';
if (!is_readable($parent)) {
  // Parent theme changed or moved the file: render nothing (as before) but leave a trace instead of a PHP warning.
  $dti_related_log('parent template not found at ' . $parent . ' — related block not rendered');
  return;
}
ob_start();
include $parent;
$orig = ob_get_clean();
$html = preg_replace('~<h2(\s+class="entry-title[^"]*"[^>]*)>(.*?)</h2>~s', '<h3$1>$2</h3>', $orig) ?? $orig;
$html = preg_replace('~(<h4 class="h2">)([^<]*)</h2>~', '$1$2</h4>', $html) ?? $html; // [^<]: never reaches a later h2
echo $html;
