<?php
/**
 * DTI Blog child theme — functions.
 * Restyles the Gridlove-based Docotel blog to match dtisolution.id.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue parent (Gridlove) + DTI child styles and the Inter webfont.
 * Priority 999 so the child stylesheet wins over Gridlove's compiled CSS.
 */
function dti_blog_enqueue_styles() {
	// Inter — same family the corporate site uses.
	wp_enqueue_style(
		'dti-inter',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
		array(),
		null
	);

	// Parent theme stylesheet (Gridlove still owns the post-grid layout).
	wp_enqueue_style(
		'gridlove-parent',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( get_template() )->get( 'Version' )
	);

	// DTI child overrides — load last.
	// Version = file mtime so the ?ver query busts CDN/browser caches
	// automatically on every edit (production serves wp-content immutable for
	// 1 year via Cloudflare/Caddy, so a static version string would go stale).
	$child_css = get_stylesheet_directory() . '/style.css';
	$child_ver = file_exists( $child_css ) ? filemtime( $child_css ) : wp_get_theme()->get( 'Version' );
	wp_enqueue_style(
		'dti-blog',
		get_stylesheet_uri(),
		array( 'gridlove-parent' ),
		$child_ver
	);
}
add_action( 'wp_enqueue_scripts', 'dti_blog_enqueue_styles', 999 );

/**
 * Theme supports + nav menu locations.
 */
function dti_blog_setup() {
	add_theme_support( 'custom-logo' );
	add_theme_support( 'title-tag' );
	register_nav_menus( array(
		'dti_primary' => __( 'DTI Primary Menu (header)', 'dti-blog' ),
	) );
}
add_action( 'after_setup_theme', 'dti_blog_setup' );

/**
 * P2 performance — lazy-load images & prioritise the LCP image.
 * Gridlove ships 47 eager images on the homepage (no native lazy), which is the
 * main cause of the ~11s LCP. We re-enable lazy-loading and keep only the FIRST
 * thumbnail eager with fetchpriority=high so the hero/LCP paints fast.
 */
add_filter( 'wp_lazy_loading_enabled', '__return_true', 99 );

function dti_blog_thumbnail_perf( $html ) {
	static $first = true;
	if ( ! $html ) {
		return $html;
	}
	if ( $first ) {
		$first = false; // hero / LCP candidate — load eagerly at high priority
		if ( strpos( $html, 'fetchpriority' ) === false ) {
			$html = str_replace( '<img ', '<img fetchpriority="high" ', $html );
		}
		$html = str_replace( ' loading="lazy"', '', $html );
		return $html;
	}
	if ( strpos( $html, 'loading=' ) === false ) {
		$html = str_replace( '<img ', '<img loading="lazy" decoding="async" ', $html );
	}
	return $html;
}
add_filter( 'post_thumbnail_html', 'dti_blog_thumbnail_perf', 20 );

/**
 * P2 performance — the Gridlove cover/hero image is the LCP element. Load it
 * eagerly at high priority and mark `skip-lazy` so EWWW does NOT lazy-load it
 * (lazy-loading the LCP image actively delays LCP).
 */
