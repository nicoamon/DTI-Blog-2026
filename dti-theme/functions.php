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
	wp_enqueue_style(
		'dti-blog',
		get_stylesheet_uri(),
		array( 'gridlove-parent' ),
		wp_get_theme()->get( 'Version' )
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
					array( 'knowledge',                 'Knowledge',                'Wawasan & artikel mendalam' ),
					array( 'artificial-intelligence',   'Artificial Intelligence',  'AI, machine learning & otomasi' ),
					array( 'digital-signature',         'Digital Signature',        'Tanda tangan elektronik & PSrE' ),
					array( 'health-information-system', 'Health Information System', 'SIMRS & teknologi kesehatan' ),
					array( 'security-key',              'Security Key',             'Keamanan akses & autentikasi' ),
					array( 'iot',                       'IoT',                      'Internet of Things & perangkat' ),
					array( 'big-data',                  'Big Data',                 'Analitik & pengolahan data' ),
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
