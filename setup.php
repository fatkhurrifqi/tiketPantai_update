<?php
// =============================================
// Setup sekali-jalan untuk deploy Railway (aman dipakai lokal juga)
// Buka di browser: https://<domain>/setup.php
// - Import skema + data awal dari database_railway.sql
// - Buat akun admin & user demo (sama seperti seed.php)
// - Hapus dirinya sendiri setelah selesai
// =============================================
$pdo = require __DIR__ . '/db.php';

echo '<pre>';

// ---- 1. Import skema + data awal ----
$sql = file_get_contents(__DIR__ . '/database_railway.sql');
if ($sql === false) {
    die('❌ database_railway.sql tidak ditemukan.');
}

try {
    $pdo->exec($sql);
    echo "✅ Skema & data awal berhasil di-import (CREATE TABLE IF NOT EXISTS — aman dijalankan ulang).\n";
} catch (PDOException $e) {
    die('❌ Import gagal: ' . htmlspecialchars($e->getMessage()) . "\n");
}

// ---- 2. Seed akun admin & user demo ----
$users = [
    ['email' => 'admin@tiketpantai.com', 'password' => 'admin123', 'name' => 'Admin TiketPantai', 'role' => 'admin', 'phone' => '0857-2826-9876'],
    ['email' => 'user@example.com', 'password' => 'user123', 'name' => 'Budi Santoso', 'role' => 'user', 'phone' => '0812-9476-1810'],
];

foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT IGNORE INTO users (email, password_hash, name, role, phone) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$u['email'], $hash, $u['name'], $u['role'], $u['phone']]);
    echo "✅ User: {$u['email']} (password: {$u['password']})\n";
}

// ---- 3. Hapus file ini sendiri ----
echo "\n🎉 Setup selesai! File setup.php dihapus otomatis demi keamanan.\n";
echo "Login admin  → /auth/login.php (admin@tiketpantai.com / admin123)\n";
echo "Login user   → /auth/login.php (user@example.com / user123)\n";
@unlink(__FILE__);
