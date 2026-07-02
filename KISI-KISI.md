# KISI-KISI FILE — TiketPantai (E-Ticketing Wisata Pantai)

> Platform pemesanan tiket masuk & sewa fasilitas pantai berbasis **PHP murni + MySQL (PDO)**.
> Setiap file dijelaskan: **Tujuan · Method/Endpoint · Input · Proses/Logika · Output · Tabel DB**.

---

## 📑 DAFTAR ISI

| Kelompok | File |
|---|---|
| **1. Konfigurasi & Database** | `db.php`, `database.sql`, `seed.php` |
| **2. Halaman Publik** | `index.php`, `destinations.php`, `destination.php`, `checkout.php`, `process_order.php`, `orders.php`, `orders_update.php`, `review_save.php`, `payments.php` |
| **3. Autentikasi** | `auth/login.php`, `auth/register.php`, `auth/logout.php` |
| **4. Admin** | `admin/index.php`, `admin/stats.php`, `admin/users.php`, `admin/tickets.php`, `admin/destination_save.php`, `admin/ticket_save.php` |
| **5. Aset** | `assets/app.js`, `assets/app.css` |

---

## 1. KONFIGURASI & DATABASE

### 🔧 `db.php`
- **Tujuan**: Titik tunggal koneksi database (singleton via `require`).
- **Proses**: Membuat instance `PDO` ke MySQL dengan kredensial dari variabel `$DB_HOST`, `$DB_NAME`, `$DB_USER`, `$DB_PASS`.
- **Opsi PDO**: `ERRMODE_EXCEPTION` (lempar exception saat error), `FETCH_ASSOC` (hasil berupa array asosiatif), `EMULATE_PREPARES = false` (prepared statement asli → aman dari SQL injection).
- **Output**: Mengembalikan objek `$pdo` (di-`return`, lalu ditangkap dengan `$pdo = require 'db.php'`).
- **Catatan**: Digunakan oleh **semua** file yang butuh database.

### 🗄️ `database.sql`
- **Tujuan**: Skema + data awal (seed) database.
- **Tabel yang dibuat**:
  | Tabel | Fungsi | FK |
  |---|---|---|
  | `users` | Akun pengguna (role: user/admin) | — |
  | `destinations` | Data destinasi/pantai | — |
  | `ticket_types` | Jenis tiket & fasilitas + harga | → `destinations` (CASCADE) |
  | `orders` | Header pesanan | → `users`, `destinations` |
  | `order_items` | Detail item per pesanan | → `orders` (CASCADE), `ticket_types` |
  | `reviews` | Ulasan (1 user/destinasi) | → `destinations` (CASCADE), `users` (CASCADE) |
- **Seed**: 1 admin, 1 user demo, 3 pantai (Klothok, Sembukan, Karang Payung), 7 jenis tiket per pantai.
- **Constraint unik**: `reviews.uniq_review_per_user` (destination_id + user_id) → satu ulasan per user per destinasi.

### 🌱 `seed.php`
- **Tujuan**: Membuat akun admin & user demo (dijalankan via CLI: `php seed.php`).
- **Proses**: `password_hash()` untuk hash password aman, lalu `INSERT IGNORE` (tidak error jika email sudah ada).
- **Output**: Pesan teks konfirmasi email & password.
- **Akun default**: `admin@tiketpantai.com / admin123` dan `user@example.com / user123`.

---

## 2. HALAMAN PUBLIK

### 🏠 `index.php` (Beranda)
- **Tujuan**: Landing page + daftar destinasi wisata.
- **Logika PHP**: Ambil semua `destinations` (aktif) + `ticket_types` tiap destinasi.
- **Komponen UI**:
  - Navbar dinamis (sesuai status login & role).
  - **Hero slideshow** (background foto pantai autoplay).
  - Kartu destinasi (gambar, lokasi, rating, harga, tombol *Beli Tiket*).
  - Section "Pesona" (keindahan tiap pantai) + footer.