function dti_blog_cover_priority( $attr, $attachment, $size ) {
	$is_cover = ( is_string( $size ) && strpos( $size, 'gridlove-cover' ) !== false )
		|| ( isset( $attr['class'] ) && strpos( $attr['class'], 'gridlove-cover' ) !== false );
	if ( $is_cover ) {
		$attr['fetchpriority'] = 'high';
		$attr['loading']       = 'eager';
		$attr['class']         = trim( ( isset( $attr['class'] ) ? $attr['class'] : '' ) . ' skip-lazy' );
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'dti_blog_cover_priority', 20, 3 );

/**
 * P2 performance — drop Contact Form 7 assets on pages with no form.
 * CF7 enqueues its JS + CSS site-wide; on a blog a form is rare, so this removes
 * render-blocking requests from nearly every page.
 */
function dti_blog_trim_cf7_assets() {
	if ( is_singular() ) {
		$post = get_post();
		if ( $post && has_shortcode( $post->post_content, 'contact-form-7' ) ) {
			return; // page actually has a form — keep assets
		}
	}
	wp_dequeue_script( 'contact-form-7' );
	wp_dequeue_script( 'swv' );
	wp_dequeue_style( 'contact-form-7' );
	wp_dequeue_style( 'contact-form-7-rtl' );
}
add_action( 'wp_enqueue_scripts', 'dti_blog_trim_cf7_assets', 100 );

/**
 * P2 performance — defer render-blocking scripts that don't need to run before
 * paint. jQuery + masonry/imagesloaded are left untouched (the grid layout
 * depends on their synchronous order); we defer the standalone helpers.
 */
function dti_blog_defer_scripts( $tag, $handle ) {
	$defer = array( 'wp-hooks', 'wp-i18n', 'contact-form-7', 'swv', 'google-recaptcha' );
	if ( in_array( $handle, $defer, true ) && strpos( $tag, ' defer' ) === false && strpos( $tag, ' async' ) === false ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'dti_blog_defer_scripts', 10, 2 );

/**
 * P2b performance — drop render-blocking assets inherited from Gridlove that the
 * DTI child theme does not use.
 *
 * `gridlove-fonts` loads Cabin + Lato from Google Fonts, but the child theme sets
 * Inter site-wide (style.css base typography), so those two families never paint
 * — it is a pure render-blocking external request. Removing it leaves one font
 * request (Inter) instead of two.
 */
function dti_blog_trim_frontend_assets() {
	if ( is_admin() ) {
		return;
	}
	wp_dequeue_style( 'gridlove-fonts' );
	wp_deregister_style( 'gridlove-fonts' );
}
add_action( 'wp_enqueue_scripts', 'dti_blog_trim_frontend_assets', 100 );

/**
 * P2b performance — jQuery Migrate is a backward-compat shim for pre-1.9 jQuery
 * APIs. Nothing on this stack needs it, so we strip it from jQuery's deps to save
 * a render-blocking request. (Front-end only — the admin/editor may still rely on
 * it, so we leave it there.)
 */
function dti_blog_remove_jquery_migrate( $scripts ) {
	if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
		return;
	}
	$scripts->registered['jquery']->deps = array_diff(
		$scripts->registered['jquery']->deps,
		array( 'jquery-migrate' )
	);
}
add_action( 'wp_default_scripts', 'dti_blog_remove_jquery_migrate' );

/**
 * P2b performance — the fonts load from Google. WordPress only emits a
 * dns-prefetch hint; upgrade fonts.googleapis.com to a full preconnect (starts
 * the TLS handshake earlier) and add fonts.gstatic.com (where the actual .woff2
 * files live) which core does not hint at all. gstatic needs crossorigin because
 * font files are fetched anonymously; the CSS host does not.
 */
function dti_blog_resource_hints( $hints, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$hints[] = 'https://fonts.googleapis.com';
		$hints[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $hints;
}
add_filter( 'wp_resource_hints', 'dti_blog_resource_hints', 10, 2 );

/**
 * Append a DTI product CTA to the end of single blog posts — turns the blog
 * into a lead funnel back to the product/contact pages on the main site.
 */
function dti_blog_post_cta( $content ) {
	if ( is_singular( 'post' ) && in_the_loop() && is_main_query() && ! post_password_required() ) {
		$cta  = '<aside class="dti-post-cta">';
		$cta .= '<p class="dti-post-cta__kicker">Solusi DTI</p>';
		$cta .= '<h3 class="dti-post-cta__title">Tertarik menerapkan teknologi ini di organisasi Anda?</h3>';
		$cta .= '<p class="dti-post-cta__text">DTI membantu industri teregulasi di Indonesia — tanda tangan digital, keamanan akses, SIMRS, hingga AI &amp; data.</p>';
		$cta .= '<div class="dti-post-cta__actions">';
		$cta .= '<a class="dti-post-cta__btn dti-post-cta__btn--accent" href="/id/kontak">Hubungi Kami</a>';
		$cta .= '<a class="dti-post-cta__btn dti-post-cta__btn--primary" href="/id/produk">Jelajahi Produk</a>';
		$cta .= '</div></aside>';
		$content .= $cta;
	}
	return $content;
}
// Priority 20 so the CTA is appended AFTER wpautop (priority 10) has run on the
// post body — otherwise the raw <aside> markup could be wrapped/mangled.
add_filter( 'the_content', 'dti_blog_post_cta', 20 );

/**
 * 301-redirect old category URLs to their consolidated targets (P3 category
 * merge). Matches the request URI directly so it still fires after the old
 * terms are deleted (which would otherwise 404). Deleted "project" → blog home.
 */
function dti_blog_category_redirects() {
	if ( is_admin() ) {
		return;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( ! preg_match( '#/category/([^/?]+)#', $uri, $m ) ) {
		return;
	}
	$map = array(
		'tips'                    => 'knowledge',
		'trending'                => 'knowledge',
		'uncategorized'           => 'knowledge',
		'artificial-intelligence' => 'ai-data',
		'big-data'                => 'ai-data',
		'iot'                     => 'ai-data',
		'rpa'                     => 'ai-data',
		'security-key'            => 'security',
		'software'                => 'security',
		'hardware'                => 'security',
		'product'                 => 'produk-solusi',
		'financial-technology'    => 'produk-solusi',
		'fds'                     => 'produk-solusi',
		'case-study'              => 'produk-solusi',
		'events'                  => 'perusahaan-berita',
		'office-life'             => 'perusahaan-berita',
		'news-docotel'            => 'perusahaan-berita',
		'company'                 => 'perusahaan-berita',
		'infographic'             => 'knowledge',
		'fsi'                     => 'produk-solusi',
		'graph-database'          => 'ai-data',
		'project'                 => '', // deleted → blog home
	);
	$slug = $m[1];
	if ( ! array_key_exists( $slug, $map ) ) {
		return;
	}
	$target = $map[ $slug ] ? home_url( '/category/' . $map[ $slug ] . '/' ) : home_url( '/' );
	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'dti_blog_category_redirects', 1 );

/**
 * Recommended default header menu, shown when no menu is assigned to the
 * "DTI Primary Menu" location. The site owner can override any time via
 * Appearance → Menus. Links use root-relative paths so they resolve on both
 * the staging host and dtisolution.id.
 */
function dti_blog_default_menu() {
	$home = home_url( '/' ); // blog home (…/blog/)
	?>
	<ul id="dti-primary-menu" class="dti-menu">
		<li><a href="<?php echo esc_url( $home ); ?>">Blog</a></li>
		<li class="dti-has-children menu-item-has-children">
			<a href="<?php echo esc_url( $home . 'category/knowledge/' ); ?>">Topik</a>
			<ul class="dti-submenu sub-menu">
				<?php
				$topics = array(
					array( 'knowledge',                 'Knowledge',                 'Wawasan & artikel mendalam' ),
					array( 'ai-data',                   'AI & Data',                 'AI, machine learning, IoT & analitik' ),
					array( 'digital-signature',         'Digital Signature',         'Tanda tangan elektronik & PSrE' ),
					array( 'health-information-system', 'Health Information System',  'SIMRS & teknologi kesehatan' ),
					array( 'security',                  'Security',                  'Keamanan akses, perangkat & sistem' ),
					array( 'produk-solusi',             'Produk & Solusi',           'Produk & solusi teknologi DTI' ),
					array( 'perusahaan-berita',         'Perusahaan & Berita',       'Kabar, event & budaya DTI' ),
				);
				foreach ( $topics as $t ) :
					printf(
						'<li><a href="%s"><span class="dti-dd-label">%s</span><span class="dti-dd-desc">%s</span></a></li>',
						esc_url( $home . 'category/' . $t[0] . '/' ),
						esc_html( $t[1] ),
						esc_html( $t[2] )
					);
				endforeach;
				?>
			</ul>
		</li>
		<li><a href="/id/produk">Produk</a></li>
		<li><a href="/id/industri">Industri</a></li>
		<li><a href="/id/resource">Resources</a></li>
		<li><a href="/id/tentang">Tentang</a></li>
	</ul>
	<?php
}

/**
 * FIX 2026-09-01 (Claude) — hide "0 views" meta.
 * Owner rule: posts with 0 views should not display the view count at all.
 * entry-views renders `.meta-views` on every card/single; strip it client-side
 * when the count is zero (no clean server-side filter in Gridlove's meta parts).
 */
function dti_blog_hide_zero_views() {
	?>
<script>
(function(){
	function hideZero(){
		document.querySelectorAll('.meta-views').forEach(function(el){
			var t=(el.textContent||'').replace(/\s+/g,' ').trim();
			if(/^0\s+views?$/i.test(t)){ el.style.display='none'; }
		});
	}
	if(document.readyState!=='loading'){hideZero();}
	else{document.addEventListener('DOMContentLoaded',hideZero);}
})();
</script>
	<?php
}
add_action( 'wp_footer', 'dti_blog_hide_zero_views', 99 );

/* ═══ CATEGORY ARCHIVE — 2026-09-01 (Fable spec / Claude impl) ═══ */

/**
 * Strip Gridlove's "Category - " title prefix (translatable string, domain gridlove).
 * Presentation-only; admin unaffected.
 */
function dti_blog_strip_category_prefix( $translated, $text, $domain ) {
	if ( $domain === 'gridlove' && $text === 'Category - ' ) {
		return '';
	}
	// Pagination button: localize + humanize (blog is Indonesian).
	if ( $domain === 'gridlove' && $text === 'Load More' ) {
		return 'Muat lebih banyak';
	}
	return $translated;
}
add_filter( 'gettext', 'dti_blog_strip_category_prefix', 10, 3 );

/**
 * Chip data-rule on category archives: drop the queried category (redundant on its
 * own archive) and cap to exactly one classification. Library discipline.
 */
function dti_blog_archive_chip_rule( $categories ) {
	if ( is_admin() || ! is_category() ) {
		return $categories;
	}
	$current = (int) get_queried_object_id();
	$filtered = array_filter( $categories, function ( $c ) use ( $current ) {
		return (int) $c->term_id !== $current;
	} );
	return array_slice( array_values( $filtered ), 0, 1 );
}
add_filter( 'get_the_categories', 'dti_blog_archive_chip_rule' );

/**
 * Append the article count + the ghost chapter glyph to the category description
 * (renders inside .module-desc). Count uses the real found_posts; glyph = first
 * letter of the category name. aria-hidden on the decorative glyph.
 */
function dti_blog_archive_desc_extras( $desc ) {
	if ( is_admin() || ! is_category() ) {
		return $desc;
	}
	if ( is_feed() || doing_action( 'wp_head' ) ) { return $desc; }
	global $wp_query;
	$count  = number_format_i18n( (int) $wp_query->found_posts );
	$letter = function_exists( 'mb_substr' )
		? mb_substr( single_cat_title( '', false ), 0, 1 )
		: substr( single_cat_title( '', false ), 0, 1 );
	$out  = $desc;
	$out .= '<p class="dti-archive-count">' . $count . ' artikel</p>';
	$out .= '<span class="dti-archive-glyph" aria-hidden="true">' . esc_html( $letter ) . '</span>';
	return $out;
}
add_filter( 'category_description', 'dti_blog_archive_desc_extras' );

/**
 * Category archive: move each card's category chip out of the image overlay and
 * into the content block (above the title), per the DTI archive design spec.
 * Progressive enhancement — no-JS users still see the chip as an image badge.
 */
function dti_blog_relocate_archive_chips() {
	if ( ! is_category() ) {
		return;
	}
	?>
<script>
(function(){
	function move(){
		document.querySelectorAll('.archive.category article.gridlove-post').forEach(function(card){
			var img  = card.querySelector('.entry-image');
			var cat  = img && img.querySelector('.entry-category');
			var body = card.querySelector('.box-inner-p');
			if(cat && body && cat.parentNode !== body){ body.insertBefore(cat, body.firstChild); }
		});
	}
	if(document.readyState!=='loading'){move();}
	else{document.addEventListener('DOMContentLoaded',move);}
})();
</script>
	<?php
}
add_action( 'wp_footer', 'dti_blog_relocate_archive_chips', 98 );

/**
 * Image lightbox — click any in-article image to enlarge/zoom (owner req
 * 2026-09-15). Vanilla JS, zero dependency. Opens the LARGEST available source
 * (handles srcset / <picture> / lazy-load data-srcset). Single posts only.
 * Respects existing links, skips tiny/icon images, Esc + click to close.
 */
function dti_blog_image_lightbox() {
	if ( ! is_single() ) return;
	?>
<style id="dti-lb-css">
.entry-content img{cursor:zoom-in}
#dti-lb{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;background:rgba(10,18,32,.93);padding:24px;opacity:0;transition:opacity .2s ease}
#dti-lb.open{display:flex;opacity:1}
#dti-lb img{max-width:94vw;max-height:92vh;width:auto;height:auto;border-radius:6px;box-shadow:0 12px 60px rgba(0,0,0,.55);cursor:zoom-out;object-fit:contain}
#dti-lb .dti-lb-close{position:absolute;top:16px;right:20px;width:42px;height:42px;border:0;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;font-size:26px;line-height:1;cursor:pointer;transition:background .15s}
#dti-lb .dti-lb-close:hover{background:rgba(255,255,255,.26)}
#dti-lb .dti-lb-cap{position:absolute;left:0;right:0;bottom:14px;text-align:center;color:#cdd6e4;font-size:13px;padding:0 24px;pointer-events:none}
body.dti-lb-lock{overflow:hidden}
@media(prefers-reduced-motion:reduce){#dti-lb{transition:none}}
</style>
<script>
(function(){
	var content=document.querySelector('.entry-content'); if(!content) return;
	var lb=document.createElement('div'); lb.id='dti-lb';
	lb.setAttribute('role','dialog'); lb.setAttribute('aria-modal','true'); lb.setAttribute('aria-label','Perbesar gambar');
	lb.innerHTML='<button class="dti-lb-close" type="button" aria-label="Tutup">&times;</button><img alt=""><div class="dti-lb-cap"></div>';
	document.body.appendChild(lb);
	var lbImg=lb.querySelector('img'), lbCap=lb.querySelector('.dti-lb-cap'), lbClose=lb.querySelector('.dti-lb-close'), lastFocus=null;
	function best(img){
		var b=null,bw=-1;
		function consider(u,w){ if(u && u.indexOf('data:')!==0 && w>=bw){ b=u; bw=w; } }
		function fromSet(ss){ if(!ss) return; ss.split(',').forEach(function(p){ var m=p.trim().match(/(\S+)\s+(\d+)w/); if(m){ consider(m[1],parseInt(m[2],10)); } else { var u=p.trim().split(/\s+/)[0]; if(u){ consider(u,0); } } }); }
		fromSet(img.getAttribute('srcset')); fromSet(img.getAttribute('data-srcset'));
		var pic=img.closest?img.closest('picture'):null; if(pic){ pic.querySelectorAll('source').forEach(function(s){ fromSet(s.getAttribute('srcset')); fromSet(s.getAttribute('data-srcset')); }); }
		consider(img.currentSrc,0); consider(img.getAttribute('src'),0); consider(img.getAttribute('data-src'),0);
		return b;
	}
	function open(img){
		var url=best(img); if(!url) return;
		lastFocus=document.activeElement;
		lbImg.setAttribute('src',url); lbImg.setAttribute('alt', img.getAttribute('alt')||'');
		lbCap.textContent=img.getAttribute('alt')||img.getAttribute('title')||'';
		lb.classList.add('open'); document.body.classList.add('dti-lb-lock'); lbClose.focus();
	}
	function close(){ lb.classList.remove('open'); document.body.classList.remove('dti-lb-lock'); lbImg.setAttribute('src',''); if(lastFocus&&lastFocus.focus){ lastFocus.focus(); } }
	content.addEventListener('click',function(e){
		var img=e.target.closest('img'); if(!img||!content.contains(img)) return;
		if(img.closest('a')) return;
		if((img.width||0)<80||(img.height||0)<60) return;
		e.preventDefault(); open(img);
	});
	lb.addEventListener('click',function(e){ if(e.target===lb||e.target===lbImg||e.target===lbClose){ close(); } });
	document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&lb.classList.contains('open')){ close(); } });
})();
</script>
	<?php
}
add_action( 'wp_footer', 'dti_blog_image_lightbox', 97 );
