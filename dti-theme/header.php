<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width,initial-scale=1.0">
	<?php if ( is_singular() && pings_open( get_queried_object() ) ) : ?>
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<?php endif; ?>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<header id="header" class="dti-header">
	<div class="dti-header__inner">
		<a class="dti-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="DTI Blog">
			<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/dti-logo.png' ); ?>" alt="DTI" />
		</a>

		<nav class="dti-nav" aria-label="Main">
			<?php
			if ( has_nav_menu( 'dti_primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'dti_primary',
					'container'      => false,
					'menu_class'     => 'dti-menu',
					'depth'          => 2,
				) );
			} else {
				dti_blog_default_menu();
			}
			?>
		</nav>

		<div class="dti-header__actions" style="display:flex;align-items:center;gap:16px;">
			<a class="dti-header__cta" href="/id/kontak">Hubungi Kami</a>
			<button class="dti-header__toggle" aria-label="Menu" aria-expanded="false"
				onclick="var h=this.closest('.dti-header');var o=h.classList.toggle('is-open');this.setAttribute('aria-expanded',o);">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>
</header>

<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-0ZFWTMD8SL"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-0ZFWTMD8SL');
</script>