- **Aksi user**: Klik destinasi → `destination.php`, klik *Beli Tiket* → `checkout.php`.

### 🌐 `destinations.php` (API JSON)
- **Tujuan**: Endpoint REST untuk integrasi frontend (mis. Next.js).
- **Method**: `GET` (+ `OPTIONS` untuk CORS).
- **Input (query)**: `?category=...` (opsional).
- **Proses**: Query destinasi aktif + tiketnya, konversi `snake_case → camelCase`.
- **Output**: `{"destinations":[...]}` (JSON).
- **Header**: `Content-Type: application/json`, `Access-Control-Allow-Origin: *`.

### 🏖️ `destination.php` (Detail Destinasi + Ulasan)
- **Tujuan**: Halaman detail satu destinasi beserta sistem ulasan.
- **Input (query)**: `?wisata=<slug>`.
- **Logika**:
  - Cari destinasi by `slug`; jika tidak ada → error.
  - Ambil semua ulasan + nama pemberi ulasan.
  - Ambil ulasan milik user login (untuk pre-fill form edit).
  - Fungsi `render_stars()` → bintang visual read-only.
- **Fitur ulasan**: Form bintang interaktif (1–5) + textarea → submit ke `review_save.php`.
- **Notifikasi**: `?review_ok=1` (sukses) / `?review_error=...` (gagal).

### 🛒 `checkout.php` (Halaman Checkout)
- **Tujuan**: Memilih jenis & jumlah tiket, tanggal kunjungan, dan metode pembayaran.
- **Input (query)**: `?wisata=<slug>`.
- **Logika**: Ambil destinasi by slug + semua `ticket_types`-nya + konfigurasi pembayaran dari `payments.php`.
- **Interaksi JS (kunci)**:
  - `ubahJumlah()` — tambah/kurang qty tiket (update hidden input `qty[id]`).
  - `updateSummary()` — hitung total real-time + validasi.
  - `pilihMetode()` / `pilihProvider()` — pilih kelompok bayar (Bank/E-Wallet/QRIS/Lokasi) + provider spesifik.
- **Validasi**: Tombol submit disabled sampai ada item + pembayaran lengkap.
- **Output**: Form POST ke `process_order.php`.

### ✅ `process_order.php` (Proses Pemesanan)
- **Tujuan**: Validasi & simpan pesanan, lalu tampilkan halaman sukses.
- **Guard**: Wajib login + method POST.
- **Input (POST)**: `destination_id`, `visit_date`, `payment_method`, `payment_detail`, `qty[tt_id]`.
- **Logika**:
  - Validasi tiap item tiket (cek harga asli dari DB, bukan dari client → anti-harga-palsu).
  - Generate nomor pesanan unik: `TP-<timestamp>-<rand5>`.
  - **Transaksi DB** (`beginTransaction`): INSERT `orders` → INSERT tiap `order_items` → `commit` (gagal → `rollBack`).
- **Output**: Halaman "Pesanan Berhasil" berisi ringkasan + instruksi pembayaran dinamis (rekening/e-wallet/QRIS/lokasi) + tombol "Salin Nomor".

### 📋 `orders.php` (Pesanan Saya)
- **Tujuan**: Riwayat pesanan user login + status & instruksi bayar.
- **Guard**: Wajib login.
- **Logika**: Ambil `orders` milik user + item-itemnya + resolve metode pembayaran.
- **Status**: `pending` (Menunggu) / `paid` (Dibayar) / `completed` (Selesai) / `cancelled` (Dibatalkan).
- **Fitur**: Jika status `pending` → tampilkan box instruksi pembayaran (rekening/QRIS/dll).

### 🔁 `orders_update.php` (API Ubah Status)
- **Tujuan**: Endpoint REST mengubah status pesanan.
- **Method**: `PATCH` (+ `OPTIONS`).
- **Input**: `?id=<order_id>` + body JSON `{status}`.
- **Validasi**: Status harus salah satu dari `pending|paid|cancelled|completed`.
- **Output**: JSON `{success:true, status}` atau error 400/404/405.

