<?php
// =============================================
// Migrasi sekali-jalan: tambah kolom max_qty ke tabel ticket_types.
// Jalankan di terminal: php migrate_max.php
// Aman dijalankan berulang (cek dulu kolomnya sudah ada/belum).
// =============================================
$pdo = require __DIR__ . '/db.php';

$exists = $pdo->query("SHOW COLUMNS FROM ticket_types LIKE 'max_qty'")->fetch();
if ($exists) {
    echo "✅ Kolom 'max_qty' sudah ada di tabel ticket_types. Tidak ada perubahan.\n";
} else {
    $pdo->exec("ALTER TABLE ticket_types ADD COLUMN max_qty INT NULL DEFAULT NULL");
    echo "✅ Berhasil menambahkan kolom 'max_qty' (INT NULL) ke tabel ticket_types.\n";
}
echo "Selesai.\n";
