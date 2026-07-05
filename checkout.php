<?php
session_start();
$pdo = require __DIR__ . '/db.php';
$user = $_SESSION['user'] ?? null;
$slug = $_GET['wisata'] ?? '';

// Konfigurasi metode pembayaran (bank, e-wallet, QRIS)
require __DIR__ . '/payments.php';
$payCfg = get_payments();

// Fetch destination by slug
$stmt = $pdo->prepare('SELECT * FROM destinations WHERE slug = ? AND is_active = TRUE LIMIT 1');
$stmt->execute([$slug]);
$dest = $stmt->fetch();

if (!$dest) {
    die('Destinasi tidak ditemukan');
}

// Fetch ticket types
$ttStmt = $pdo->prepare('SELECT * FROM ticket_types WHERE destination_id = ? ORDER BY price ASC');
$ttStmt->execute([$dest['id']]);
$ticketTypes = $ttStmt->fetchAll();

// Identifikasi tiket masuk (wajib dipesan minimal 1 sebelum fasilitas lain)
$entryTicketId = null;
foreach ($ticketTypes as $tt) {
    if (stripos($tt['name'], 'masuk') !== false) {
        $entryTicketId = (int)$tt['id'];
        break;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Checkout - <?= htmlspecialchars($dest['name']) ?> - TiketPantai</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css?v=5">
  <style>
  body { font-family: 'Inter', sans-serif; }
  h1, h2, h3, .font-display { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
  </style>
</head>
<body class="tp-page-bg min-h-screen flex flex-col">

  <!-- Navbar -->
  <nav class="tp-nav tp-navbar sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
      <a href="index.php" class="flex items-center gap-2 hover:opacity-80 transition">
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center"><i class="fa-solid fa-water text-white text-base"></i></div>
        <span class="text-xl font-bold text-white">tiket<span class="text-cyan-100">Pantai</span></span>
      </a>
      <div class="hidden md:flex items-center gap-3">
        <?php if ($user): ?>
        <a href="orders.php" class="tp-nav-link text-sm font-medium px-3 py-1.5">Pesanan Saya</a>
        <div class="tp-nav-glass flex items-center gap-2 px-3 py-1.5 rounded-full">
          <div class="w-6 h-6 bg-white/30 rounded-full flex items-center justify-center"><span class="text-[10px] font-bold text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span></div>
          <span class="text-sm font-medium"><?= htmlspecialchars($user['name']) ?></span>
        </div>
        <a href="auth/logout.php" class="tp-nav-outline text-sm font-medium px-3 py-1.5 rounded-lg">Keluar</a>
        <?php else: ?>
        <a href="auth/login.php" class="tp-nav-outline text-sm font-medium px-4 py-2 rounded-xl">Masuk</a>
        <a href="auth/register.php" class="text-sm font-bold text-teal-700 bg-white hover:bg-cyan-50 px-4 py-2 rounded-xl shadow">Daftar</a>
        <?php endif; ?>
      </div>
      <button type="button" data-nav-toggle="tpMobileNav" aria-label="Buka menu" aria-expanded="false" class="md:hidden w-10 h-10 flex items-center justify-center text-white rounded-lg hover:bg-white/15 transition">
        <i data-nav-icon class="fa-solid fa-bars text-lg"></i>
      </button>
    </div>
    <div id="tpMobileNav" class="md:hidden hidden tp-mobile-menu border-t border-white/15">
      <div class="max-w-7xl mx-auto px-4 py-3 space-y-1">
        <a href="index.php" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/15">Beranda</a>
        <?php if ($user): ?>
        <a href="orders.php" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/15">Pesanan Saya</a>
        <div class="flex items-center justify-between px-3 py-2 pt-3 mt-2 border-t border-white/15">
          <span class="text-sm font-medium truncate"><i class="fa-solid fa-user mr-1.5"></i><?= htmlspecialchars($user['name']) ?></span>
          <a href="auth/logout.php" class="text-sm bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg">Keluar</a>
        </div>
        <?php else: ?>
        <div class="flex gap-2 pt-3 mt-2 border-t border-white/15">
          <a href="auth/login.php" class="flex-1 text-center text-sm font-medium bg-white/15 hover:bg-white/25 px-3 py-2 rounded-lg">Masuk</a>
          <a href="auth/register.php" class="flex-1 text-center text-sm font-bold text-teal-700 bg-white px-3 py-2 rounded-lg">Daftar</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <div class="max-w-5xl mx-auto my-8 px-4">
    <a href="index.php" class="text-sm text-gray-500 hover:text-gray-700 mb-6 inline-flex items-center gap-1"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
    <h1 class="font-display text-3xl font-extrabold text-gray-900 mb-1"><?= htmlspecialchars($dest['name']) ?></h1>
    <p class="text-sm text-gray-500 mb-6">Selesaikan pemesanan tiket masuk dan fasilitas Anda.</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div class="lg:col-span-2">
        <div class="reveal bg-white rounded-3xl shadow-md overflow-hidden">
          <div class="p-6 border-b border-gray-100 flex items-center gap-3">
            <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center"><i class="fa-solid fa-ticket text-teal-600"></i></div>
            <div>
              <h2 class="font-bold text-gray-900">Pilih Tiket & Fasilitas</h2>
              <p class="text-xs text-gray-400">Pilih jenis & jumlah tiket yang ingin dibeli</p>
            </div>
          </div>
          <div class="p-6">
            <form id="checkoutForm" method="POST" action="process_order.php">
              <input type="hidden" name="destination_id" value="<?= $dest['id'] ?>">
              <?php if ($entryTicketId): ?>
              <div id="entryHint" class="hidden mb-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs font-medium px-3 py-2 rounded-lg flex items-center gap-2">
                <i class="fa-solid fa-circle-info"></i> Pilih <strong>tiket masuk</strong> terlebih dahulu sebelum memesan fasilitas lain (tikar, kursi, dll).
              </div>
              <?php endif; ?>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($ticketTypes as $tt): ?>
                <?php $isEntry = $entryTicketId === (int)$tt['id']; ?>
                <div id="card-<?= $tt['id'] ?>" data-ticket-card="<?= $tt['id'] ?>" class="tp-card relative border rounded-3xl p-5 flex flex-col justify-between min-h-[150px] <?= $isEntry ? 'border-teal-400 ring-1 ring-teal-200 bg-teal-50/40 hover:border-teal-500' : 'border-gray-200 hover:border-teal-400' ?>">
                  <?php if ($isEntry): ?>
                  <span class="absolute top-3 right-3 bg-teal-500 text-white text-[9px] font-bold px-2 py-0.5 rounded-full shadow-sm">WAJIB</span>
                  <?php endif; ?>
                  <div>
                    <h3 class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($tt['name']) ?></h3>
                    <div class="text-teal-600 font-bold text-xl mt-2">Rp <?= number_format($tt['price'], 0, ',', '.') ?></div>
                    <div class="text-[11px] text-gray-400 mt-0.5"><?= htmlspecialchars($tt['unit']) ?></div>
                    <?php if ($tt['description']): ?>
                    <div class="text-[11px] text-gray-400 mt-1"><?= htmlspecialchars($tt['description']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="flex items-center justify-between bg-gray-50 rounded-xl p-2 mt-4">
                    <span class="text-xs text-gray-400 pl-2">Jumlah</span>
                    <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-lg p-1">
                      <button type="button" onclick="ubahJumlah(<?= $tt['id'] ?>, -1)" class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-md transition cursor-pointer text-sm font-semibold">&mdash;</button>
                      <span id="qty-<?= $tt['id'] ?>" class="font-bold text-gray-900 text-sm min-w-[20px] text-center">0</span>
                      <button type="button" onclick="ubahJumlah(<?= $tt['id'] ?>, 1)" class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-md transition cursor-pointer text-sm font-semibold">+</button>
                    </div>
                  </div>
              <input type="hidden" name="qty[<?= $tt['id'] ?>]" id="input-<?= $tt['id'] ?>" value="0" data-price="<?= $tt['price'] ?>">
                </div>
                <?php endforeach; ?>
              </div>

              <div class="mt-6 space-y-4">
                <div>
                  <label class="block text-xs text-gray-500 mb-1.5 font-medium">Tanggal Kunjungan</label>
                  <input type="date" name="visit_date" id="visit_date" min="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" required>
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1.5 font-medium">Metode Pembayaran</label>
                  <!-- Pilih tipe metode -->
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                    <?php foreach ($payCfg['groups'] as $gKey => $g): ?>
                    <button type="button" data-group="<?= $gKey ?>"
                      onclick="pilihMetode('<?= $gKey ?>', '<?= htmlspecialchars($g['label'], ENT_QUOTES) ?>')"
                      class="metode-btn flex flex-col items-center gap-1.5 border border-gray-200 rounded-xl py-3 px-2 text-xs font-medium text-gray-600 hover:border-teal-400 hover:bg-teal-50 transition cursor-pointer">
                      <i class="fa-solid <?= $g['icon'] ?> text-lg"></i>
                      <span><?= htmlspecialchars($g['label']) ?></span>
                    </button>
                    <?php endforeach; ?>
                  </div>

                  <!-- Panel sub-pilihan dinamis -->
                  <div id="payment-panel" class="hidden border border-gray-200 rounded-xl p-4 bg-gray-50">
                    <!-- Transfer Bank -->
                    <div id="panel-bank" class="hidden">
                      <p class="text-xs text-gray-500 mb-2">Pilih bank tujuan transfer:</p>
                      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <?php foreach ($payCfg['bank'] as $b): ?>
                        <button type="button"
                          onclick="pilihProvider(this,'<?= htmlspecialchars($b['key'], ENT_QUOTES) ?>')"
                          class="provider-btn flex flex-col items-center gap-1 border border-gray-200 bg-white rounded-xl py-2.5 px-2 text-[11px] font-medium text-gray-600 hover:border-teal-400 transition cursor-pointer">
                          <i class="fa-solid fa-building-columns text-teal-600"></i>
                          <span><?= htmlspecialchars($b['name']) ?></span>
                        </button>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <!-- E-Wallet -->
                    <div id="panel-ewallet" class="hidden">
                      <p class="text-xs text-gray-500 mb-2">Pilih e-wallet:</p>
                      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <?php foreach ($payCfg['ewallet'] as $w): ?>
                        <button type="button"
                          onclick="pilihProvider(this,'<?= htmlspecialchars($w['key'], ENT_QUOTES) ?>')"
                          class="provider-btn flex flex-col items-center gap-1 border border-gray-200 bg-white rounded-xl py-2.5 px-2 text-[11px] font-medium text-gray-600 hover:border-teal-400 transition cursor-pointer">
                          <i class="fa-solid fa-wallet text-teal-600"></i>
                          <span><?= htmlspecialchars($w['name']) ?></span>
                        </button>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <!-- QRIS -->
                    <div id="panel-qris" class="hidden text-center">
                      <img src="<?= htmlspecialchars($payCfg['qris']['image']) ?>" alt="QRIS" class="w-40 h-40 mx-auto rounded-xl border border-gray-200 bg-white object-contain p-2">
                      <p class="text-xs text-gray-500 mt-2"><i class="fa-solid fa-qrcode mr-1"></i>Scan kode QRIS di atas untuk membayar</p>
                    </div>
                    <!-- Bayar di Lokasi -->
                    <div id="panel-location" class="hidden text-center py-2">
                      <i class="fa-solid fa-map-location-dot text-2xl text-teal-600 mb-2"></i>
                      <p class="text-xs text-gray-600">Pembayaran dilakukan langsung di lokasi destinasi saat kunjungan.</p>
                    </div>
                  </div>

                  <input type="hidden" name="payment_method" id="input-method" value="">
                  <input type="hidden" name="payment_detail" id="input-detail" value="">
                  <p id="payment-error" class="hidden text-xs text-red-500 mt-2"></p>
                </div>
              </div>

              <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                <button type="submit" id="submitBtn" class="tp-btn tp-btn-gradient text-white text-sm font-semibold py-2.5 px-6 rounded-xl shadow-md cursor-pointer flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                  <i class="fa-solid fa-credit-card"></i> Lanjutkan Pembayaran
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div>
        <div class="reveal bg-white rounded-2xl shadow-lg p-6 sticky top-24">
          <h3 class="font-bold text-gray-900 mb-4">Ringkasan Pesanan</h3>
          <div class="text-sm text-gray-400 text-center py-4" id="summary-empty">Belum ada item dipilih</div>
          <div id="summary-items" class="space-y-2 hidden"></div>
          <hr class="my-4">
          <div class="flex justify-between font-bold text-lg">
            <span>Total</span>
            <span class="text-teal-600" id="total-display">Rp 0</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    let dataTiket = {};
    <?php foreach ($ticketTypes as $tt): ?>
    dataTiket[<?= $tt['id'] ?>] = { qty: 0, price: <?= $tt['price'] ?>, name: '<?= addslashes($tt['name']) ?>' };
    <?php endforeach; ?>

    let metodeTerpilih = null;   // key kelompok: bank | ewallet | qris | location
    let providerTerpilih = null; // key provider untuk bank/ewallet
    const ENTRY_ID = <?= json_encode($entryTicketId) ?>; // id tiket masuk (null bila tak ada)

    // Kunci/buka kartu fasilitas sesuai tiket masuk sudah dipilih atau belum
    function refreshEntryLock() {
      if (!ENTRY_ID) return;
      const unlocked = dataTiket[ENTRY_ID].qty > 0;
      document.querySelectorAll('[data-ticket-card]').forEach(function (card) {
        if (parseInt(card.dataset.ticketCard, 10) !== ENTRY_ID) {
          card.classList.toggle('tp-locked', !unlocked);
        }
      });
    }

    let entryHintTimer = null;
    function flashEntryHint() {
      const el = document.getElementById('entryHint');
      if (!el) return;
      el.classList.remove('hidden');
      clearTimeout(entryHintTimer);
      entryHintTimer = setTimeout(function () { el.classList.add('hidden'); }, 2500);
    }

    function pilihMetode(groupKey, label) {
      metodeTerpilih = groupKey;
      providerTerpilih = null;
      document.getElementById('input-method').value = label;

      // QRIS & Lokasi tidak butuh provider terpisah
      if (groupKey === 'qris') {
        providerTerpilih = 'qris';
        document.getElementById('input-detail').value = 'qris';
      } else if (groupKey === 'location') {
        document.getElementById('input-detail').value = '';
      } else {
        document.getElementById('input-detail').value = '';
      }

      // Tampilkan panel yang sesuai
      document.getElementById('payment-panel').classList.remove('hidden');
      ['bank', 'ewallet', 'qris', 'location'].forEach(function (k) {
        document.getElementById('panel-' + k).classList.toggle('hidden', k !== groupKey);
      });

      // Highlight tombol metode
      document.querySelectorAll('.metode-btn').forEach(function (b) {
        const on = b.dataset.group === groupKey;
        b.classList.toggle('border-teal-500', on);
        b.classList.toggle('bg-teal-50', on);
        b.classList.toggle('text-teal-700', on);
      });
      resetProviderHighlight();
      updateSummary();
    }

    function pilihProvider(btn, key) {
      providerTerpilih = key;
      document.getElementById('input-detail').value = key;
      resetProviderHighlight();
      btn.classList.add('border-teal-500', 'bg-teal-50', 'text-teal-700');
      updateSummary();
    }

    function resetProviderHighlight() {
      document.querySelectorAll('.provider-btn').forEach(function (b) {
        b.classList.remove('border-teal-500', 'bg-teal-50', 'text-teal-700');
      });
    }

    function ubahJumlah(id, aksi) {
      // Fasilitas hanya bisa dipesan setelah tiket masuk dipilih
      if (ENTRY_ID && id !== ENTRY_ID && dataTiket[ENTRY_ID].qty === 0) {
        flashEntryHint();
        return;
      }
      dataTiket[id].qty += aksi;
      if (dataTiket[id].qty < 0) dataTiket[id].qty = 0;
      document.getElementById('qty-' + id).innerText = dataTiket[id].qty;
      document.getElementById('input-' + id).value = dataTiket[id].qty;
      refreshEntryLock();
      updateSummary();
    }

    function updateSummary() {
      let total = 0;
      let hasItems = false;
      let html = '';
      for (const [id, item] of Object.entries(dataTiket)) {
        if (item.qty > 0) {
          hasItems = true;
          const sub = item.price * item.qty;
          total += sub;
          html += `<div class="flex justify-between text-sm"><span class="text-gray-600">${item.name} x${item.qty}</span><span class="font-medium">Rp ${sub.toLocaleString('id-ID')}</span></div>`;
        }
      }
      document.getElementById('summary-items').innerHTML = html;
      document.getElementById('summary-items').classList.toggle('hidden', !hasItems);
      document.getElementById('summary-empty').classList.toggle('hidden', hasItems);
      document.getElementById('total-display').innerText = 'Rp ' + total.toLocaleString('id-ID');

      // Validasi pembayaran: untuk bank/ewallet wajib pilih provider
      const autoProviders = ['qris', 'location'];
      const paymentReady = metodeTerpilih && (autoProviders.includes(metodeTerpilih) || providerTerpilih);
      const err = document.getElementById('payment-error');
      if (hasItems && !paymentReady) {
        err.textContent = metodeTerpilih
          ? 'Silakan pilih provider ' + (metodeTerpilih === 'bank' ? 'bank' : 'e-wallet') + ' di atas.'
          : 'Silakan pilih metode pembayaran.';
        err.classList.remove('hidden');
      } else {
        err.classList.add('hidden');
      }
      const entryOk = !ENTRY_ID || dataTiket[ENTRY_ID].qty > 0;
      document.getElementById('submitBtn').disabled = !(hasItems && paymentReady && entryOk);
    }

    // Saat halaman dimuat: kunci fasilitas sampai tiket masuk dipilih
    refreshEntryLock();
  </script>

  <!-- Footer -->
  <footer class="bg-[#0b1325] text-[#718096] text-[11px] mt-auto">
    <div class="max-w-7xl mx-auto px-4 py-8 text-center">
      <div class="text-white text-sm font-bold mb-1">tiket<span class="text-teal-400">Pantai</span></div>
      <p>&copy; <?= date('Y') ?> E-Tiketing Paranggupito. Seluruh hak cipta dilindungi.</p>
    </div>
  </footer>

  <script src="assets/app.js"></script>
</body>
</html>