### ⭐ `review_save.php` (Handler Ulasan)
- **Tujuan**: Upsert ulasan (1 ulasan/user/destinasi) + update agregat rating.
- **Guard**: Wajib login + POST.
- **Input (POST)**: `destination_id`, `rating` (1–5), `comment`.
- **Logika (transaksi)**:
  - Cek apakah user sudah pernah ulas → `oldRating`.
  - **Ulasan baru**: INSERT + `reviews+1` + hitung rata-rata baru.
  - **Edit ulasan**: UPDATE + sesuaikan rata-rata berdasarkan selisih rating.
  - Simpan `destinations.rating` & `destinations.reviews` (denormalisasi untuk performa).
- **Output**: Redirect balik ke `destination.php` dengan notifikasi.

### 💳 `payments.php` (Konfigurasi Pembayaran)
- **Tujuan**: Pusat konfigurasi metode pembayaran (tanpa sentuh DB).
- **Fungsi**:
  - `get_payments()` → array 4 kelompok: **Bank** (BCA/Mandiri/BNI/BRI), **E-Wallet** (GoPay/DANA/ShopeePay/OVO), **QRIS** (path gambar), **Bayar di Lokasi**.
  - `resolve_payment($method, $detail)` → terjemahkan label+key tersimpan menjadi info terstruktur (`type`, `provider`, `image`).
- **Penyimpanan**: `orders.payment_method` = label kelompok; `orders.payment_detail` = key provider (mis. `bca`, `gopay`, `qris`).

---

## 3. AUTENTIKASI

### 🔐 `auth/login.php`
- **Tujuan**: Form & proses login.
- **Input (POST)**: `email`, `password`.
- **Logika**: Cari user by email → `password_verify()` cocokkan hash → simpan data ke `$_SESSION['user']`.
- **Keamanan**: Password tidak pernah disimpan plain; pakai bcrypt (`password_hash` saat register).
- **Output**: Redirect ke `index.php` (sukses) atau tampilkan pesan error.

### 📝 `auth/register.php`
- **Tujuan**: Form & proses registrasi user baru.
- **Input (POST)**: `name`, `email`, `phone`, `password`, `password_confirm`.
- **Validasi**: Semua wajib · email valid · konfirmasi password cocok · min 6 karakter · email belum terdaftar.
- **Logika**: `password_hash()` → INSERT user (role otomatis `user`) → auto-login via session.
- **Output**: Redirect ke `index.php` atau tampilkan error.

### 🚪 `auth/logout.php`
- **Tujuan**: Mengakhiri sesi login.
- **Logika**: Hapus variabel session · hapus cookie session · `session_destroy()`.
- **Output**: Redirect ke `index.php`.

---

## 4. ADMIN (role = admin)

### 🛡️ `admin/index.php` (Dashboard)
- **Tujuan**: Pusat kendali admin: statistik + kelola pesanan/user/destinasi.
- **Guard**: `$user['role'] === 'admin'` (selain itu → redirect login).
- **Statistik**: Total pengguna, total pesanan, total pendapatan (status paid+completed), destinasi aktif.
- **Modul kelola**:
  - **Pesanan**: tabel + dropdown ubah status (POST `update_status`) + modal detail.
  - **Pengguna**: tabel daftar (nama/email/role).
  - **Destinasi (CRUD)**: tabel + tombol Tambah/Edit/Hapus/Aktifkan → form modal → submit ke `destination_save.php`.
- **JS kunci**: `openDestForm()` (isi modal edit via data JSON), `confirmDelete()`, `openOrderDetail()`.

### 📊 `admin/stats.php` (API Statistik)
- **Tujuan**: Endpoint JSON ringkasan dashboard.
- **Method**: `GET`.
- **Output**: `{totalUsers, totalOrders, totalRevenue, ordersByStatus, recentOrders}`.

