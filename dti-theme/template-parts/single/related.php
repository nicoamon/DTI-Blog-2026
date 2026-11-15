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
ob_start();
include get_template_directory() . '/template-parts/single/related.php';
$orig = ob_get_clean();
$html = preg_replace('~<h2(\s+class="entry-title[^"]*"[^>]*)>(.*?)</h2>~s', '<h3$1>$2</h3>', $orig) ?? $orig;
$html = preg_replace('~(<h4 class="h2">)([^<]*)</h2>~', '$1$2</h4>', $html) ?? $html; // [^<]: never reaches a later h2
echo $html;
