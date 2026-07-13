# 🍫 KISI-KISI ANALOGI — TiketPantai

> Setiap konsep & file dijelaskan lewat **analogi sehari-hari** + **potongan kode asli**,
> supaya nggak cuma hafal kode, tapi benar-benar paham *kenapa* begitu.
>
> Cara baca tiap bagian: **🍽️ Analogi → 🧩 Pemetaan → 💻 Kode → 💡 Kenapa penting**

---

## BAGIAN 1 — KONSEP DASAR (pondasi yang dipakai di semua file)

### 🍽️ Analogi 1: `db.php` = **Pintu Masuk Satu-satunya ke Gudang**
Bayangkan database itu **gudang penyimpanan**, dan aplikasi adalah orang yang mau ambil/menyimpan barang.
Daripada tiap pegawai bikin pintu sendiri-sendiri (rawan bocor & ribet), dibuat **satu pintu utama** dengan satpam ketat.
Semua file cukup bilang *"pakai pintu itu"* → `require 'db.php'`.

- **Pemetaan**: `db.php` = pintu; `PDO` = satpam ber-siap-siap lempar alarm (`ERRMODE_EXCEPTION`) bila ada penyusup.
- **Kode** (`db.php`):
```php
$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $options);
return $pdo;   // ← dikembalikan ke siapa pun yang 'require'
```
- **Pemakaian di file lain**:
```php
$pdo = require __DIR__ . '/db.php';   // "Aku mau pinjam pintu gudang"
```
- 💡 **Kenapa**: cukup ubah koneksi di **satu tempat** saja, semua file ikut. Nggak ada duplikat.

---

### 🍽️ Analogi 2: Prepared Statement = **Formulir dengan Kotak Kosong**
Bayangkan kamu pesan makan di restoran. Ada 2 cara:
- ❌ **Lama (rawan)**: kamu *teriak* pesanan bebas → "Mau nasi goreng... eh tambah DROP TABLE users". Pelayan bisa ketipu & jalanin perintah aneh (ini **SQL Injection**).
- ✅ **Prepared**: pelayan kasih **formulir cetak** dengan kotak: "Nama Makanan: [____]". Kamu isi kotaknya, dan apapun yang kamu tulis **tetap dianggap teks**, bukan perintah.

- **Pemetaan**: tanda `?` = kotak kosong; `execute([...])` = isi kotaknya.
- **Kode** (`auth/login.php`):
```php
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');  // formulir
$stmt->execute([$email]);   // isi kotak 'email' → apapun input user, aman
```
- 💡 **Kenapa**: walau user ketik `' OR 1=1 --`, itu dibaca sebagai teks biasa, bukan perintah SQL → **anti-hack**.

---

### 🍽️ Analogi 3: Session = **Gelang Wristband Taman Bermain**
Saat masuk taman bermain, kamu tunjuk KTP di loket → dikasih **gelang**. Selama gelangnya nyala, kamu bisa naik wahana mana pun tanpa tunjuk KTP lagi. Pulang → gelang dilepas.
- **Login** = tunjuk KTP → dikasih gelang (simpan `$_SESSION['user']`).
- **Buka halaman lain** = cukup tunjuk gelang, sistem tahu kamu siapa.
- **Logout** = potong gelang (`session_destroy()`).

- **Kode** (`auth/login.php`):
```php
$_SESSION['user'] = ['id'=>..., 'name'=>..., 'role'=>...];   // pasang gelang
```
- **Cek gelang** (`process_order.php`):
```php
$user = $_SESSION['user'] ?? null;   // "ada gelang nggak?"
if (!$user) { header('Location: auth/login.php'); exit; }   // nggak ada → ke loket
```

---

### 🍽️ Analogi 4: `password_hash` = **Blender Sekali Jalan**
Password itu bahan makanan. `password_hash` = **blender** yang menghaluskan jadi smoothie.
- Di database hanya disimpan **smoothie**-nya (hash), bukan bahan asli.
- **Nggak bisa** diblender-balik (un-blend) jadi password asli → itu sebabnya disebut *one-way* (satu arah).
- Saat login: password yang diketik **diblender ulang**, lalu dibandingkan smoothie-nya sama atau tidak (`password_verify`).

- **Kode** (`auth/register.php` — bikin smoothie):
```php
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
```
- **Kode** (`auth/login.php` — cek):
```php
password_verify($password, $found['password_hash'])   // true kalau hasil blender cocok
```
- 💡 **Kenapa**: kalau database bocor, hacker cuma dapat smoothie — nggak bisa tau password aslimu.

---

