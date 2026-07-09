<?php
session_start();
$pdo = require __DIR__ . '/../db.php';
$user = $_SESSION['user'] ?? null;

// Helper metode pembayaran (bank, e-wallet, QRIS)
require __DIR__ . '/../payments.php';

// Prefix path asset: admin/ berada 1 level di bawah root project, jadi
// path relatif (uploads/..., assets/..., beaches/...) butuh prefix '../'
$ASSET = '../';

if (!$user || $user['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Stats
$totalUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalOrders = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$revenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status IN ('paid', 'completed')")->fetchColumn();
$activeDest = $pdo->query('SELECT COUNT(*) FROM destinations WHERE is_active = TRUE')->fetchColumn();

// Orders by status
$statusData = $pdo->query('SELECT status, COUNT(*) as cnt FROM orders GROUP BY status')->fetchAll();

// All orders
$orders = $pdo->query('
    SELECT o.*, d.name as dest_name, u.name as user_name
    FROM orders o
    JOIN destinations d ON o.destination_id = d.id
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
')->fetchAll();

// All users
$users = $pdo->query('SELECT id, email, name, role, phone, created_at FROM users ORDER BY created_at DESC')->fetchAll();

// Destinations (serta jumlah tiket/fasilitas per destinasi)
$destinations = $pdo->query('
    SELECT d.*, (SELECT COUNT(*) FROM ticket_types t WHERE t.destination_id = d.id) AS ticket_count
    FROM destinations d
    ORDER BY d.created_at DESC
')->fetchAll();

// Siapkan data destinasi untuk form edit (modal) di sisi JS
$destJson = [];
foreach ($destinations as $d) {
    $destJson[] = [
        'id'          => (int)$d['id'],
        'name'        => $d['name'],
        'image'       => $d['image'],
        'location'    => $d['location'],
        'rating'      => (float)$d['rating'],
        'open_hours'  => $d['open_hours'],
        'price'       => (int)$d['price'],
        'description' => $d['description'],
        'category'    => $d['category'],
        'is_popular'  => (bool)$d['is_popular'],
        'is_active'   => (bool)$d['is_active'],
    ];
}

// Peta pesan notifikasi (?msg=...)
$messages = [
    'dest_created'     => ['type' => 'success', 'text' => 'Destinasi baru berhasil ditambahkan.'],
    'order_deleted'    => ['type' => 'success', 'text' => 'Pesanan berhasil dihapus. Total pendapatan diperbarui otomatis.'],
    'dest_updated'     => ['type' => 'success', 'text' => 'Destinasi berhasil diperbarui.'],
    'dest_deleted'     => ['type' => 'success', 'text' => 'Destinasi berhasil dihapus.'],
    'dest_toggled'     => ['type' => 'success', 'text' => 'Status destinasi diubah.'],
    'dest_invalid'     => ['type' => 'error',   'text' => 'Data destinasi tidak lengkap (nama wajib diisi).'],
    'dest_img_invalid' => ['type' => 'error',   'text' => 'Gambar tidak valid. Gunakan JPG, PNG, atau WEBP.'],
    'dest_in_use'      => ['type' => 'error',   'text' => 'Destinasi masih digunakan data lain sehingga tidak bisa dihapus.'],
    'dest_active'      => ['type' => 'error',   'text' => 'Destinasi tidak bisa dihapus karena masih ada pesanan aktif (Menunggu/Dibayar). Ubah status pesanan menjadi Selesai atau Dibatalkan dahulu.'],
    'dest_notfound'    => ['type' => 'error',   'text' => 'Destinasi tidak ditemukan.'],
    'dest_error'       => ['type' => 'error',   'text' => 'Terjadi kesalahan saat menyimpan destinasi.'],
];

$statusConfig = [
    'pending' => ['color' => 'bg-amber-100 text-amber-700', 'label' => 'Menunggu', 'icon' => 'fa-clock'],
    'paid' => ['color' => 'bg-teal-100 text-teal-700', 'label' => 'Dibayar', 'icon' => 'fa-circle-check'],
    'completed' => ['color' => 'bg-emerald-100 text-emerald-700', 'label' => 'Selesai', 'icon' => 'fa-circle-check'],
    'cancelled' => ['color' => 'bg-red-100 text-red-700', 'label' => 'Dibatalkan', 'icon' => 'fa-circle-xmark'],
];

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['new_status'];
    if (in_array($newStatus, ['pending', 'paid', 'completed', 'cancelled'])) {
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $orderId]);
        header('Location: index.php?updated=1');
        exit;
    }
}

// Handle order deletion (item pesanan ter-cascade otomatis; pendapatan dihitung ulang saat reload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $orderId = (int)$_POST['order_id'];
    $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    header('Location: index.php?msg=order_deleted');
    exit;
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - TiketPantai</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../assets/app.css">
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

<body class="tp-page-bg min-h-screen flex flex-col">
  <!-- Navbar -->
  <nav class="tp-nav tp-navbar sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
      <a href="../index.php" class="flex items-center gap-2 hover:opacity-80 transition">
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center"><i
            class="fa-solid fa-water text-white text-base"></i></div>
        <span class="text-xl font-bold text-white">tiket<span class="text-cyan-100">Pantai</span></span>
      </a>
      <div class="hidden md:flex items-center gap-2 sm:gap-3">
        <a href="../index.php" class="tp-nav-link text-sm font-medium px-3 py-1.5"><i
            class="fa-solid fa-arrow-left mr-1"></i> Kembali</a>
        <div class="tp-nav-glass flex items-center gap-2 px-3 py-1.5 rounded-full">
          <div class="w-6 h-6 bg-white/30 rounded-full flex items-center justify-center"><span
              class="text-[10px] font-bold text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span></div>
          <span class="text-sm font-medium max-w-[120px] truncate"><?= htmlspecialchars($user['name']) ?></span>
          <span class="bg-amber-400 text-amber-900 text-[9px] px-1.5 py-0.5 rounded font-bold">ADMIN</span>
        </div>
        <a href="../auth/logout.php" class="tp-nav-outline text-sm font-medium px-3 py-1.5 rounded-lg">Keluar</a>
      </div>
      <button type="button" data-nav-toggle="tpMobileNav" aria-label="Buka menu" aria-expanded="false"
        class="md:hidden w-10 h-10 flex items-center justify-center text-white rounded-lg hover:bg-white/15 transition">
        <i data-nav-icon class="fa-solid fa-bars text-lg"></i>
      </button>
    </div>
    <div id="tpMobileNav" class="md:hidden hidden tp-mobile-menu border-t border-white/15">
      <div class="max-w-7xl mx-auto px-4 py-3 space-y-1">
        <a href="../index.php" class="block px-3 py-2 rounded-lg text-sm font-medium hover:bg-white/15"><i
            class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Situs</a>
        <div class="flex items-center justify-between px-3 py-2 pt-3 mt-2 border-t border-white/15">
          <span class="text-sm font-medium truncate"><i
              class="fa-solid fa-user mr-1.5"></i><?= htmlspecialchars($user['name']) ?></span>
          <a href="../auth/logout.php" class="text-sm bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg">Keluar</a>
        </div>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white/80 border border-gray-200 shadow-xl shadow-gray-200/40 rounded-[2rem] backdrop-blur-xl p-6">
      <div class="reveal flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
          <span class="inline-flex items-center gap-1.5 text-teal-600 text-xs font-bold tracking-wider mb-2"><i
              class="fa-solid fa-shield-halved"></i> PANEL ADMIN</span>
          <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-gray-900">Dashboard</h1>
          <p class="text-sm text-gray-500 mt-1">Kelola destinasi, pesanan, dan   pengguna</p>
        </div>
      </div>

      <?php if (isset($_GET['updated'])): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl mb-6">
        <i class="fa-solid fa-check mr-1"></i> Status pesanan berhasil diperbarui!
      </div>
      <?php endif; ?>
      <?php
    $msgCode = $_GET['msg'] ?? '';
    if ($msgCode !== '' && isset($messages[$msgCode])):
        $m = $messages[$msgCode];
        $bg = $m['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700';
        $icon = $m['type'] === 'success' ? 'fa-check' : 'fa-circle-exclamation';
    ?>
      <div class="<?= $bg ?> border text-sm px-4 py-3 rounded-xl mb-6">
        <i class="fa-solid <?= $icon ?> mr-1"></i> <?= htmlspecialchars($m['text']) ?>
      </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-5 mb-8">
        <div class="reveal tp-stat-grad tp-stat-grad--teal p-5">
          <div class="relative flex items-center justify-between mb-3">
            <span class="text-[11px] font-semibold text-white/85 uppercase tracking-wide">Total Pengguna</span>
            <div class="tp-stat-icon w-10 h-10 rounded-xl flex items-center justify-center"><i
                class="fa-solid fa-users"></i></div>
          </div>
          <div class="relative font-display text-2xl sm:text-3xl font-extrabold"><?= $totalUsers ?></div>
          <div class="relative text-[11px] text-white/80 mt-0.5">Pengguna terdaftar</div>
        </div>
        <div class="reveal tp-stat-grad tp-stat-grad--e p-5" style="--reveal-delay:80ms">
          <div class="relative flex items-center justify-between mb-3">
            <span class="text-[11px] font-semibold text-white/85 uppercase tracking-wide">Total Pesanan</span>
            <div class="tp-stat-icon w-10 h-10 rounded-xl flex items-center justify-center"><i
                class="fa-solid fa-cart-shopping"></i></div>
          </div>
          <div class="relative font-display text-2xl sm:text-3xl font-extrabold"><?= $totalOrders ?></div>
          <div class="relative text-[11px] text-white/80 mt-0.5">Transaksi masuk</div>
        </div>
        <div class="reveal tp-stat-grad tp-stat-grad--emerald p-5" style="--reveal-delay:160ms">
          <div class="relative flex items-center justify-between mb-3">
            <span class="text-[11px] font-semibold text-white/85 uppercase tracking-wide">Total Pendapatan</span>
            <div class="tp-stat-icon w-10 h-10 rounded-xl flex items-center justify-center"><i
                class="fa-solid fa-money-bill-wave"></i></div>
          </div>
          <div class="relative font-display text-2xl sm:text-3xl font-extrabold">Rp
            <?= number_format($revenue, 0, ',', '.') ?></div>
          <div class="relative text-[11px] text-white/80 mt-0.5">Dari pesanan dibayar/selesai</div>
        </div>
        <div class="reveal tp-stat-grad tp-stat-grad--rose p-5" style="--reveal-delay:240ms">
          <div class="relative flex items-center justify-between mb-3">
            <span class="text-[11px] font-semibold text-white/85 uppercase tracking-wide">Destinasi Aktif</span>
            <div class="tp-stat-icon w-10 h-10 rounded-xl flex items-center justify-center"><i
                class="fa-solid fa-location-dot"></i></div>
          </div>
          <div class="relative font-display text-2xl sm:text-3xl font-extrabold"><?= $activeDest ?></div>
          <div class="relative text-[11px] text-white/80 mt-0.5">Tersedia untuk dipesan</div>
        </div>
      </div>

      <!-- Orders Management -->
      <div class="reveal bg-white rounded-2xl shadow-md overflow-hidden mb-8">
        <div class="tp-section-head p-6 border-b border-gray-100 flex items-center gap-3">
          <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center"><i
              class="fa-solid fa-cart-shopping text-teal-600"></i></div>
          <h2 class="font-display text-lg font-bold text-gray-900">Manajemen Pesanan</h2>
        </div>
        <div class="overflow-auto tp-table-scroll">
          <table class="tp-table w-full text-sm min-w-[720px]">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">No. Pesanan</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Destinasi</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Pengguna</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Total</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Pembayaran</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($orders as $order):
              $sc = $statusConfig[$order['status']] ?? $statusConfig['pending'];
              $pi = resolve_payment($order['payment_method'] ?? null, $order['payment_detail'] ?? null);
            ?>
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-mono text-xs"><?= $order['order_number'] ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($order['dest_name']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($order['user_name']) ?></td>
                <td class="px-6 py-4 font-medium">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                <td class="px-6 py-4 text-xs text-gray-600">
                  <?= htmlspecialchars($order['payment_method'] ?: '-') ?>
                  <?php if (!empty($pi['provider'])): ?>
                  <span class="block text-[10px] text-gray-400"><?= htmlspecialchars($pi['provider']['name']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4"><span class="tp-chip <?= $sc['color'] ?>"><i
                      class="fa-solid <?= $sc['icon'] ?>"></i><?= $sc['label'] ?></span></td>
                <td class="px-6 py-4">
                  <button type="button" onclick="openOrderDetail(this)"
                    data-order="<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>"
                    data-dest="<?= htmlspecialchars($order['dest_name'], ENT_QUOTES) ?>"
                    data-user="<?= htmlspecialchars($order['user_name'], ENT_QUOTES) ?>"
                    data-total="<?= (int)$order['total_amount'] ?>"
                    data-status="<?= htmlspecialchars($sc['label'], ENT_QUOTES) ?>"
                    data-method="<?= htmlspecialchars($order['payment_method'] ?? '', ENT_QUOTES) ?>"
                    data-detail="<?= htmlspecialchars($order['payment_detail'] ?? '', ENT_QUOTES) ?>"
                    class="tp-btn-soft mb-1.5">
                    <i class="fa-solid fa-eye"></i> Detail
                  </button>
                  <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <select name="new_status"
                      class="text-xs border rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-teal-500">
                      <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                      <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>Dibayar</option>
                      <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Selesai
                      </option>
                      <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Dibatalkan
                      </option>
                    </select>
                    <button type="submit" name="update_status"
                      class="tp-btn tp-btn-gradient text-white text-xs px-3 py-1.5 rounded-lg">Update</button>
                  </form>
                  <form method="POST" class="inline-block mt-1"
                    onsubmit="return confirm('Hapus pesanan ini? Pendapatan akan diperbarui otomatis.');">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" name="delete_order" value="1" class="tp-btn-soft tp-btn-soft--danger"
                      title="Hapus pesanan">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Users -->
      <div class="reveal bg-white rounded-2xl shadow-md overflow-hidden mb-8">
        <div class="tp-section-head p-6 border-b border-gray-100 flex items-center gap-3">
          <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center"><i
              class="fa-solid fa-users text-teal-600"></i></div>
          <h2 class="font-display text-lg font-bold text-gray-900">Pengguna</h2>
        </div>
        <div class="overflow-auto tp-table-scroll">
          <table class="tp-table w-full text-sm min-w-[720px]">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Nama</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Email</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Role</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($users as $u): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-3"><?= htmlspecialchars($u['name']) ?></td>
                <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars($u['email']) ?></td>
                <td class="px-6 py-3">
                  <span
                    class="tp-chip <?= $u['role'] === 'admin' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' ?>">
                    <i class="fa-solid <?= $u['role'] === 'admin' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
                    <?= $u['role'] === 'admin' ? 'Admin' : 'User' ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Destinasi (CRUD) -->
      <div class="reveal bg-white rounded-2xl shadow-md overflow-hidden mb-8">
        <div class="tp-section-head p-6 border-b border-gray-100 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center"><i
                class="fa-solid fa-location-dot text-teal-600"></i></div>
            <h2 class="font-display text-lg font-bold text-gray-900">Destinasi</h2>
          </div>
          <button onclick="openDestForm(null)"
            class="tp-btn tp-btn-gradient text-white text-sm font-semibold px-4 py-2 rounded-xl flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-plus"></i> Tambah Destinasi
          </button>
        </div>
        <div class="overflow-auto tp-table-scroll">
          <table class="tp-table w-full text-sm min-w-[720px]">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Gambar</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Nama</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Alamat</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Rating</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Jam Operasional</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Harga</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($destinations as $d): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-3">
                  <img src="<?= $ASSET . htmlspecialchars($d['image'] ?: 'assets/no-image.svg') ?>"
                    alt="<?= htmlspecialchars($d['name']) ?>"
                    class="w-14 h-14 rounded-lg object-cover border border-gray-200">
                </td>
                <td class="px-6 py-3 font-medium"><?= htmlspecialchars($d['name']) ?></td>
                <td class="px-6 py-3 text-xs text-gray-500 max-w-[220px] truncate"
                  title="<?= htmlspecialchars($d['location'] ?? '') ?>"><?= htmlspecialchars($d['location'] ?: '-') ?>
                </td>
                <td class="px-6 py-3 text-xs"><?= htmlspecialchars($d['rating']) ?> <i
                    class="fa-solid fa-star text-amber-400"></i></td>
                <td class="px-6 py-3 text-xs text-gray-500"><?= htmlspecialchars($d['open_hours'] ?: '-') ?></td>
                <td class="px-6 py-3">Rp <?= number_format($d['price'], 0, ',', '.') ?></td>
                <td class="px-6 py-3">
                  <span
                    class="tp-chip <?= $d['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                    <span
                      class="w-1.5 h-1.5 rounded-full <?= $d['is_active'] ? 'bg-emerald-500' : 'bg-gray-400' ?>"></span>
                    <?= $d['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                  </span>
                </td>
                <td class="px-6 py-3">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <a href="tickets.php?destination_id=<?= (int)$d['id'] ?>"
                      class="text-xs border border-teal-200 bg-teal-50 text-teal-700 px-2.5 py-1.5 rounded-lg hover:bg-teal-100 inline-flex items-center gap-1"
                      title="Kelola tiket & fasilitas">
                      <i class="fa-solid fa-ticket"></i> <?= (int)$d['ticket_count'] ?>
                    </a>
                    <button onclick="openDestForm(<?= (int)$d['id'] ?>)" class="tp-btn-soft" title="Edit">
                      <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" action="destination_save.php" class="inline">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                      <button type="submit" class="tp-btn-soft" title="Aktif/Nonaktif">
                        <i class="fa-solid fa-power-off"></i>
                      </button>
                    </form>
                    <button
                      onclick="confirmDelete(<?= (int)$d['id'] ?>, '<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')"
                      class="tp-btn-soft tp-btn-soft--danger" title="Hapus">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Footer -->
  </div>
  <footer class="bg-[#0b1325] text-[#718096] text-[11px] mt-auto">
    <div class="max-w-7xl mx-auto px-4 py-8 text-center">
      &copy; <?= date('Y') ?> E-Tiketing Paranggupito. Seluruh hak cipta dilindungi.
    </div>
  </footer>

  <!-- Form hapus destinasi (disubmit via JS) -->
  <form id="deleteForm" method="POST" action="destination_save.php" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId" value="">
  </form>

  <!-- Modal Detail Pesanan -->
  <div id="orderModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeOrderModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
      <div class="p-5 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center"><i
            class="fa-solid fa-receipt text-teal-600"></i></div>
        <h3 class="font-display font-bold text-gray-900 flex-1">Detail Pesanan</h3>
        <button type="button" onclick="closeOrderModal()"
          class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition"><i
            class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <div class="p-5 space-y-3 text-sm">
        <div class="flex justify-between"><span class="text-gray-500">No. Pesanan</span><span
            class="font-mono font-medium" id="om-order"></span></div>
        <div class="flex justify-between"><span class="text-gray-500">Destinasi</span><span class="font-medium"
            id="om-dest"></span></div>
        <div class="flex justify-between"><span class="text-gray-500">Pengguna</span><span class="font-medium"
            id="om-user"></span></div>
        <div class="flex justify-between"><span class="text-gray-500">Total</span><span class="font-bold text-teal-600"
            id="om-total"></span></div>
        <div class="flex justify-between items-center"><span class="text-gray-500">Status</span><span
            id="om-status"></span></div>
        <hr>
        <div>
          <div class="text-gray-500 mb-2 text-xs font-medium">Instruksi Pembayaran</div>
          <div class="border border-gray-100 rounded-xl p-4 bg-gray-50" id="om-payment"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Tambah/Edit Destinasi -->
  <div id="destModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeDestModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <div class="p-5 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white rounded-t-2xl">
        <h3 class="font-display font-bold text-gray-900" id="destModalTitle"><i
            class="fa-solid fa-location-dot mr-2 text-teal-500"></i>Tambah Destinasi</h3>
        <button type="button" onclick="closeDestModal()"
          class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition"><i
            class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <form id="destForm" method="POST" action="destination_save.php" enctype="multipart/form-data"
        class="p-5 space-y-4">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id" value="">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Nama Destinasi *</label>
            <input type="text" name="name" required
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Gambar</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" onchange="previewDestImage(this)"
              class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-teal-50 file:text-teal-700">
            <img id="destImgPreview" src="" alt="preview"
              class="hidden mt-2 w-24 h-24 rounded-lg object-cover border border-gray-200">
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Alamat</label>
            <textarea name="location" rows="2"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1 font-medium">Rating (0-5)</label>
            <input type="number" name="rating" step="0.1" min="0" max="5" value="0"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1 font-medium">Harga (Rp)</label>
            <input type="number" name="price" min="0" value="0"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Jam Operasional</label>
            <input type="text" name="open_hours" placeholder="contoh: 08:00 - 17:00"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1 font-medium">Kategori</label>
            <input type="text" name="category" value="Obyek Wisata"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
          </div>
          <div class="flex items-end gap-4 pb-1">
            <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="is_popular"
                value="1" class="rounded"> Populer</label>
            <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="is_active"
                value="1" class="rounded"> Aktif</label>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Deskripsi</label>
            <textarea name="description" rows="3"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
          <button type="button" onclick="closeDestModal()"
            class="border border-gray-200 text-gray-600 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-gray-50">Batal</button>
          <button type="submit"
            class="tp-btn tp-btn-gradient text-white px-4 py-2 rounded-xl text-sm font-semibold">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  const ASSET = '<?php echo $ASSET; ?>';
  const PAYMENTS = <?php echo json_encode(get_payments(), JSON_UNESCAPED_SLASHES); ?>;
  const DESTINATIONS = <?php echo json_encode($destJson, JSON_UNESCAPED_SLASHES); ?>;

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      } [c];
    });
  }

  // ---------- Modal Detail Pesanan ----------
  function openOrderDetail(btn) {
    const d = btn.dataset;
    document.getElementById('om-order').textContent = d.order;
    document.getElementById('om-dest').textContent = d.dest;
    document.getElementById('om-user').textContent = d.user;
    document.getElementById('om-total').textContent = 'Rp ' + Number(d.total).toLocaleString('id-ID');
    document.getElementById('om-status').innerHTML = '<span class="tp-chip bg-gray-100 text-gray-600">' + escapeHtml(d
      .status) + '</span>';
    document.getElementById('om-payment').innerHTML = buildPaymentInfo(d.method, d.detail);
    document.getElementById('orderModal').classList.remove('hidden');
  }

  function closeOrderModal() {
    document.getElementById('orderModal').classList.add('hidden');
  }

  function buildPaymentInfo(method, detail) {
    let groupKey = null;
    for (const [k, g] of Object.entries(PAYMENTS.groups)) {
      if (g.label === method) {
        groupKey = k;
        break;
      }
    }
    if (!groupKey) return '<p class="text-xs text-gray-500 text-center">Metode pembayaran tidak diketahui.</p>';
    if (groupKey === 'bank' || groupKey === 'ewallet') {
      const list = PAYMENTS[groupKey] || [];
      const p = list.find(function(x) {
        return x.key === detail;
      });
      if (!p) return '<p class="text-xs text-gray-500 text-center">' + escapeHtml(method) + '</p>';
      const label = groupKey === 'bank' ? 'Transfer ke rekening' : 'Kirim ke';
      return '<div class="text-center">' +
        '<div class="text-xs text-gray-400">' + label + '</div>' +
        '<div class="font-bold text-gray-900">' + escapeHtml(p.name) + '</div>' +
        '<div class="font-mono text-xl font-bold text-teal-600 my-1.5 tracking-wider">' + escapeHtml(p.number) +
        '</div>' +
        '<div class="text-xs text-gray-500">a.n. ' + escapeHtml(p.holder) + '</div></div>';
    }
    if (groupKey === 'qris') {
      return '<div class="text-center"><img src="' + ASSET + PAYMENTS.qris.image +
        '" alt="QRIS" class="w-40 h-40 mx-auto rounded-xl border border-gray-200 object-contain p-2 bg-white"><p class="text-xs text-gray-500 mt-2">Scan QRIS untuk membayar</p></div>';
    }
    if (groupKey === 'location') {
      return '<p class="text-xs text-gray-600 text-center">Pembayaran dilakukan langsung di lokasi destinasi saat kunjungan.</p>';
    }
    return '';
  }

  // ---------- Modal Tambah/Edit Destinasi ----------
  // Gunakan elements.namedItem karena 'name','action','id' adalah properti form bawaan.
  function field(form, name) {
    return form.elements.namedItem(name);
  }

  function openDestForm(id) {
    const form = document.getElementById('destForm');
    form.reset();
    const prev = document.getElementById('destImgPreview');
    prev.classList.add('hidden');
    prev.src = '';

    if (id) {
      const d = DESTINATIONS.find(function(x) {
        return x.id === id;
      });
      if (!d) return;
      field(form, 'action').value = 'update';
      field(form, 'id').value = d.id;
      field(form, 'name').value = d.name;
      field(form, 'location').value = d.location || '';
      field(form, 'rating').value = d.rating;
      field(form, 'price').value = d.price;
      field(form, 'open_hours').value = d.open_hours || '';
      field(form, 'category').value = d.category || 'Obyek Wisata';
      field(form, 'description').value = d.description || '';
      field(form, 'is_popular').checked = !!d.is_popular;
      field(form, 'is_active').checked = !!d.is_active;
      prev.src = ASSET + (d.image || 'assets/no-image.svg');
      prev.classList.remove('hidden');
      document.getElementById('destModalTitle').innerHTML =
        '<i class="fa-solid fa-pen mr-2 text-teal-500"></i>Edit Destinasi';
    } else {
      field(form, 'action').value = 'create';
      field(form, 'id').value = '';
      field(form, 'is_active').checked = true;
      field(form, 'category').value = 'Obyek Wisata';
      document.getElementById('destModalTitle').innerHTML =
        '<i class="fa-solid fa-plus mr-2 text-teal-500"></i>Tambah Destinasi';
    }
    document.getElementById('destModal').classList.remove('hidden');
  }

  function closeDestModal() {
    document.getElementById('destModal').classList.add('hidden');
  }

  function previewDestImage(input) {
    const prev = document.getElementById('destImgPreview');
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        prev.src = e.target.result;
        prev.classList.remove('hidden');
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  function confirmDelete(id, name) {
    if (confirm('Hapus destinasi "' + name + '"?\nTindakan ini tidak bisa dibatalkan.')) {
      document.getElementById('deleteId').value = id;
      document.getElementById('deleteForm').submit();
    }
  }
  </script>
  <script src="../assets/app.js"></script>
</body>

</html>