<!-- Testing -->

<?php
// Memulai session PHP agar bisa membaca data login user ($_SESSION)
session_start();

// Mengambil koneksi database (PDO) dari file db.php
$pdo = require __DIR__ . '/db.php';

// Judul halaman, dipakai di tag <title>
$title = 'Home';
  
// Ambil data user yang sedang login (jika ada). Jika belum login, nilainya null.
$user = $_SESSION['user'] ?? null;

// ===== Ambil data destinasi wisata =====
// Hanya destinasi yang aktif (is_active = TRUE), diurutkan dari yang terbaru
$stmt = $pdo->query('SELECT * FROM destinations WHERE is_active = TRUE ORDER BY created_at DESC');
$destinations = $stmt->fetchAll();

// ===== Ambil jenis tiket untuk setiap destinasi =====
// &$dest artinya kita mengubah langsung isi array $destinations (reference)
foreach ($destinations as &$dest) {
    $ttStmt = $pdo->prepare('SELECT * FROM ticket_types WHERE destination_id = ? ORDER BY (name LIKE \'%masuk%\') DESC, id ASC');
    $ttStmt->execute([$dest['id']]);
    $dest['ticket_types'] = $ttStmt->fetchAll();
}
unset($dest); // Wajib di-unset setelah foreach by-reference, untuk mencegah bug data ke-overwrite di loop lain

// ===== Hitung statistik ulasan & rating GLOBAL (dinamis dari data asli) =====
// Sebelumnya angka "1.9K+" ulasan & "4.6" rating di Hero di-hardcode (tidak
// berubah walau data berubah) -> menyesatkan. Sekarang dihitung dari kolom
// `rating` & `reviews` tiap destinasi: rating dipakai sebagai rata-rata
// TERBOBOT oleh jumlah ulasan agar adil antar destinasi.
$totalReviews = 0;
$ratingSum = 0;     // akumulator (rating * reviews) untuk rata-rata terbobot
foreach ($destinations as $d) {
    $rv = (int) ($d['reviews'] ?? 0);
    if ($rv > 0) {
        $totalReviews += $rv;
        $ratingSum += ((float) $d['rating']) * $rv;
    }
}
// Rata-rata rating terbobot, dibulatkan 1 desimal; 0 bila belum ada ulasan
$avgRating = $totalReviews > 0 ? round($ratingSum / $totalReviews, 1) : 0;

// Format angka ulasan: >=1000 -> "1.9K+" supaya ringkas, selain itu "n+"
$reviewsLabel = $totalReviews >= 1000
    ? rtrim(rtrim(number_format($totalReviews / 1000, 1), '0'), '.') . 'K+'
    : $totalReviews . '+';
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($title) ?> - TiketPantai</title>

  <!-- Tailwind CSS versi browser (langsung compile di client, cocok untuk prototipe) -->
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

  <!-- Font Awesome: dipakai untuk SEMUA icon di halaman ini -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!--
    DIHAPUS: Bootstrap Icons (bootstrap-icons@1.11.1)
    Alasan: tidak ada satupun class "bi-" yang dipakai di seluruh halaman ini,
    semua icon sudah pakai Font Awesome (fa-solid / fa-brands).
    Load library yang tidak dipakai hanya menambah waktu loading halaman.
  -->

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="assets/app.css?v=7">
  <style>
  body {
    font-family: 'Inter', sans-serif;
  }

  h1,
  h2,
  h3,
  .font-display {
    font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
  }
  </style>
</head>

