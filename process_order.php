<?php
session_start();
$pdo = require __DIR__ . '/db.php';
$user = $_SESSION['user'] ?? null;

if (!$user) {
    header('Location: auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$destinationId = (int)($_POST['destination_id'] ?? 0);
$visitDate = $_POST['visit_date'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$paymentDetail = $_POST['payment_detail'] ?? '';
$qty = $_POST['qty'] ?? [];

// Konfigurasi & resolusi metode pembayaran
require __DIR__ . '/payments.php';
$payInfo = resolve_payment($paymentMethod, $paymentDetail);

if (!$destinationId || !$visitDate) {
    die('Data pesanan tidak lengkap');
}

// Calculate total and validate
$totalAmount = 0;
$validatedItems = [];

foreach ($qty as $ttId => $quantity) {
    $quantity = (int)$quantity;
    if ($quantity <= 0) continue;
    
    $stmt = $pdo->prepare('SELECT * FROM ticket_types WHERE id = ? AND destination_id = ?');
    $stmt->execute([$ttId, $destinationId]);
    $tt = $stmt->fetch();
    
    if (!$tt) continue;

    // Pengaman server-side: jangan melebihi maksimal pesanan (jika diset admin).
    if (!empty($tt['max_qty']) && $quantity > (int)$tt['max_qty']) {
        $quantity = (int)$tt['max_qty'];
    }

    $subtotal = $tt['price'] * $quantity;
    $totalAmount += $subtotal;
    $validatedItems[] = ['ticket_type_id' => $ttId, 'quantity' => $quantity, 'subtotal' => $subtotal, 'name' => $tt['name']];
}

if (empty($validatedItems)) {
    die('Silakan pilih minimal 1 tiket');
}

// Generate order number
$orderNumber = 'TP-' . time() . '-' . strtoupper(substr(md5(uniqid()), 0, 5));

// Create order
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO orders (order_number, user_id, destination_id, visit_date, total_amount, payment_method, payment_detail) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$orderNumber, $user['id'], $destinationId, $visitDate, $totalAmount, $paymentMethod, $paymentDetail]);
    $orderId = $pdo->lastInsertId();
    
    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, ticket_type_id, quantity, subtotal) VALUES (?, ?, ?, ?)');
    foreach ($validatedItems as $item) {
        $itemStmt->execute([$orderId, $item['ticket_type_id'], $item['quantity'], $item['subtotal']]);
    }
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Gagal membuat pesanan: ' . $e->getMessage());
}

