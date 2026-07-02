<?php
session_start();
$pdo = require __DIR__ . '/db.php';
$user = $_SESSION['user'] ?? null;

// Helper metode pembayaran
require __DIR__ . '/payments.php';

if (!$user) {
    header('Location: auth/login.php');
    exit;
}

// Fetch user orders
$stmt = $pdo->prepare('
    SELECT o.*, d.name as dest_name, d.location as dest_location, d.image as dest_image
    FROM orders o
    JOIN destinations d ON o.destination_id = d.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

// Get order items for each order
foreach ($orders as &$order) {
    $itemStmt = $pdo->prepare('
        SELECT oi.*, tt.name as tt_name, tt.price as tt_price
        FROM order_items oi
        JOIN ticket_types tt ON oi.ticket_type_id = tt.id
        WHERE oi.order_id = ?
    ');
    $itemStmt->execute([$order['id']]);
    $order['items'] = $itemStmt->fetchAll();
}
unset($order);

$statusConfig = [
    'pending' => ['color' => 'bg-amber-100 text-amber-700', 'icon' => 'fa-clock', 'label' => 'Menunggu'],
    'paid' => ['color' => 'bg-teal-100 text-teal-700', 'icon' => 'fa-check', 'label' => 'Dibayar'],
    'completed' => ['color' => 'bg-emerald-100 text-emerald-700', 'icon' => 'fa-check-double', 'label' => 'Selesai'],
    'cancelled' => ['color' => 'bg-red-100 text-red-700', 'icon' => 'fa-xmark', 'label' => 'Dibatalkan'],
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pesanan Saya - TiketPantai</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css?v=4">
  <style>
  body { font-family: 'Inter', sans-serif; }
  h1, h2, h3, .font-display { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
  </style>
</head>
<body class="tp-page-bg min-h-screen flex flex-col">
  <nav class="tp-nav tp-navbar sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
      <a href="index.php" class="flex items-center gap-2 hover:opacity-80 transition">
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center"><i class="fa-solid fa-water text-white text-base"></i></div>
        <span class="text-xl font-bold text-white">tiket<span class="text-cyan-100">Pantai</span></span>
      </a>
      <div class="hidden md:flex items-center gap-3">
        <a href="index.php" class="tp-nav-link text-sm font-medium px-3 py-1.5"><i class="fa-solid fa-arrow-left mr-1"></i> Beranda</a>
        <?php if ($user): ?>
        <div class="tp-nav-glass flex items-center gap-2 px-3 py-1.5 rounded-full">
          <div class="w-6 h-6 bg-white/30 rounded-full flex items-center justify-center"><span class="text-[10px] font-bold text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span></div>
          <span class="text-sm font-medium"><?= htmlspecialchars($user['name']) ?></span>
        </div>
        <a href="auth/logout.php" class="tp-nav-outline text-sm font-medium px-3 py-1.5 rounded-lg">Keluar</a>
        <?php endif; ?>
      </div>
      <button type="button" data-nav-toggle="tpMobileNav" aria-label="Buka menu" aria-expanded="false" class="md:hidden w-10 h-10 flex items-center justify-center text-white rounded-lg hover:bg-white/15 transition">
        <i data-nav-icon class="fa-solid fa-bars text-lg"></i>
      </button>
    </div>
    <div id="tpMobileNav" class="md:hidden hidden tp-mobile-menu border-t border-white/15">
      <div class="max-w-7xl mx-auto px-4 py-3 space-y-1">
        <a href="index.php" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/15"><i class="fa-solid fa-arrow-left mr-1"></i> Beranda</a>
        <?php if ($user): ?>
        <div class="flex items-center justify-between px-3 py-2 pt-3 mt-2 border-t border-white/15">
          <span class="text-sm font-medium truncate"><i class="fa-solid fa-user mr-1.5"></i><?= htmlspecialchars($user['name']) ?></span>
          <a href="auth/logout.php" class="text-sm bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg">Keluar</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <div class="max-w-5xl mx-auto px-4 py-8">
    <div class="reveal mb-8">
      <h1 class="font-display text-3xl font-extrabold text-gray-900 mb-2">Pesanan Saya</h1>
      <p class="text-sm text-gray-500">Riwayat dan status pemesanan tiket Anda</p>
    </div>

    <?php if (empty($orders)): ?>
    <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
      <i class="fa-solid fa-cart-shopping text-4xl text-gray-300 mb-4"></i>
      <h3 class="font-bold text-gray-900 mb-1">Belum Ada Pesanan</h3>
      <p class="text-sm text-gray-500">Anda belum memiliki riwayat pemesanan tiket.</p>
      <a href="index.php" class="tp-btn tp-btn-gradient inline-block mt-4 text-white px-6 py-2 rounded-xl text-sm font-semibold">Cari Tiket</a>
    </div>
    <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($orders as $order):
        $sc = $statusConfig[$order['status']] ?? $statusConfig['pending'];
        $pi = resolve_payment($order['payment_method'] ?? null, $order['payment_detail'] ?? null);
      ?>
      <div class="reveal tp-card bg-white rounded-3xl shadow-sm p-6 border border-gray-100">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <span class="text-sm font-mono text-gray-400"><?= $order['order_number'] ?></span>
              <span class="<?= $sc['color'] ?> text-[10px] font-semibold px-2 py-0.5 rounded"><i class="fa-solid <?= $sc['icon'] ?> mr-1"></i><?= $sc['label'] ?></span>
            </div>
            <h3 class="font-bold text-gray-900 text-lg mb-1"><?= htmlspecialchars($order['dest_name']) ?></h3>
            <div class="flex flex-wrap gap-4 text-xs text-gray-500">
              <span><i class="fa-regular fa-calendar mr-1"></i><?= date('d F Y', strtotime($order['visit_date'])) ?></span>
              <?php if ($order['payment_method']): ?>
              <span><i class="fa-solid fa-credit-card mr-1"></i><?= htmlspecialchars($order['payment_method']) ?><?= !empty($pi['provider']) ? ' &middot; ' . htmlspecialchars($pi['provider']['name']) : '' ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-right flex flex-col items-end gap-3">
            <div class="text-lg font-bold text-teal-600">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></div>
            <button type="button" onclick="openTicket(this)"
              data-order="<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>"
              data-dest="<?= htmlspecialchars($order['dest_name'], ENT_QUOTES) ?>"
              data-date="<?= htmlspecialchars(date('d F Y', strtotime($order['visit_date'])), ENT_QUOTES) ?>"
              data-total="<?= (int)$order['total_amount'] ?>"
              data-status="<?= htmlspecialchars($sc['label'], ENT_QUOTES) ?>"
              data-statuscls="<?= htmlspecialchars($sc['color'], ENT_QUOTES) ?>"
              data-statusicon="<?= htmlspecialchars($sc['icon'], ENT_QUOTES) ?>"
              data-method="<?= htmlspecialchars($order['payment_method'] ?: '-', ENT_QUOTES) ?>"
              data-items="<?= htmlspecialchars(json_encode(array_map(function ($i) { return ['name' => $i['tt_name'], 'qty' => $i['quantity'], 'sub' => $i['subtotal']]; }, $order['items'])), ENT_QUOTES) ?>"
              class="tp-btn tp-btn-gradient text-white text-xs font-semibold px-4 py-2 rounded-lg inline-flex items-center gap-1.5 shadow-sm">
              <i class="fa-solid fa-ticket"></i> Lihat Tiket
            </button>
          </div>
        </div>
        <?php if (!empty($order['items'])): ?>
        <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-2">
          <?php foreach ($order['items'] as $item): ?>
          <div class="flex justify-between text-xs text-gray-500">
            <span><?= htmlspecialchars($item['tt_name']) ?> x<?= $item['quantity'] ?></span>
            <span class="font-medium text-gray-700">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($order['status'] === 'pending'): ?>
        <div class="mt-4 bg-amber-50 rounded-xl p-3">
          <div class="text-xs font-semibold text-amber-800 mb-2"><i class="fa-solid fa-circle-info mr-1"></i>Instruksi Pembayaran</div>
          <?php if ($pi['type'] === 'bank' && !empty($pi['provider'])): ?>
            <div class="text-xs text-gray-700">
              Transfer ke <strong><?= htmlspecialchars($pi['provider']['name']) ?></strong>:
              <span class="font-mono font-bold text-teal-600"><?= htmlspecialchars($pi['provider']['number']) ?></span>
              <span class="text-gray-500">(a.n. <?= htmlspecialchars($pi['provider']['holder']) ?>)</span>
            </div>
          <?php elseif ($pi['type'] === 'ewallet' && !empty($pi['provider'])): ?>
            <div class="text-xs text-gray-700">
              Kirim ke <strong><?= htmlspecialchars($pi['provider']['name']) ?></strong>:
              <span class="font-mono font-bold text-teal-600"><?= htmlspecialchars($pi['provider']['number']) ?></span>
              <span class="text-gray-500">(a.n. <?= htmlspecialchars($pi['provider']['holder']) ?>)</span>
            </div>
          <?php elseif ($pi['type'] === 'qris'): ?>
            <div class="flex items-center gap-3">
              <img src="<?= htmlspecialchars($pi['image']) ?>" alt="QRIS" class="w-20 h-20 rounded-lg border border-gray-200 bg-white object-contain p-1">
              <span class="text-xs text-gray-700">Scan QRIS untuk membayar.</span>
            </div>
          <?php elseif ($pi['type'] === 'location'): ?>
            <div class="text-xs text-gray-700">Bayar langsung di lokasi destinasi saat kunjungan.</div>
          <?php else: ?>
            <div class="text-xs text-gray-500"><?= htmlspecialchars($order['payment_method'] ?? 'Metode tidak diketahui') ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <!-- Footer -->
  <footer class="bg-[#0b1325] text-[#718096] text-[11px] mt-auto">
    <div class="max-w-7xl mx-auto px-4 py-8 text-center">
      <div class="text-white text-sm font-bold mb-1">tiket<span class="text-teal-400">Pantai</span></div>
      <p>&copy; <?= date('Y') ?> E-Tiketing Paranggupito. Seluruh hak cipta dilindungi.</p>
    </div>
  </footer>

  <!-- Modal E-Tiket -->
  <div id="ticketModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeTicket()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
      <div class="p-5 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center"><i class="fa-solid fa-ticket text-teal-600"></i></div>
        <h3 class="font-display font-bold text-gray-900 flex-1">E-Tiket</h3>
        <button type="button" onclick="closeTicket()" class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <div class="p-5 space-y-3 text-sm">
        <div class="text-center pb-4 border-b border-dashed border-gray-200">
          <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">No. Tiket</div>
          <div class="font-mono text-lg font-bold text-gray-900" id="tk-order"></div>
          <div id="tk-status" class="mt-2"></div>
        </div>
        <div class="flex justify-between gap-4"><span class="text-gray-500">Destinasi</span><span class="font-medium text-right" id="tk-dest"></span></div>
        <div class="flex justify-between"><span class="text-gray-500">Tanggal Kunjungan</span><span class="font-medium" id="tk-date"></span></div>
        <div class="flex justify-between"><span class="text-gray-500">Pembayaran</span><span class="font-medium" id="tk-method"></span></div>
        <div class="pt-3 border-t border-dashed border-gray-200 space-y-2" id="tk-items"></div>
        <div class="pt-3 border-t border-gray-200 flex justify-between items-center">
          <span class="font-bold text-gray-900">Total</span>
          <span class="font-display text-xl font-extrabold text-teal-600" id="tk-total"></span>
        </div>
        <button type="button" onclick="closeTicket()" class="tp-btn tp-btn-gradient w-full text-white text-sm font-semibold py-2.5 rounded-xl mt-2">Tutup</button>
      </div>
    </div>
  </div>

  <script>
    function escapeHtml(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
    function openTicket(btn) {
      var d = btn.dataset;
      document.getElementById('tk-order').textContent = d.order;
      document.getElementById('tk-dest').textContent = d.dest;
      document.getElementById('tk-date').textContent = d.date;
      document.getElementById('tk-method').textContent = d.method;
      document.getElementById('tk-total').textContent = 'Rp ' + Number(d.total).toLocaleString('id-ID');
      document.getElementById('tk-status').innerHTML = '<span class="tp-chip ' + d.statuscls + '"><i class="fa-solid ' + d.statusicon + ' mr-1"></i>' + escapeHtml(d.status) + '</span>';
      var items = [];
      try { items = JSON.parse(d.items); } catch (e) { items = []; }
      var html = items.map(function (i) {
        return '<div class="flex justify-between text-xs"><span class="text-gray-500">' + escapeHtml(i.name) + ' ×' + i.qty + '</span><span class="font-medium text-gray-700">Rp ' + Number(i.sub).toLocaleString('id-ID') + '</span></div>';
      }).join('');
      if (!html) html = '<div class="text-xs text-gray-400 text-center py-1">Tidak ada item</div>';
      document.getElementById('tk-items').innerHTML = html;
      document.getElementById('ticketModal').classList.remove('hidden');
    }
    function closeTicket() { document.getElementById('ticketModal').classList.add('hidden'); }
  </script>
  <script src="assets/app.js"></script>
</body>
</html>