### 🍽️ Analogi 5: Foreign Key (FK) = **Sistem Tanda-Tangan/KTP**
Bayangkan `order_items` itu daftar belanja. Tiap baris harus nulis **KTP pembeli** dan **nomor menu** (bukan nulis ulang nama lengkap).
- **FK** = aturan "KTP ini harus benar-benar ada di meju user".
- `ON DELETE CASCADE` = kalau pemilik KTP dihapus, semua catatan miliknya ikut hilang (otomatis dibersihkan).

- **Kode** *(ilustrasi skema; relasi lengkap lihat `erd.drawio`)*:
```sql
CREATE TABLE order_items (
  order_id INT NOT NULL,
  ticket_type_id INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
```
> Catatan: di `admin/destination_save.php`, pantai yang **masih punya pesanan aktif** (pending/paid) sengaja **diblok duluan** oleh aplikasi sebelum sampai ke aturan FK ini — supaya penghapusan lebih aman & pesanan yang sedang jalan tidak rusak.

---

### 🍽️ Analogi 6: Slug = **Plat Nomor Cantik (Vanity)**
Daripada alamat `destination.php?id=3` (seperti plat biasa), dibuat `destination.php?wisata=pantai-klothok` (plat cantik).
Mudah dibaca, mudah diingat, ramah Google (SEO).

- **Kode** (`admin/destination_save.php`):
```php
function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);  // "Pantai Klothok!" → "pantai-klothok"
    return trim($text, '-');
}
```

---

### 🍽️ Analogi 7: Transaksi DB = **Sepakat "Semua atau Tidak Sama Sekali"**
Bayangkan transfer uang: **potong saldo A** + **tambah saldo B** harus jadi bareng. Kalau setengah jalan listrik mati, uang hilang! Maka dibungkus "transaksi":
- `beginTransaction()` = mulai, semua perubahan **ditahan dulu** (belum permanen).
- `commit()` = **"sudah lengkap, simpan permanen!"**
- `rollBack()` = **"ada yang gagal, batalkan SEMUA, ulang dari awal."**

- **Kode** (`process_order.php`):
```php
$pdo->beginTransaction();
try {
    // INSERT orders
    // INSERT order_items (banyak baris)
    $pdo->commit();        // ✅ semua sukses → permanen
} catch (Exception $e) {
    $pdo->rollBack();      // ❌ ada error → semua dibatalkan, data nggak setengah-setengah
}
```
- 💡 **Kenapa**: mencegah pesanan **ke-setengah** (ada header, tapi item-nya hilang).

---

## BAGIAN 2 — STRUKTUR DATABASE = **Sekumpulan Laci/Meja**

| Tabel | Analogi | Isi |
|---|---|---|
| `users` | 🗂️ **Laci kartu member** | Data orang yang punya akun |
| `destinations` | 📖 **Katalog brosur pantai** | Tiap pantai 1 brosur |
| `ticket_types` | 🍱 **Menu harga** (per pantai) | Tiket masuk, sewa tenda, dll + harga |
| `orders` | 🧾 **Bukti struk utama** | 1 pesanan = 1 struk (nomor, total, status) |
| `order_items` | 📝 **Rincian di struk** | Baris-baris: "Tenda x2 = Rp20.000" |
| `reviews` | 📒 **Buku tamu** | 1 orang 1 tandatangan per pantai |

**Hubungannya (ERD)** — analogi **keluarga**:
```
1 User  ——punya banyak——>  Orders  ——di satu——>  1 Destinasi
                              │
                              └──punya banyak——> Order_items ——merujuk——> Ticket_types
```

---

## BAGIAN 3 — FILE PER FILE (dengan analogi masing-masing)

### 🏠 `index.php` — **Etalase Toko**
> = pintu depan toko. Orang lewat lihat pajangan (hero + kartu pantai). Klik salah satu → masuk detail.

**Logika**: ambil semua pantai aktif + menu tiketnya, lalu pajang.
```php
$destinations = $pdo->query('SELECT * FROM destinations WHERE is_active = TRUE')->fetchAll();
foreach ($destinations as &$dest) {
    $dest['ticket_types'] = ...;  // sertakan menu tiap pantai
}
```

---

### 🏖️ `destination.php` — **Halaman Brosur + Buku Tamu**
> = halaman detail 1 pantai. Ada foto, deskripsi, **dan buku tamu ulasan** (bisa kasih bintang + komentar).

- Cari pantai lewat **slug** (plat cantik):
```php
$stmt = $pdo->prepare('SELECT * FROM destinations WHERE slug = ? AND is_active = TRUE');
$stmt->execute([$slug]);
```
- **Beri bintang** = interaksi JS:
```js
function setRating(n) {
    currentRating = n;
    document.getElementById('ratingInput').value = n;  // simpan ke input tersembunyi
}
```

---

### 🛒 `checkout.php` — **Keranjang Belanja + Kasir**
> = kamu jalan-jalan pilih tiket (tambah/kurang jumlah), pilih tanggal, pilih cara bayar. **Total dihitung real-time** sambil kamu pilih.