### 👥 `admin/users.php` (API Daftar User)
- **Tujuan**: Endpoint JSON daftar pengguna.
- **Method**: `GET`.
- **Output**: `{users:[...]}`.

### 🎫 `admin/tickets.php` (Kelola Tiket/Fasilitas)
- **Tujuan**: CRUD jenis tiket & fasilitas per destinasi.
- **Input (query)**: `?destination_id=<id>`.
- **Logika**: Ambil destinasi + daftar `ticket_types`-nya.
- **Fitur**: Tabel tiket + modal Tambah/Edit + tombol Hapus → submit ke `ticket_save.php`.
- **JS**: `openTicketForm()` (pre-fill edit), `confirmDeleteTicket()`.

### 💾 `admin/destination_save.php` (Handler CRUD Destinasi)
- **Tujuan**: Memproses tambah/edit/hapus/toggle-aktif destinasi.
- **Guard**: Admin + POST.
- **Aksi** (`$_POST['action']`): `create` | `update` | `delete` | `toggle_active`.
- **Logika kunci**:
  - `slugify()` → buat slug dari nama.
  - `unique_slug()` → pastikan unik (suffix `-2`, `-3`).
  - `handle_upload()` → upload gambar ke `uploads/destinations/` dengan validasi ekstensi **+ MIME type asli** (anti upload file berbahaya).
- **Penanganan error**: FK constraint 1451 → pesan "tidak bisa dihapus (masih dipakai)".
- **Output**: Redirect ke `index.php?msg=<kode>`.

### 💾 `admin/ticket_save.php` (Handler CRUD Tiket)
- **Tujuan**: Memproses tambah/edit/hapus jenis tiket.
- **Guard**: Admin + POST.
- **Aksi**: `ticket_create` | `ticket_update` | `ticket_delete`.
- **Logika**: INSERT/UPDATE/DELETE pada `ticket_types`.
- **Output**: Redirect ke `tickets.php?destination_id=...&msg=<kode>`.

---

## 5. ASET

### 🎨 `assets/app.css`
- **Tujuan**: Polish global & micro-interactions (di luar utility Tailwind).
- **Isi**: Variabel warna (teal/cyan), custom scrollbar, hero gradient berlapis, **scroll reveal**, hover kartu (angkat + zoom gambar), efek kilau tombol `.tp-btn`, badge glass, animasi slideshow + Ken Burns.
- **Aksesibilitas**: `@media (prefers-reduced-motion: reduce)` → matikan semua animasi.

### ⚙️ `assets/app.js`
- **Tujuan**: Interaksi global dimuat di semua halaman publik.
- **Modul**:
  1. `initReveal()` — animasi muncul saat scroll (IntersectionObserver, fallback jika tidak didukung).
  2. `initNav()` — navbar shadow muncul setelah scroll.
  3. `initSlideshow()` — hero background autoplay (5 dtk) + dot navigasi + jeda saat tab tidak aktif.

---

## 🔁 RINGKASAN ALUR UTAMA

```
Pengunjung → index.php → pilih destinasi → destination.php (detail + ulasan)
                                              ↓ Beli Tiket
                                         checkout.php (pilih tiket + bayar)
                                              ↓ POST
                                         process_order.php (simpan DB → halaman sukses)
                                              ↓
                                         orders.php (riwayat + instruksi bayar)

Admin → admin/index.php → ubah status pesanan (pending→paid→completed)
                        → CRUD destinasi → admin/tickets.php (CRUD tiket)
```

## 🗃️ ERD (Relasi Tabel)
```
users (1) ──< orders >── (1) destinations
                  │               │
                  │               └──< ticket_types
                  │                      ▲
                  └──< order_items >─────┘

reviews ──> destinations  (1 user : 1 destinasi, unik)
reviews ──> users
```
