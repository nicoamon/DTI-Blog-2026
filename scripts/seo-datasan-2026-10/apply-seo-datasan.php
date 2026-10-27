<?php
/**
 * SEO-DATASAN-01 blog part — idempotent. Run: wp eval-file apply-seo-datasan.php [dry]
 * 1) "Baca juga" box → 2–3 relevant website LPs, inserted before the last .dti-product-cta
 * 2) Rewrite internal links pointing at the two merged thin posts
 * 3) Deepen post 5165 (content from 5165-new.html)
 * 4) Merge thin posts 5164 → 5223, 5166 → 5225: set draft + Rank Math 301
 */
$dry  = in_array('dry', $args ?? [], true);
$site = strpos(home_url(), 'dti.amon.id') !== false ? 'https://dti.amon.id' : 'https://dtisolution.id';
$blog = untrailingslashit(home_url()); // https://dtisolution.id/blog or staging equivalent
$lp   = fn($slug) => "$site/id/solusi/$slug";
$cat  = "$site/id/produk/datasan-catalog";

$L = [
  'panduan'  => [$lp('panduan-pemusnahan-data'), 'Panduan lengkap pemusnahan data di Indonesia'],
  'itad'     => [$lp('it-asset-disposal'), 'IT asset disposal: pemusnahan data aset TI'],
  'onsite'   => [$lp('pemusnahan-data-on-site'), 'Jasa pemusnahan data on-site bersertifikat'],
  'ssd'      => [$lp('pemusnahan-ssd'), 'Pemusnahan data SSD sesuai NIST 800-88'],
  'degausser'=> [$lp('degausser-hard-disk'), 'Harga degausser hard disk vs jasa degaussing'],
  'jasadeg'  => [$lp('jasa-degaussing'), 'Jasa degaussing on-site tersertifikasi'],
  'bank'     => [$lp('pemusnahan-data-perbankan'), 'Pemusnahan data perbankan sesuai POJK & UU PDP'],
  'catalog'  => [$cat, 'Katalog mesin degausser & shredder'],
  'rs'       => [$lp('keamanan-data-rumah-sakit'), 'Keamanan dan pemusnahan data rumah sakit'],
];
$MAP = [
  5622 => ['panduan', 'onsite', 'bank'],
  5544 => ['panduan', 'ssd', 'jasadeg'],
  5408 => ['ssd', 'catalog', 'panduan'],
  5311 => ['panduan', 'onsite', 'itad'],
  5309 => ['degausser', 'catalog', 'jasadeg'],
  5305 => ['catalog', 'degausser', 'ssd'],
  5491 => ['onsite', 'panduan', 'itad'],
  5225 => ['onsite', 'panduan', 'itad'],
  5223 => ['panduan', 'ssd', 'catalog'],
  5235 => ['onsite', 'itad', 'panduan'],
  5189 => ['bank', 'onsite', 'panduan'],
  5165 => ['panduan', 'onsite', 'itad'],
  5713 => ['itad', 'panduan', 'onsite'],
  5197 => ['rs', 'onsite', 'panduan'],
  5670 => ['itad', 'onsite', 'panduan'],
];
$MERGE = [ // thin => target
  5164 => 5223,
  5166 => 5225,
];

$box = function (array $keys) use ($L) {
  $li = '';
  foreach ($keys as $k) $li .= '<li><a href="' . esc_url($L[$k][0]) . '">' . esc_html($L[$k][1]) . '</a></li>';
  return '<div class="dti-related-lp" style="margin:28px 0;padding:16px 20px;border:1px solid #dfe4ec;border-radius:6px"><strong>Baca juga di DTI</strong><ul style="margin:8px 0 0 18px">' . $li . '</ul></div>' . "\n";
};
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);

// 3) deepen 5165 first, so step 1 adds its box to the new content
$new5165 = file_get_contents(__DIR__ . '/5165-new.html');
$p = get_post(5165);
if ($p && $new5165 && strpos($p->post_content, 'Pasal 45') === false) {
  $new5165 = str_replace('https://dtisolution.id/blog', $blog, $new5165);
  $log('5165 deepened (' . str_word_count(wp_strip_all_tags($new5165)) . ' words)');
  if (!$dry) wp_update_post(['ID' => 5165, 'post_content' => $new5165]);
}

// 1) related box + 2) relink merged slugs
$slugOf = fn($id) => get_post_field('post_name', $id);
$relink = [];
foreach ($MERGE as $thin => $to) $relink[$slugOf($thin)] = $slugOf($to);

foreach (array_keys($MAP) as $id) {
  $p = get_post($id);
  if (!$p) { $log("$id missing — skip"); continue; }
  $c = $p->post_content; $orig = $c;
  foreach ($relink as $from => $to) $c = preg_replace('#(/blog/)' . preg_quote($from, '#') . '/?#', '${1}' . $to . '/', $c);
  if (strpos($c, 'dti-related-lp') === false) {
    $pos = strrpos($c, '<div class="dti-product-cta"');
    $c = $pos === false ? $c . "\n" . $box($MAP[$id]) : substr($c, 0, $pos) . $box($MAP[$id]) . substr($c, $pos);
  }
  if ($c !== $orig) { $log("$id {$p->post_name} updated"); if (!$dry) wp_update_post(['ID' => $id, 'post_content' => $c]); }
  else $log("$id unchanged");
}

// 4) merge thin posts
foreach ($MERGE as $thin => $to) {
  $p = get_post($thin); if (!$p) continue;
  $src = $p->post_name; $dest = get_permalink($to);
  if (!$dry && $p->post_status === 'publish') wp_update_post(['ID' => $thin, 'post_status' => 'draft']);
  $exists = \RankMath\Redirections\DB::match_redirections_source($src);
  if (!$exists) {
    $log("301 /$src/ → $dest");
    if (!$dry) {
      $r = \RankMath\Redirections\Redirection::from([
        'sources' => [['pattern' => $src, 'comparison' => 'exact']],
        'url_to' => $dest, 'header_code' => 301, 'status' => 'active',
      ]);
      $r->save();
    }
  } else $log("301 for $src already present");
}