- Tambah/kurang jumlah (di browser dulu, sebelum dikirim):
```js
function ubahJumlah(id, aksi) {
    dataTiket[id].qty += aksi;          // ubah jumlah
    document.getElementById('input-' + id).value = dataTiket[id].qty;  // simpan ke form
    updateSummary();                    // hitung ulang total
}
```
💡 Tapi ingat: perhitungan di browser ini cuma "etiket harga". **Harga asli** baru dipercaya saat dicek ulang di kasir (`process_order.php`).

---

### ✅ `process_order.php` — **Kasir yang Hitung Ulang + Cetak Struk**
> = kasir. Dia **tidak percaya** total yang kamu bawa; dia hitung ulang dari menu asli, baru cetak struk.

- **Hitung ulang harga dari DB** (anti-curang harga):
```php
foreach ($qty as $ttId => $quantity) {
    $stmt = $pdo->prepare('SELECT * FROM ticket_types WHERE id = ? AND destination_id = ?');
    $stmt->execute([$ttId, $destinationId]);
    $tt = $stmt->fetch();
    $subtotal = $tt['price'] * $quantity;   // harga AMBIL DARI DB, bukan dari form
    $totalAmount += $subtotal;
}
```
- Bikin **nomor struk unik**: `TP-<waktu>-<acak5>`.
- Lalu simpan pakai **transaksi** (lihat Analogi 7).

---

### 💳 `payments.php` — **Papan Daftar Metode Bayar di Dinding**
> = poster di kasir yang daftar 4 kelompok: **Transfer Bank** (BCA/SeaBank/BRI), **E-Wallet** (GoPay/DANA/ShopeePay/OVO), **QRIS**, dan **Bayar di Lokasi**. Diganti di **satu papan** saja, semua halaman ikut.

- `get_payments()` = baca papan; `resolve_payment()` = terjemahkan "si user pilih apa" jadi instruksi jelas.
```php
function resolve_payment(?string $method, ?string $detail): array {
    // "Transfer Bank" + "bca"  →  {bank BCA, rekening 1234567890, a.n. TiketPantai}
}
```

---

### 📋 `orders.php` — **Buku Catatan Pesanan Pribadi**
> = buku catatan kamu sendiri. Tiap halaman = 1 pesanan + statusnya + cara bayarnya kalau belum lunas.

- Ambil pesanan milik **user yang sedang login** saja (lewat gelang session):
```php
$stmt = $pdo->prepare('SELECT o.*, d.name ... FROM orders o WHERE o.user_id = ?');
$stmt->execute([$user['id']]);
```

---

### 🔁 `orders_update.php` — **Ganti Label Status di Dapur**
> = di dapur, slip pesanan diganti label: *Menunggu → Diproses → Selesai*. Di sini: `pending → paid → completed`.

```php
$validStatuses = ['pending', 'paid', 'cancelled', 'completed'];
if (!in_array($status, $validStatuses)) { /* tolak */ }
$stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
```

---

### ⭐ `review_save.php` — **Buku Tamu: 1 Orang 1 Tanda Tangan**
> = di buku tamu, tiap tamu boleh **komentar sekali** per pantai, tapi bisa **edit** komentarnya. Sekaligus **rata-rata bintang** dipunggung otomatis dihitung ulang.

- **Upsert** = kalau belum ada → tambah; kalau sudah ada → edit:
```php
if ($oldRating === false) {
    // tamu baru → INSERT + tambah jumlah ulasan + hitung rata-rata baru
    $newRating = (($curRating * $curCount) + $rating) / $newCount;
} else {
    // tamu lama edit → UPDATE + sesuaikan rata-rata (karena nilai bintang berubah)
    $newRating = $curRating + (($rating - $oldRating) / $curCount);
}
```
💡 **Kenapa simpan rating rata-rata di tabel pantai?** = biar pas tampil di etalase nggak usah hitung ulang dari nol (cepat). Ini namanya **denormalisasi**.

---

### 🔐 `auth/login.php` — **Satpam Cek KTP**
> = satpam di gerbang. Kamu kasih email+password; dia cek apakah **blender password** cocok. Cocok → kasih gelang (session).

```php
if ($found && password_verify($password, $found['password_hash'])) {
    $_SESSION['user'] = [...];        // kasih gelang
    header('Location: ../index.php'); // masuk
}
```

### 📝 `auth/register.php` — **Daftar Jadi Member Baru**
> = isi formulir member baru. Divalidasi (email belum dipakai, password cukup kuat), lalu dibuatkan KTP + langsung dikasih gelang (auto-login).

```php
if ($stmt->fetch()) { $errors[] = 'Email sudah terdaftar.'; }  // cek dobel
else {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT); // blender
    $insert->execute([...]);
    $_SESSION['user'] = [...];   // langsung gelang
}
```

