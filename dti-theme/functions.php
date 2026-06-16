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
add_filter( 'the_content', 'dti_blog_post_cta' );

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
