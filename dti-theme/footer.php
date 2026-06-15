<?php
/**
 * DTI footer — mirrors components/blocks/Footer.tsx from dti-website.
 * Static values match the locked FALLBACK + id.json dictionary.
 */
$dti_year = date( 'Y' );
?>

<footer class="dti-footer">
	<div class="dti-footer__inner">
		<div class="dti-footer__grid">

			<!-- Brand -->
			<div class="dti-footer__brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="PT Docotel Teknologi Informasi">
					<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/dti-logo-white.png' ); ?>" alt="DTI" />
				</a>
				<p class="dti-footer__tagline">Upgrade You — Technology that earns trust.</p>
				<div class="dti-footer__social">
					<a href="https://www.linkedin.com/company/dtinformasi/" target="_blank" rel="noopener" aria-label="LinkedIn">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
					</a>
					<a href="https://www.instagram.com/dti.solution/" target="_blank" rel="noopener" aria-label="Instagram">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
					</a>
					<a href="https://wa.me/+6285810688431?text=Saya%20Tertarik%20Akan%20Product%20DTI%20" target="_blank" rel="noopener" aria-label="WhatsApp">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
					</a>
				</div>
			</div>

			<!-- Industri -->
			<div>
				<p class="dti-footer__col-title">Industri</p>
				<div class="dti-footer__links">
					<a href="/id/industri/banking-fsi">Perbankan &amp; FSI</a>
					<a href="/id/industri/healthcare">Kesehatan</a>
					<a href="/id/industri/government">Pemerintahan &amp; BUMN</a>
					<a href="/id/industri/enterprise">Enterprise</a>
					<a href="/id/industri/plantation">Perkebunan &amp; Agribisnis</a>
				</div>
			</div>

			<!-- Produk -->
			<div>
				<p class="dti-footer__col-title">Produk</p>
				<div class="dti-footer__links">
					<a href="/id/produk/tilaka-digital-signature">Tilaka Tanda Tangan Elektronik</a>
					<a href="/id/produk/yubikey-security-key">YubiKey Security Key</a>
					<a href="/id/produk/dhealth-simrs">DHealth SIMRS</a>
					<a href="/id/produk/data-sanitization">Data Sanitization</a>
					<a href="/id/produk/pikk-reporting">PIKK Reporting</a>
					<a href="/id/produk/geoai">GEOAI</a>
				</div>
			</div>

			<!-- Perusahaan -->
			<div>
				<p class="dti-footer__col-title">Perusahaan</p>
				<div class="dti-footer__links">
					<a href="/id/tentang">Tentang</a>
					<a href="/id/resource">Resources</a>
					<a href="/id/kontak">Hubungi Kami</a>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Blog</a>
				</div>
			</div>

		</div>

		<hr class="dti-footer__divider" />
		<div class="dti-footer__bottom">
			<p>&copy; <?php echo esc_html( $dti_year ); ?> PT Docotel Teknologi Informasi (DTI)</p>
			<p class="dti-footer__legal">
				<a href="/id/legal/kebijakan-privasi">Kebijakan Privasi</a>
				<span class="sep" aria-hidden="true">&middot;</span>
				<a href="/id/legal/syarat-ketentuan">Syarat Layanan</a>
			</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
