<?php
session_start();
$pdo = require __DIR__ . '/../db.php';
$title = 'Login';
$user = $_SESSION['user'] ?? null;
$errors = [];

// Flash pesan sukses (mis. dari halaman register). Sekali pakai lalu dihapus.
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT id, email, password_hash, name, role, phone FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $found = $stmt->fetch();

        if ($found && password_verify($password, $found['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $found['id'],
                'email' => $found['email'],
                'name' => $found['name'],
                'role' => $found['role'],
                'phone' => $found['phone'],
            ];
            header('Location: ../index.php');
            exit;
        } else {
            $errors[] = 'Email atau password salah.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - TiketPantai</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/app.css?v=7">
  <style>
  body { font-family: 'Inter', sans-serif; }
  h1, h2, h3, .font-display { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
  </style>
</head>
<body class="tp-page-bg min-h-screen flex flex-col">
  <nav class="tp-nav tp-navbar sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto px-4 flex items-center h-16">
      <a href="../index.php" class="flex items-center gap-2 hover:opacity-80 transition">
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center"><i class="fa-solid fa-water text-white text-base"></i></div>
        <span class="text-xl font-bold text-white">tiket<span class="text-cyan-100">Pantai</span></span>
      </a>
    </div>
  </nav>

  <main class="flex-1 flex items-center justify-center px-4 py-10">
    <div class="reveal grid grid-cols-1 md:grid-cols-2 max-w-4xl w-full bg-white rounded-3xl shadow-xl overflow-hidden">

      <!-- Branding panel -->
      <div class="tp-hero relative hidden md:flex flex-col justify-between p-10 text-white">
        <div class="relative">
          <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center mb-8"><i class="fa-solid fa-water text-xl"></i></div>
          <h2 class="font-display text-3xl font-extrabold leading-tight mb-3">Liburan ke Pantai<br>Paranggupito?</h2>
          <p class="text-white/85 text-sm leading-relaxed max-w-xs">Pesan tiket masuk &amp; sewa fasilitas secara online — lebih cepat, mudah, dan tanpa antrian.</p>
        </div>
        <ul class="relative space-y-3 text-sm text-white/90">
          <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-cyan-200"></i> Tanpa antrian di lokasi</li>
          <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-cyan-200"></i> Banyak metode pembayaran</li>
          <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-cyan-200"></i> E-tiket instan</li>
        </ul>
      </div>

      <!-- Form -->
      <div class="p-8 sm:p-10">
        <h2 class="font-display text-2xl font-extrabold text-gray-900 mb-1">Selamat Datang</h2>
        <p class="text-sm text-gray-500 mb-6">Masuk untuk mengelola pesanan Anda.</p>

        <?php if ($flash_success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded mb-4">
          <p class="text-green-700 text-sm"><?= htmlspecialchars($flash_success) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($errors): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-3 rounded mb-4">
          <?php foreach ($errors as $e): ?>
          <p class="text-red-700 text-sm"><?= htmlspecialchars($e) ?></p>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($user): ?>
        <div class="text-center">
          <p class="text-gray-600">Anda sudah masuk sebagai <strong><?= htmlspecialchars($user['name']) ?></strong>.</p>
          <a href="logout.php" class="tp-btn inline-block mt-4 bg-gray-200 text-gray-700 px-6 py-2 rounded-xl text-sm font-medium">Keluar</a>
        </div>
        <?php else: ?>
        <form method="post" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
            <input type="email" name="email" required placeholder="nama@email.com" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Password</label>
            <div class="relative">
              <input type="password" name="password" id="password" required placeholder="Masukkan password" class="w-full px-4 py-2.5 pr-11 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500">
              <button type="button" data-toggle-password="password" aria-label="Tampilkan password" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-eye"></i>
              </button>
            </div>
          </div>
          <button type="submit" class="tp-btn tp-btn-gradient w-full text-white py-3 rounded-xl font-semibold text-sm">Masuk</button>
        </form>
        <p class="text-center text-sm text-gray-500 mt-4">Belum punya akun? <a href="register.php" class="text-teal-600 font-semibold hover:underline">Daftar di sini</a></p>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <script src="../assets/app.js"></script>
</body>
</html>
