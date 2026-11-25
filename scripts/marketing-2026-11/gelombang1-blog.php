<?php
defined('WP_CLI') && WP_CLI || exit; // WP-CLI only
/** Audit konten blog 21 Nov 2026 — gelombang 1 (pembersihan, TANPA mengubah klaim). ACC Nico 22 Nov ("Ya, lewat staging").
 *  wp eval-file gelombang1-blog.php [dry] --user=nico
 *  A) hapus catatan draf/placeholder yang tayang + perbaiki tautan rusak/CTA (4960 4989 5009 5023 5085 5329)
 *  B) post demo tema Gridlove (13) → draft + 301 ke /blog/
 *  C) judul dobel (11 pasang) → yang views-nya lebih kecil jadi draft + 301 ke kembarannya
 *  Idempoten. Backup isi lama di meta _dti_prev_content_w1; penanda _dti_w1 di setiap post yang disentuh.
 *  Rollback: post_content ← _dti_prev_content_w1; post_status ← publish; hapus redirect di _dti_w1_redirect_id (dibuat skrip ini).
 *  Redirect yang sudah ada sebelumnya (tujuan sama) dipakai ulang, dicatat di _dti_w1_redirect_reused dan TIDAK dihapus
 *  skrip; saat rollback nonaktifkan manual bila perlu. Redirect lama ke tujuan lain → post dilewati dengan peringatan.
 *  Unpublish gagal → redirect yang baru dibuat dihapus lagi, sehingga post yang masih tayang tidak ter-301. */