// Get destination name
$destStmt = $pdo->prepare('SELECT name FROM destinations WHERE id = ?');
$destStmt->execute([$destinationId]);
$destName = $destStmt->fetchColumn();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pesanan Berhasil - TiketPantai</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css?v=7">
  <style>
  body { font-family: 'Inter', sans-serif; }
  h1, h2, h3, .font-display { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
  </style>
</head>
<body class="tp-page-bg min-h-screen flex flex-col">
  <nav class="tp-nav sticky top-0 z-50 bg-white/85 backdrop-blur-lg border-b border-gray-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
      <a href="index.php" class="flex items-center gap-2 hover:opacity-80 transition">
        <div class="w-8 h-8 bg-teal-500 rounded-lg flex items-center justify-center"><i class="fa-solid fa-water text-white text-sm"></i></div>
        <span class="text-xl font-bold text-gray-900">tiket<span class="text-teal-500">Pantai</span></span>
      </a>
      <a href="orders.php" class="text-sm text-gray-500 hover:text-gray-700 inline-flex items-center gap-1"><i class="fa-solid fa-receipt"></i> Pesanan Saya</a>
    </div>
  </nav>

  <div class="max-w-lg mx-auto my-10 sm:my-16 px-4">
    <!-- Header sukses -->
    <div class="tp-success reveal rounded-3xl p-8 text-center text-white shadow-xl mb-6">
      <div class="tp-success-pop tp-success-ring w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-5">
        <i class="fa-solid fa-check text-4xl"></i>
      </div>
      <h1 class="font-display text-3xl font-extrabold mb-2">Pesanan Berhasil!</h1>
      <p class="text-white/85 text-sm">E-tiket Anda telah dibuat. Selesaikan pembayaran untuk mengonfirmasi pesanan.</p>
    </div>

    <!-- Receipt / kupon -->
    <div class="tp-ticket reveal shadow-md" style="--reveal-delay:80ms">
      <div class="p-6">
        <div class="text-center mb-4">
          <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">Nomor Pesanan</div>
          <div class="font-mono text-lg font-bold text-gray-900"><?= $orderNumber ?></div>
        </div>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between gap-4"><span class="text-gray-500">Destinasi</span><span class="font-medium text-right"><?= htmlspecialchars($destName) ?></span></div>
          <div class="flex justify-between"><span class="text-gray-500">Tanggal Kunjungan</span><span class="font-medium"><?= date('d F Y', strtotime($visitDate)) ?></span></div>
          <div class="flex justify-between"><span class="text-gray-500">Pembayaran</span><span class="font-medium"><?= htmlspecialchars($paymentMethod) ?></span></div>
        </div>
        <div class="mt-4 pt-4 border-t border-dashed border-gray-200 space-y-2 text-sm">
          <?php foreach ($validatedItems as $item): ?>
          <div class="flex justify-between"><span class="text-gray-500"><?= htmlspecialchars($item['name']) ?> x<?= $item['quantity'] ?></span><span class="font-medium">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span></div>
          <?php endforeach; ?>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
          <span class="font-bold text-gray-900">Total</span>
          <span class="font-display text-xl font-extrabold text-teal-600">Rp <?= number_format($totalAmount, 0, ',', '.') ?></span>
        </div>
      </div>

      <!-- Garis sobek kupon -->
      <div class="tp-ticket-tear relative px-6">
        <span class="tp-ticket-notch tp-ticket-notch--left"></span>
        <span class="tp-ticket-notch tp-ticket-notch--right"></span>
      </div>

      <!-- Instruksi Pembayaran -->
      <div class="p-6">
        <div class="text-xs font-semibold text-gray-500 mb-3 flex items-center gap-1.5"><i class="fa-solid fa-credit-card text-teal-500"></i> Instruksi Pembayaran</div>
        <?php if ($payInfo['type'] === 'bank'): ?>
          <?php $p = $payInfo['provider']; ?>
          <div class="text-center bg-gray-50 rounded-xl p-4">
            <div class="text-xs text-gray-400">Transfer ke rekening</div>
            <div class="font-bold text-gray-900"><?= htmlspecialchars($p['name'] ?? '') ?></div>
            <div class="font-mono text-2xl font-bold text-teal-600 my-2 tracking-wider"><?= htmlspecialchars($p['number'] ?? '') ?></div>
            <div class="text-xs text-gray-500">a.n. <?= htmlspecialchars($p['holder'] ?? '') ?></div>
            <button onclick="salinNomor('<?= htmlspecialchars($p['number'] ?? '', ENT_QUOTES) ?>', this)" class="tp-btn-soft mt-3"><i class="fa-regular fa-copy"></i> Salin Nomor</button>
          </div>
        <?php elseif ($payInfo['type'] === 'ewallet'): ?>
          <?php $p = $payInfo['provider']; ?>
          <div class="text-center bg-gray-50 rounded-xl p-4">
            <div class="text-xs text-gray-400">Kirim ke <?= htmlspecialchars($p['name'] ?? '') ?></div>
            <div class="font-mono text-xl font-bold text-teal-600 my-2 tracking-wider"><?= htmlspecialchars($p['number'] ?? '') ?></div>
            <div class="text-xs text-gray-500">a.n. <?= htmlspecialchars($p['holder'] ?? '') ?></div>
            <button onclick="salinNomor('<?= htmlspecialchars($p['number'] ?? '', ENT_QUOTES) ?>', this)" class="tp-btn-soft mt-3"><i class="fa-regular fa-copy"></i> Salin Nomor</button>
          </div>
        <?php elseif ($payInfo['type'] === 'qris'): ?>
          <div class="text-center">
            <img src="<?= htmlspecialchars($payInfo['image']) ?>" alt="QRIS" class="w-44 h-44 mx-auto rounded-xl border border-gray-200 object-contain p-2 bg-white">
            <div class="text-xs text-gray-500 mt-2">Scan QRIS di atas untuk membayar <strong>Rp <?= number_format($totalAmount, 0, ',', '.') ?></strong></div>
          </div>
        <?php elseif ($payInfo['type'] === 'location'): ?>
          <div class="text-center text-xs text-gray-600 bg-gray-50 rounded-xl py-4">
            <i class="fa-solid fa-map-location-dot text-teal-600 text-lg mb-1 block"></i>
            Pembayaran dilakukan langsung di lokasi destinasi saat kunjungan.
          </div>
        <?php else: ?>
          <div class="text-center text-xs text-gray-500 py-2">Metode pembayaran tidak terdefinisi.</div>
        <?php endif; ?>
        <div class="mt-3 bg-amber-50 text-amber-700 text-xs p-3 rounded-xl">
          <i class="fa-solid fa-circle-info mr-1"></i> Status: <strong>Menunggu Pembayaran</strong>. Pesanan akan dikonfirmasi admin setelah pembayaran diterima.
        </div>
      </div>
    </div>

    <div class="flex gap-3 mt-8 justify-center">
      <a href="orders.php" class="tp-btn tp-btn-gradient text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-sm">Lihat Pesanan Saya</a>
      <a href="index.php" class="tp-btn border border-gray-200 text-gray-600 px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-50">Kembali</a>
    </div>
  </div>

  <script>
    function salinNomor(nomor, btn) {
      const awal = btn.innerHTML;
      navigator.clipboard.writeText(nomor).then(function () {
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin';
        setTimeout(function () { btn.innerHTML = awal; }, 1500);
      }).catch(function () {
        prompt('Salin nomor ini:', nomor);
      });
    }
  </script>
  <script src="assets/app.js"></script>
</body>
</html>