### 🚪 `auth/logout.php` — **Potong Gelang & Pulang**
```php
$_SESSION = [];            // kosongkan
session_destroy();         // potong gelang
header('Location: ../index.php');
```

---

### 🛡️ `admin/index.php` — **Ruang Kontrol Manajer**
> = ruang kontrol dengan layar statistik (jumlah user, pesanan, pendapatan) + tombol kendali (ganti status pesanan, kelola pantai).

- **Pintu khusus admin**: cek gelang, harus berlabel "admin".
```php
if (!$user || $user['role'] !== 'admin') {
    header('Location: ../auth/login.php'); exit;  // bukan admin → diusir
}
```

---

### 💾 `admin/destination_save.php` — **Petugas Tambah Brosur Baru**
> = petugas yang: bikin brosur baru / edit / buang / matikan pajangan. Saat tambah, otomatis bikin **plat cantik** (slug) + **cek keamanan foto**.

**Analogi keamanan upload = satpam periksa 2 lapis**:
1. Lihat **label kotak** (ekstensi `.jpg`).
2. Lihat **isi aslinya** (MIME type) — takutnya label ditipu, isinya malware.
```php
if (!in_array($ext, $allowedExt, true)) return null;          // cek label
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedMime, true)) return null;        // cek isi
```

**Proteksi FK** = pantai yang **masih ada pesanan** tak boleh dibuang (nanti struknya jadi yatim):
```php
// error 1451 = FK constraint → "tidak bisa dihapus, masih dipakai"
```

---

### 🎫 `admin/tickets.php` + `ticket_save.php` — **Atur Menu per Pantai**
> = tiap pantai punya menu berbeda (Klothok ada ban renang, Sembukan ada snorkeling). Petugas atur menu tiap pantai.

---

## BAGIAN 4 — KONSEP KEAMANAN (dalam analogi)

| Konsep | Analogi | Efek |
|---|---|---|
| **SQL Injection** | Penyusup nyelipin perintah lewat form | ✅ Diblok **Prepared Statement** |
| **XSS** | Orang nulis "script jahat" di komentar biar jalan saat dibaca | ✅ Diblok `htmlspecialchars()` (anggap teks biasa) |
| **CSRF/Sesi** | Gelang session dibajak | ✅ Dicek tiap aksi sensitif |
| **Password** | Bahan makanan → di-blender | ✅ `password_hash` satu arah |
| **Upload jahat** | Kotak berlabel foto, isinya virus | ✅ Cek ekstensi + MIME |
| **Manipulasi harga** | Customer bohongin total | ✅ Kasir hitung ulang dari DB |

**XSS dicegah dengan escape**:
```php
<?= htmlspecialchars($dest['name']) ?>   // nama ditampilkan apa adanya, <script> jadi teks
```

---

## BAGIAN 5 — ALUR CERITA LENGKAP (kalau dijadiin sketsa)

```
1. 🚶 TAMU DATANG
   index.php (etalase) → klik pantai → destination.php (brosur + buku tamu)

2. 🛒 BELANJA
   klik "Beli Tiket" → checkout.php (isi keranjang + pilih bayar)
        JS hitung total sementara

3. 💰 KE KASIR
   checkout POST → process_order.php
        - cek gelang (login?)
        - hitung ulang harga dari menu asli
        - transaksi: simpan struk + rincian
        - tampilkan halaman "Berhasil" + cara bayar

4. 📒 CATAT
   status awal = pending → muncul di orders.php (buku pribadi)

5. 👑 ADMIN KONFIRMASI
   admin ganti status → paid / completed
```

---

## 🧠 RANGKUMAN: "Satu Kalimat per Konsep"

| Kalau ditanya... | Jawaban analogi |
|---|---|
| `db.php` apa? | Pintu satu-satunya ke gudang data |
| Prepared statement? | Formulir cetak dengan kotak kosong (anti-hack) |
| Session? | Gelang wristband taman bermain |
| `password_hash`? | Blender sekali jalan, nggak bisa dibalik |
| Foreign Key? | KTP yang nunjukkin "ini milik siapa" |
| Transaksi DB? | Sepakat semua-atau-tidak-sama-sekali |
| Slug? | Plat nomor cantik biar gampang ingat |
| `process_order` kenapa hitung ulang? | Kasir nggak percaya total customer |
| `review_save`? | Buku tamu 1 tanda tangan/orang + rata-rata auto |
| XSS/`htmlspecialchars`? | Anggap tulisan jadi teks biasa, bukan perintah |

---

> 📌 **Tip belajar**: tutup kode, coba cerita ulang analoginya pakai kata-kata sendiri.
> Kalau bisa jelasin analoginya → kamu sudah paham idenya, bukan cuma hafal sintaks.