<body class="tp-page-bg font-sans antialiased min-h-screen flex flex-col">

  <!-- ===================== NAVBAR ===================== -->
  <nav class="tp-nav tp-navbar sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
      <div class="flex items-center justify-between h-16">

        <!-- Logo / brand, klik untuk balik ke beranda -->
        <a href="index.php" class="flex items-center gap-2 hover:opacity-80 transition">
          <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center">
            <i class="fa-solid fa-water text-white text-base"></i>
          </div>
          <span class="text-xl font-bold text-white">tiket<span class="text-cyan-100">Pantai</span></span>
        </a>

        <!-- Menu navigasi desktop (disembunyikan di mobile via 'hidden md:flex') -->
        <div class="hidden md:flex items-center gap-1">
          <a href="index.php" class="tp-nav-link tp-nav-link--active px-3 py-1.5 text-sm font-medium">Beranda</a>
          <?php if ($user): ?>
          <a href="orders.php" class="tp-nav-link px-3 py-1.5 text-sm font-medium">Pesanan Saya</a>
          <?php if ($user['role'] === 'admin'): ?>
          <a href="admin/index.php" class="tp-nav-link px-3 py-1.5 text-sm font-medium"><i
              class="fa-solid fa-shield-halved mr-1"></i>Admin</a>
          <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Area akun: login/daftar atau profil user (desktop) -->
        <div class="hidden md:flex items-center gap-3">
          <?php if ($user): ?>
          <div class="tp-nav-glass flex items-center gap-2 px-3 py-1.5 rounded-full">
            <div class="w-6 h-6 bg-white/30 rounded-full flex items-center justify-center">
              <span class="text-[10px] font-bold text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
            </div>
            <span class="text-sm font-medium"><?= htmlspecialchars($user['name']) ?></span>
            <?php if ($user['role'] === 'admin'): ?>
            <span class="bg-amber-400 text-amber-900 text-[9px] px-1.5 py-0.5 rounded font-bold">ADMIN</span>
            <?php endif; ?>
          </div>
          <a href="auth/logout.php" class="tp-nav-outline text-sm font-medium px-3 py-1.5 rounded-lg">Keluar</a>
          <?php else: ?>
          <a href="auth/login.php" class="tp-nav-outline text-sm font-medium px-4 py-2 rounded-xl">Masuk</a>
          <a href="auth/register.php"
            class="text-sm font-bold text-teal-700 bg-white hover:bg-cyan-50 px-4 py-2 rounded-xl shadow">Daftar</a>
          <?php endif; ?>
        </div>

        <!-- Tombol hamburger, hanya tampil di mobile ('md:hidden') -->
        <button type="button" data-nav-toggle="tpMobileNav" aria-label="Buka menu" aria-expanded="false"
          class="md:hidden w-10 h-10 flex items-center justify-center text-white rounded-lg hover:bg-white/15 transition">
          <i data-nav-icon class="fa-solid fa-bars text-lg"></i>
        </button>
      </div>
    </div>

    <!-- Panel menu mobile: muncul saat hamburger diklik (di-toggle oleh assets/app.js) -->
    <div id="tpMobileNav" class="md:hidden hidden tp-mobile-menu border-t border-white/15">
      <div class="max-w-7xl mx-auto px-4 py-3 space-y-1">
        <a href="index.php" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/15">Beranda</a>
        <?php if ($user): ?>
        <a href="orders.php" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/15">Pesanan Saya</a>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="admin/index.php" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/15"><i
            class="fa-solid fa-shield-halved mr-1"></i>Admin</a>
        <?php endif; ?>
        <div class="flex items-center justify-between px-3 py-2 pt-3 mt-2 border-t border-white/15">
          <span class="text-sm font-medium truncate"><i
              class="fa-solid fa-user mr-1.5"></i><?= htmlspecialchars($user['name']) ?></span>
          <a href="auth/logout.php" class="text-sm bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg">Keluar</a>
        </div>
        <?php else: ?>
        <div class="flex gap-2 pt-3 mt-2 border-t border-white/15">
          <a href="auth/login.php"
            class="flex-1 text-center text-sm font-medium bg-white/15 hover:bg-white/25 px-3 py-2 rounded-lg">Masuk</a>
          <a href="auth/register.php"
            class="flex-1 text-center text-sm font-bold text-teal-700 bg-white px-3 py-2 rounded-lg">Daftar</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- ===================== HERO SECTION ===================== -->
  <section class="relative overflow-hidden text-white">

    <!-- Slideshow background: satu <div> per destinasi, dipindah oleh app.js (class is-active) -->
    <div class="tp-slides">
      <?php foreach ($destinations as $i => $dest): ?>
      <div class="tp-slide <?= $i === 0 ? 'is-active' : '' ?>"
        style="background-image: url('<?= htmlspecialchars($dest['image']) ?>');"></div>
      <?php endforeach; ?>
    </div>

    <!-- Overlay gelap-teal supaya teks tetap terbaca di atas foto -->
    <div class="tp-hero-overlay absolute inset-0 z-[2]"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 py-20 sm:py-28">
      <div class="max-w-2xl is-visible">
        <span
          class="tp-badge-glass inline-flex items-center gap-1.5 text-white text-xs font-medium px-3.5 py-1.5 rounded-full mb-5">
          <i class="fa-solid fa-sun text-amber-300 text-[11px]"></i> E-Tiketing Paranggupito
        </span>
        <h1 class="font-display text-4xl sm:text-6xl font-extrabold leading-[1.05] mb-5 tracking-tight">
          Jelajahi Pesona<br><span
            class="bg-clip-text text-transparent bg-gradient-to-r from-white via-cyan-100 to-teal-100">Pantai
            Paranggupito</span>
        </h1>
        <p class="text-base sm:text-lg text-white/85 mb-8 max-w-lg leading-relaxed">Platform resmi pembelian tiket
          wisata online
          Kecamatan Paranggupito, Kabupaten Wonogiri. Pesan dari rumah, nikmati pantainya.</p>

        <div class="flex flex-wrap items-center gap-3">
          <a href="#destinasi"
            class="tp-btn tp-btn-gradient inline-flex items-center gap-2 text-white text-sm font-semibold py-3 px-6 rounded-xl shadow-lg">
            <i class="fa-solid fa-compass"></i> Lihat Destinasi
          </a>
          <a href="#pesona"
            class="tp-btn inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/25 text-white text-sm font-semibold py-3 px-6 rounded-xl backdrop-blur-sm">
            <i class="fa-solid fa-play text-[11px]"></i> Selengkapnya
          </a>
        </div>
      </div>

      <!--
        Statistik ringkas: jumlah destinasi diambil DINAMIS dari database (count($destinations)),
        tapi "Ulasan" dan "Rating" DIHAPUS di versi ini karena sebelumnya di-hardcode
        ("1.9K+" dan "4.6") — angka itu tidak pernah berubah walau data ulasan asli berubah,
        sehingga berpotensi menyesatkan pengunjung.
        Jika nanti sudah ada tabel ulasan/rating di database, tinggal tambahkan lagi
        dengan query SUM/AVG yang sebenarnya.
      -->
      <div class="grid grid-cols-3 gap-2 sm:gap-5 mt-14 max-w-md reveal is-visible" style="--reveal-delay:120ms">
        <div class="tp-stat px-2 py-4 sm:px-5 sm:py-5 text-center">
          <div class="font-display text-2xl sm:text-4xl font-extrabold"><?= count($destinations) ?>+</div>
          <div class="text-white/75 text-[11px] sm:text-sm mt-0.5">Destinasi</div>
        </div>
        <div class="tp-stat px-2 py-4 sm:px-5 sm:py-5 text-center">
          <div class="font-display text-2xl sm:text-4xl font-extrabold flex items-center justify-center gap-1 sm:gap-1.5">
            <i class="fa-solid fa-star text-amber-300 text-base sm:text-2xl"></i><?= $avgRating ?: '—' ?>
          </div>
          <div class="text-white/75 text-[11px] sm:text-sm mt-0.5">Rating</div>
        </div>
        <div class="tp-stat px-2 py-4 sm:px-5 sm:py-5 text-center">
          <div class="font-display text-2xl sm:text-4xl font-extrabold"><?= $reviewsLabel ?></div>
          <div class="text-white/75 text-[11px] sm:text-sm mt-0.5">Ulasan</div>
        </div>
      </div>

      <!-- Dots navigasi slideshow: jumlahnya mengikuti jumlah destinasi -->
      <div class="mt-10 reveal is-visible">
        <div class="tp-dots" role="tablist" aria-label="Galeri pantai">
          <?php foreach ($destinations as $i => $dest): ?>
          <button type="button" class="tp-dot <?= $i === 0 ? 'is-active' : '' ?>"
            data-name="<?= htmlspecialchars($dest['name']) ?>"
            aria-label="Tampilkan <?= htmlspecialchars($dest['name']) ?>"></button>
          <?php endforeach; ?>
        </div>
        <p class="text-xs text-white/80 mt-3"><i class="fa-solid fa-camera-retro mr-1"></i><span
            class="tp-caption"><?= htmlspecialchars($destinations[0]['name'] ?? '') ?></span></p>
      </div>
    </div>

    <!-- Bentuk gelombang SVG sebagai transisi visual ke section berikutnya -->
    <div class="absolute bottom-0 w-full leading-[0] z-10">
      <svg viewBox="0 0 1440 80" fill="none" preserveAspectRatio="none" class="w-full h-[40px] sm:h-[70px]">
        <path d="M0 80V40C240 70 480 10 720 40C960 70 1200 10 1440 40V80H0Z" fill="#f9fafb" />
      </svg>
    </div>
  </section>

  <!-- ===================== DAFTAR DESTINASI ===================== -->
  <section id="destinasi" class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20 scroll-mt-20">
    <div class="flex items-end justify-between mb-10 reveal">
      <div>
        <span class="inline-flex items-center gap-1.5 text-teal-600 text-xs font-bold tracking-wider mb-2"><i
            class="fa-solid fa-water"></i> DESTINASI</span>
        <h2 class="font-display text-3xl sm:text-4xl font-extrabold text-gray-900">Wisata Pantai Pilihan</h2>
        <p class="text-gray-500 text-sm mt-2">Temukan pantai eksotis di Paranggupito, Wonogiri</p>
      </div>
      <!--
        DIHAPUS: tombol filter "Semua" / "Obyek Wisata".
        Alasan: tidak ada atribut (onclick / data-filter) atau script yang menghubungkan
        tombol ini ke logika filter apa pun — jadi selama ini cuma dekorasi yang
        terlihat bisa diklik tapi tidak melakukan apa-apa. Kalau nanti mau ada filter
        kategori sungguhan, bisa ditambahkan lagi dengan query berdasarkan $_GET['kategori'].
      -->
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
      <?php foreach ($destinations as $dest): ?>
      <!-- Satu kartu per destinasi -->
      <div class="reveal tp-card bg-white rounded-3xl overflow-hidden shadow-md border border-gray-100">
        <a href="destination.php?wisata=<?= urlencode($dest['slug']) ?>" class="tp-zoom block relative h-56">
          <img src="<?= htmlspecialchars($dest['image']) ?>" alt="<?= htmlspecialchars($dest['name']) ?>"
            class="w-full h-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent"></div>
          <div class="absolute top-3 left-3 flex gap-1.5">
            <span
              class="bg-emerald-500/90 text-white text-[10px] font-semibold px-2 py-0.5 rounded flex items-center gap-1">
              <span class="w-1.5 h-1.5 bg-white rounded-full"></span> Buka
            </span>
            <?php if ($dest['is_popular']): ?>
            <span
              class="bg-orange-400/90 text-white text-[10px] font-semibold px-2 py-0.5 rounded flex items-center gap-1">
              <i class="fa-solid fa-star text-[8px]"></i> Populer
            </span>
            <?php endif; ?>
          </div>
          <span
            class="absolute bottom-3 left-3 bg-black/40 backdrop-blur-sm text-white text-[10px] px-2 py-0.5 rounded"><?= htmlspecialchars($dest['category']) ?></span>
        </a>
        <div class="p-5">
          <h3 class="text-lg font-bold text-gray-900 mb-2"><a
              href="destination.php?wisata=<?= urlencode($dest['slug']) ?>"
              class="hover:text-teal-600 transition-colors"><?= htmlspecialchars($dest['name']) ?></a></h3>
          <div class="flex items-start gap-1.5 text-xs text-gray-500 mb-2">
            <i class="fa-solid fa-location-dot mt-0.5 text-gray-400 shrink-0"></i>
            <p class="line-clamp-2"><?= htmlspecialchars($dest['location']) ?></p>
          </div>
          <!-- Rating & ulasan: TETAP DIPERTAHANKAN karena datanya asli dari database ($dest['rating']/$dest['reviews']),
               beda dengan statistik ulasan global di Hero yang sebelumnya di-hardcode -->
          <div class="flex items-center gap-1.5 text-xs text-gray-500 mb-2">
            <i class="fa-solid fa-star text-amber-400"></i>
            <?php if ($dest['reviews'] > 0): ?>
            <span class="font-bold text-gray-700"><?= $dest['rating'] ?></span>
            <span class="text-gray-400">(<?= $dest['reviews'] ?> ulasan)</span>
            <?php else: ?>
            <span class="text-gray-400">Belum ada ulasan</span>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-1.5 text-xs text-gray-500 mb-4">
            <i class="fa-regular fa-clock text-gray-400"></i>
            <span><?= htmlspecialchars($dest['open_hours']) ?></span>
          </div>
          <div class="flex items-center justify-between">
            <div>
              <div class="text-[10px] text-gray-400">Mulai dari</div>
              <div class="text-sm font-bold text-teal-600">Rp <?= number_format($dest['price'], 0, ',', '.') ?></div>
            </div>
            <a href="checkout.php?wisata=<?= urlencode($dest['slug']) ?>"
              class="tp-btn tp-btn-gradient text-white text-sm font-semibold py-2 px-4 rounded-xl flex items-center gap-1.5 shadow-sm">
              <i class="fa-solid fa-ticket"></i> Beli Tiket
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ===================== FITUR / PESONA ===================== -->
  <section id="pesona" class="bg-gradient-to-b from-cyan-50/70 via-white to-white py-20 scroll-mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 text-center">
      <div class="reveal">
        <span class="inline-flex items-center gap-1.5 text-teal-600 text-xs font-bold tracking-wider mb-2"><i
            class="fa-solid fa-star"></i> PESONA</span>
        <h2 class="font-display text-3xl sm:text-4xl font-extrabold text-gray-900 mb-3">Keindahan yang Memikat</h2>
        <p class="text-gray-500 max-w-xl mx-auto mb-12">Karakteristik unik dari pantai-pantai eksotis di Kabupaten
          Wonogiri.</p>
      </div>
      <div class="flex flex-wrap justify-center gap-6 max-w-6xl mx-auto">
        <?php
        // Kombinasi warna gradient + icon, dipakai bergantian (rotasi) untuk tiap kartu fitur.
        // Sudah dinamis: kalau destinasi dihapus/dinonaktifkan, kartunya otomatis hilang.
        $featStyles = [
          ['from-teal-500', 'to-cyan-600', 'fa-anchor'],
          ['from-emerald-500', 'to-teal-600', 'fa-mountain'],
          ['from-amber-500', 'to-orange-600', 'fa-compass'],
          ['from-sky-500', 'to-cyan-600', 'fa-water'],
          ['from-violet-500', 'to-purple-600', 'fa-umbrella-beach'],
          ['from-rose-500', 'to-pink-600', 'fa-sun'],
          ['from-cyan-500', 'to-blue-600', 'fa-fish'],
          ['from-lime-500', 'to-emerald-600', 'fa-leaf'],
        ];
        if (empty($destinations)):
        ?>
        <p class="text-gray-400 col-span-full py-8">Belum ada destinasi untuk ditampilkan.</p>
        <?php else: foreach (array_slice($destinations, 0, 8) as $fi => $fd):
          $st = $featStyles[$fi % count($featStyles)]; // % supaya style berulang jika destinasi > 8
        ?>
        <div
          class="reveal tp-card flex flex-col items-center text-center px-6 py-8 rounded-3xl text-white shadow-lg bg-gradient-to-br <?= $st[0] ?> <?= $st[1] ?> w-full sm:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)]"
          style="--reveal-delay:<?= $fi * 60 ?>ms">
          <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mb-5"><i
              class="fa-solid <?= $st[2] ?> text-xl"></i></div>
          <h3 class="text-lg font-bold mb-2"><?= htmlspecialchars($fd['name']) ?></h3>
          <?php
          // Batasi deskripsi maksimal 120 karakter (tanpa scroll).
          $featDesc = trim(preg_replace('/\s+/', ' ', $fd['description'] ?: 'Destinasi wisata pilihan di Paranggupito, Wonogiri.'));
          $featDesc = mb_strlen($featDesc) > 120 ? mb_substr($featDesc, 0, 120) . '…' : $featDesc;
          ?>
          <p class="w-full text-left text-sm text-white/90 leading-relaxed"><?= htmlspecialchars($featDesc) ?></p>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </section>

  <!-- ===================== FOOTER ===================== -->
  <footer class="bg-[#0b1325] text-[#a0aec0] text-xs mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 mb-10">
        <div>
          <h3 class="text-white text-xl font-bold tracking-wide mb-3">tiket<span
              class="text-teal-400">Paranggupito</span></h3>
          <p class="text-[#8a99ad] leading-relaxed text-[13px]">Platform resmi pembelian tiket wisata online Kecamatan
            Paranggupito.</p>
        </div>
        <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-8">
          <div>
            <h5 class="text-white text-[11px] font-bold tracking-widest uppercase mb-4">Hubungi Kami</h5>
            <ul class="space-y-3 text-[13px]">
              <li class="flex items-start gap-2"><i
                  class="fa-solid fa-location-dot text-gray-500 mt-0.5"></i><span>Kabupaten Wonogiri, Jawa Tengah</span>
              </li>
              <li class="flex items-center gap-2"><i
                  class="fa-solid fa-envelope text-gray-500"></i><span>info@etiketingparanggupito.com</span></li>
            </ul>
          </div>
          <div>
            <h5 class="text-white text-[11px] font-bold tracking-widest uppercase mb-4">Layanan Pelanggan</h5>
            <ul class="space-y-2 text-[13px] mb-3">
              <li class="flex items-center gap-2"><i
                  class="fa-brands fa-whatsapp text-emerald-500"></i><span>0857-2826-9876</span></li>
              <li class="flex items-center gap-2"><i
                  class="fa-brands fa-whatsapp text-emerald-500"></i><span>0812-9476-1810</span></li>
            </ul>
          </div>
        </div>
      </div>
      <hr class="border-[#1e293b] my-6">
      <div
        class="flex flex-col md:flex-row md:justify-between items-center text-center md:text-left text-[#718096] text-[11px] gap-2">
        <p>&copy; <?= date('Y') ?> E-Tiketing Paranggupito. Seluruh hak cipta dilindungi.</p>
        <p class="text-gray-500">Dikelola oleh Pemerintah Kabupaten Wonogiri</p>
      </div>
    </div>
  </footer>

  <!-- Script untuk interaksi: toggle menu mobile, slideshow otomatis, animasi 'reveal', dll -->
  <script src="assets/app.js"></script>
</body>

</html>