$unknown = array_diff($args ?? [], ['dry']); if ($unknown) WP_CLI::error('Argumen tidak dikenal: ' . implode(', ', $unknown) . ' — tidak ada yang ditulis.');
$dry = in_array('dry', $args ?? [], true);
$log = fn($m) => WP_CLI::log(($dry ? '[dry] ' : '') . $m);
if (!$dry && !current_user_can('unfiltered_html')) WP_CLI::error('Run with --user=nico (needs unfiltered_html) — nothing changed.');
$K = '/id/kontak?product=';
// ---------- A) content fixes: [post => [[old, new, expected_count], ...]] ----------
$fix = [
 4960 => [
  ["<strong>[IMAGE: DIAGRAM ARSITEKTUR]</strong>", '', 1],
  ['A: Brief menyebut onboarding kurang dari 5 menit, tetapi angka ini perlu diverifikasi oleh Solution Architect DTI sebelum dipublikasikan. Untuk enterprise, durasi implementasi total juga bergantung pada integrasi, approval internal, dan kompleksitas workflow.',
   'A: Durasi onboarding dan implementasi bergantung pada integrasi, approval internal, dan kompleksitas workflow di organisasi Anda.', 1],
 ],
 4989 => [
  ['~<p>Untuk ketersediaan lokal, bundling, lead time, dan pemilihan model yang benar-benar bisa dipasok melalui Docotel, gunakan catatan internal berikut sebelum publish:</p>\s*<blockquote>\s*<p><em>Sebagai authorized distributor Yubico di Indonesia,.{0,400}?</blockquote>~s',
   '<p>Untuk ketersediaan model, bundling, dan lead time, konfirmasikan langsung dengan tim Docotel.</p>', 1],
  ['/blog/fido2-webauthn-u2f-enterprise/', '/blog/fido2-webauthn-cara-kerja-lebih-aman-dari-otp-sms/', 1],
  ['/contact/request-demo/', $K . 'yubikey', 2],
 ],
 5009 => [
  [' sebagaimana tercatat dalam knowledge base Docotel.', '.', 1],
  ['Berdasarkan knowledge base Docotel, Tilaka adalah', 'Tilaka adalah', 2],
  ['Placeholder — ganti dengan: diagram hierarki CA (Root CA pemerintah → PSrE Induk → PSrE Indonesia → sertifikat pengguna → dokumen → verifier). Draft brand di assets/ca_hierarchy_w03.svg.',
   'Hierarki sertifikat elektronik: Root CA pemerintah, PSrE Induk, PSrE, sertifikat pengguna, dokumen, dan verifier.', 1],
  ['href="/resources/whitepaper-psre-checklist/">Download Whitepaper PSrE Checklist</a>', 'href="' . $K . 'tilaka">Diskusikan Kebutuhan PSrE</a>', 2],
  ['/blog/tanda-tangan-digital-uu-ite/', '/blog/uu-ite-tanda-tangan-digital/', 1],
  ['/contact/request-demo/', $K . 'tilaka', 1],
 ],
 5023 => [
  ['menghubungkan kebijakan ini dengan <a href="/guides/compliance-digital-indonesia/">panduan compliance digital Indonesia</a> dan artikel cluster tentang', 'menghubungkan kebijakan ini dengan artikel cluster tentang', 1],
  ['/contact/request-demo/', $K . 'tilaka', 3],
 ],
 5085 => [
  ['<a href="/tilaka">Tilaka</a>', '<a href="/id/produk/tilaka-digital-signature">Tilaka</a>', 1],
  ['/contact/request-demo/', $K . 'tilaka', 1],
  ['href="https://dtisolution.id/en/contact">Request Demo</a>', 'href="' . $K . 'tilaka">Request Demo</a>', 2],
 ],
 5329 => [
  ['Anda juga bisa melihat halaman solusi Docotel untuk <a href="/industries/insurance">industri asuransi Docotel</a> dan pendekatan', 'Anda juga bisa melihat pendekatan', 1],
  ['<a href="/tilaka/">halaman produk Tilaka</a>', '<a href="/id/produk/tilaka-digital-signature">halaman produk Tilaka</a>', 1],
  ['<br /><strong>Ditinjau oleh</strong>: <mark>[PLACEHOLDER: role reviewer Docotel]</mark>', '', 1],
  ['/contact/request-demo/', $K . 'tilaka', 2],
 ],
];
$fixed = 0;
foreach ($fix as $id => $reps) {
  $p = get_post($id); if (!$p) { WP_CLI::warning("$id: tidak ada — dilewati"); continue; }
  $c = $p->post_content; $orig = $c; $ok = true;
  foreach ($reps as [$old, $new, $n]) {
    $rx = $old[0] === '~'; // regex entry (bounded pattern) for blocks whose whitespace differs
    $have = $rx ? preg_match_all($old, $c) : substr_count($c, $old);
    if ($have === 0 && ($rx || $new === '' || substr_count($c, $new) > 0)) { $log("$id: sudah beres — " . mb_substr(strip_tags($old), 0, 50)); continue; }
    if ($have !== $n) { WP_CLI::warning("$id: diharapkan $n kemunculan, ada $have — " . mb_substr(strip_tags($old), 0, 60) . " (post TIDAK diubah)"); $ok = false; break; }
    $c = $rx ? preg_replace($old, $new, $c) : str_replace($old, $new, $c);
    if ($c === null) { WP_CLI::warning("$id: regex gagal (" . preg_last_error_msg() . ") — post TIDAK diubah"); $ok = false; break; }
  }
  if (!$ok || $c === $orig) { if ($ok) $log("$id: tidak ada perubahan"); continue; }
  $log("$id: " . count($reps) . ' perbaikan, ' . (strlen($orig) - strlen($c)) . ' byte lebih pendek');
  if ($dry) continue;
  if (!metadata_exists('post', $id, '_dti_prev_content_w1')) update_post_meta($id, '_dti_prev_content_w1', wp_slash($orig));
  $r = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $c]), true);
  if (is_wp_error($r) || !$r) { WP_CLI::warning("$id: simpan GAGAL"); continue; }
  update_post_meta($id, '_dti_w1', 'isi'); $fixed++;
}
// ---------- B/C) draft + 301 ----------
$home = trailingslashit(home_url());
$demo = [153, 155, 159, 164, 168, 180, 187, 366, 368, 1388, 1411, 1417, 1423];
$dupe = [70 => 906, 807 => 960, 986 => 833, 836 => 993, 1180 => 843, 1178 => 839, 1182 => 846, 848 => 1184, 1186 => 850, 866 => 1188, 1190 => 868];
$targets = array_fill_keys($demo, null) + $dupe;
$rm = class_exists('\\RankMath\\Redirections\\Redirection') && class_exists('\\RankMath\\Redirections\\DB');
if (!$rm && !$dry) WP_CLI::error('Rank Math Redirections tidak tersedia — bagian B/C dihentikan.');
$exists = function ($src) {
  foreach ((array) (\RankMath\Redirections\DB::get_redirections(['search' => $src, 'status' => 'active', 'limit' => 200])['redirections'] ?? []) as $row)
    foreach ((array) maybe_unserialize($row['sources'] ?? []) as $one) if (($one['pattern'] ?? '') === $src) return $row;
  return null;
};
// Redirect lama dipakai ulang hanya bila tujuannya sama (URL relatif dibaca terhadap host situs; skema & garis miring akhir diabaikan).
$sameDest = function ($a, $b) {
  $host = preg_replace('#^(https?://[^/]+).*$#i', '$1', home_url());
  $n = fn($u) => untrailingslashit(strtolower(preg_replace('#^https?://#i', '', preg_match('#^https?://#i', (string) $u) ? (string) $u : $host . '/' . ltrim((string) $u, '/'))));
  return $n($a) === $n($b);
};
$moved = 0;
$alarm = []; // posts left published while a 301 of ours is still active
foreach ($targets as $id => $to) {
  $p = get_post($id);
  if (!$p || $p->post_type !== 'post') { WP_CLI::warning("$id: tidak ada — dilewati"); continue; }
  if ($p->post_status !== 'publish') { $log("$id: sudah {$p->post_status} — dilewati"); continue; }
  if ($to !== null) { $t = get_post($to); if (!$t || $t->post_status !== 'publish') { WP_CLI::warning("$id: kembaran $to tidak tayang — dilewati"); continue; } }
  $src = $p->post_name; $dest = $to === null ? $home : get_permalink($to);
  $log("$id {$src} → draft + 301 ke $dest");
  if ($dry || !$rm) continue;
  $row = $exists($src); $created = false;
  if ($row && (int) $row['id'] === (int) get_post_meta($id, '_dti_w1_redirect_id', true)) {
    $rid = (int) $row['id']; $created = true; // buatan run sebelumnya yang berhenti sebelum unpublish
  } elseif ($row) {
    $rid = (int) $row['id'];
    if (!$sameDest($row['url_to'] ?? '', $dest)) { WP_CLI::warning("$id: redirect lama #$rid untuk $src menunjuk ke " . ($row['url_to'] ?? '?') . ", bukan $dest — dilewati, post tetap tayang; periksa manual"); continue; }
    update_post_meta($id, '_dti_w1_redirect_reused', $rid);
  } else {
    $rid = (int) \RankMath\Redirections\Redirection::from(['sources' => [['pattern' => $src, 'comparison' => 'exact']], 'url_to' => $dest, 'header_code' => 301, 'status' => 'active'])->save();
    if ($rid <= 0) { WP_CLI::warning("$id: redirect gagal — post tetap tayang"); continue; }
    $created = true;
    update_post_meta($id, '_dti_w1_redirect_id', $rid);
  }
  $r = wp_update_post(['ID' => $id, 'post_status' => 'draft'], true);
  if (is_wp_error($r) || !$r) {
    if ($created && !\RankMath\Redirections\DB::delete([$rid])) { $alarm[] = $id; WP_CLI::warning("$id: unpublish gagal DAN redirect #$rid GAGAL dihapus — post TAYANG tetapi ter-301. Hapus redirect #$rid manual; meta _dti_w1_redirect_id dipertahankan"); }
    elseif ($created) { delete_post_meta($id, '_dti_w1_redirect_id'); WP_CLI::warning("$id: unpublish gagal — redirect yang baru dibuat dihapus lagi, post tetap tayang"); }
    else { delete_post_meta($id, '_dti_w1_redirect_reused'); WP_CLI::warning("$id: unpublish gagal — post tetap tayang; redirect lama #$rid (bukan buatan skrip ini) dibiarkan, periksa manual"); }
    continue;
  }
  update_post_meta($id, '_dti_w1', $to === null ? 'demo' : "dobel→$to"); $moved++;
}
$log("selesai: isi diperbaiki $fixed post · draft+301 $moved post");
if ($alarm) WP_CLI::error('Redirect gagal dihapus pada post tayang: ' . implode(', ', $alarm) . ' — periksa manual.');
