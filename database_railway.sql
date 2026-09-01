-- ============================================================
-- TiketPantai — Skema Database MySQL (versi Railway)
-- Untuk di-import ke database MySQL Railway (nama DB sudah ditentukan
-- oleh Railway, jadi tanpa CREATE DATABASE / USE)
-- ============================================================


-- ------------------------------------------------------------
-- Tabel: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  phone         VARCHAR(30)   NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: destinations
-- - rating & reviews = kolom aggregate (diperbarui review_save.php)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS destinations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150)  NOT NULL,
  slug        VARCHAR(170)  NOT NULL,
  image       VARCHAR(255)  NULL,
  location    VARCHAR(200)  NULL,
  rating      DECIMAL(3,1)  NOT NULL DEFAULT 0.0,
  reviews     INT UNSIGNED  NOT NULL DEFAULT 0,
  open_hours  VARCHAR(100)  NULL,
  price       INT UNSIGNED  NOT NULL DEFAULT 0,          -- harga mulai (rupiah, tanpa titik)
  description TEXT          NULL,
  category    VARCHAR(80)   NOT NULL DEFAULT 'Obyek Wisata',
  is_popular  TINYINT(1)    NOT NULL DEFAULT 0,
  is_active   TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_destinations_slug (slug),
  KEY idx_destinations_category (category),
  KEY idx_destinations_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: ticket_types
-- - max_qty = batas maksimal pesanan per transaksi (NULL = bebas)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_types (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  destination_id INT UNSIGNED NOT NULL,
  name           VARCHAR(120)  NOT NULL,
  price          INT UNSIGNED  NOT NULL DEFAULT 0,
  unit           VARCHAR(50)   NULL,                    -- mis. 'orang', 'unit', 'hari'
  description    VARCHAR(255)  NULL,
  max_qty        INT UNSIGNED  NULL DEFAULT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ticket_types_destination (destination_id),
  CONSTRAINT fk_ticket_types_destination
    FOREIGN KEY (destination_id) REFERENCES destinations(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: orders
-- - status: pending -> paid -> completed (atau cancelled)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number   VARCHAR(40)   NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  destination_id INT UNSIGNED NOT NULL,
  visit_date     DATE          NOT NULL,
  total_amount   INT UNSIGNED  NOT NULL DEFAULT 0,
  payment_method VARCHAR(50)   NULL,                    -- bank_transfer / ewallet / qris / location
  payment_detail VARCHAR(120)  NULL,
  status         ENUM('pending','paid','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (order_number),
  KEY idx_orders_user (user_id),
  KEY idx_orders_destination (destination_id),
  KEY idx_orders_status (status),
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_orders_destination
    FOREIGN KEY (destination_id) REFERENCES destinations(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: order_items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id       INT UNSIGNED NOT NULL,
  ticket_type_id INT UNSIGNED NOT NULL,
  quantity       INT UNSIGNED NOT NULL DEFAULT 1,
  subtotal       INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  KEY idx_order_items_ticket (ticket_type_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_items_ticket
    FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel: reviews
-- - 1 ulasan per user per destinasi (upsert di review_save.php)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  destination_id INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  rating        TINYINT UNSIGNED NOT NULL DEFAULT 5,    -- 1..5
  comment       TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_destination_user (destination_id, user_id),
  CONSTRAINT fk_reviews_destination
    FOREIGN KEY (destination_id) REFERENCES destinations(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reviews_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATA CONTOH (destinasi + tiket)
-- Akun user/admin dibuat lewat:  php seed.php
-- ============================================================

INSERT INTO destinations (name, slug, image, location, rating, reviews, open_hours, price, description, category, is_popular, is_active) VALUES
('Pantai Kuta',            'pantai-kuta',            'uploads/destinations/dest_6a31d15b5bebd1.85285445.jpg',  'Kuta, Badung, Bali',        4.8, 0, '06.00 - 18.00', 15000, 'Pantai ikonik Bali dengan pasir putih dan ombak favorit peselancar.', 'Pantai', 1, 1),
('Pantai Tanjung Aan',     'pantai-tanjung-aan',     'uploads/destinations/dest_6a31daf5a39521.03436128.jpg',  'Lombok, Nusa Tenggara Barat', 4.7, 0, '07.00 - 17.00', 20000, 'Pasir putih halus berbentuk merica dengan bukit permandian.', 'Pantai', 1, 1),
('Pantai Parangtritis',    'pantai-parangtritis',    'uploads/destinations/dest_6a31efef95cb55.99035807.jpeg', 'Bantul, Yogyakarta',        4.5, 0, '05.30 - 18.30', 10000, 'Pantai legendaris dengan gumuk pasir dan matahari terbenam terbaik.', 'Pantai', 0, 1),
('Pantai Ngrenehan',       'pantai-ngrenehan',       'uploads/destinations/dest_6a31f1b41c39d3.34522722.jpeg', 'Gunungkidul, Yogyakarta',   4.4, 0, '06.00 - 18.00', 8000,  'Teluk kecil asri dengan dermaga nelayan dan ikan segar.', 'Pantai', 0, 1),
('Pantai Ngobaran',        'pantai-ngobaran',        'uploads/destinations/dest_6a31f23a398710.16260328.jpeg', 'Gunungkidul, Yogyakarta',   4.5, 0, '06.00 - 18.00', 10000, 'Pantai berkarang dengan pura unik dan budaya lokan bakar.', 'Pantai', 0, 1),
('Pantai Drini',           'pantai-drini',           'uploads/destinations/dest_6a31f621473058.32659244.jpg',  'Gunungkidul, Yogyakarta',   4.6, 0, '06.00 - 18.00', 10000, 'Pantai landai ramah keluarga dengan perahu nelayan warna-warni.', 'Pantai', 1, 1),
('Pantai Krakal',          'pantai-krakal',          'uploads/destinations/dest_6a45c16a01c7e1.17594930.jpg',  'Gunungkidul, Yogyakarta',   4.4, 0, '06.00 - 18.00', 10000, 'Garis pantai panjang berkarang dengan kolam pasang alami.', 'Pantai', 0, 1),
('Pantai Wediombo',        'pantai-wediombo',        'uploads/destinations/dest_6a49ed971fcb11.12978049.jpeg', 'Gunungkidul, Yogyakarta',   4.6, 0, '06.00 - 18.00', 5000,  'Pantai tersembunyi dengan laguna dan air terjun kecil.', 'Pantai', 0, 1),
('Pantai Sepanjang',       'pantai-sepanjang',       'uploads/destinations/dest_6a49eec0ea55e9.81137656.jpeg', 'Gunungkidul, Yogyakarta',   4.5, 0, '06.00 - 18.00', 8000,  'Pantai panjang dengan gardu pandang dan spot foto estetik.', 'Pantai', 0, 1),
('Pantai Sadranan',        'pantai-sadranan',        'uploads/destinations/dest_6a49f55d4b1041.59486290.jpeg', 'Gunungkidul, Yogyakarta',   4.5, 0, '06.00 - 18.00', 10000, 'Pantai bersih dengan fasilitas lengkap dan snorkeling.', 'Pantai', 1, 1),
('Pantai Nglambor',        'pantai-nglambor',        'uploads/destinations/dest_6a49f569f0cbb5.78880791.jpeg', 'Gunungkidul, Yogyakarta',   4.6, 0, '06.00 - 18.00', 10000, 'Cagar biosfer dengan terumbu karang dan ikan warna-warni.', 'Pantai', 0, 1),
('Pantai Timang',          'pantai-timang',          'uploads/destinations/dest_6a4da4f563ceb9.24109212.jpeg', 'Gunungkidul, Yogyakarta',   4.7, 0, '07.00 - 17.00', 10000, 'Home of the Gondola: kereta gantung menyeberang ke Pulau Timang.', 'Pantai', 1, 1),
('Pantai Jogan',           'pantai-jogan',           'uploads/destinations/dest_6a4da513912235.19367319.jpeg', 'Gunungkidul, Yogyakarta',   4.6, 0, '06.00 - 18.00', 10000, 'Air terjun yang jatuh langsung ke bibir pantai, langka di Indonesia.', 'Pantai', 1, 1),
('Pantai Siung',           'pantai-siung',           'uploads/destinations/dest_6a4da52bbad8f2.11425809.jpeg', 'Gunungkidul, Yogyakarta',   4.5, 0, '06.00 - 18.00', 10000, 'Spot panjat tebing legendaris dengan pemandangan liar.', 'Pantai', 0, 1),
('Pantai Gesing',          'pantai-gesing',          'uploads/destinations/dest_6a4da933a53709.10129096.jpeg', 'Gunungkidul, Yogyakarta',   4.4, 0, '06.00 - 18.00', 5000,  'Pantai sepi dengan hamparan batu karang hitam yang dramatis.', 'Pantai', 0, 1),
('Pantai Pandansimo',      'pantai-pandansimo',      'uploads/destinations/dest_6a4da95c3a5a50.79560577.jpeg', 'Bantul, Yogyakarta',        4.3, 0, '06.00 - 18.00', 5000,  'Muara sungai bertemu laut, lokasi mancing favorit warga.', 'Pantai', 0, 1),
('Pantai Baru',            'pantai-baru',            'uploads/destinations/dest_6a4da96b396c08.80100969.jpeg', 'Bantul, Yogyakarta',        4.2, 0, '06.00 - 18.00', 5000,  'Pantai keluarga dengan jembatan kayu dan warung lesehan.', 'Pantai', 0, 1),
('Pantai Depok',           'pantai-depok',           'uploads/destinations/dest_6a504b34522d23.34717424.png',  'Bantul, Yogyakarta',        4.4, 0, '06.00 - 18.00', 5000,  'Sentra ikan laut segar dengan menu bandeng presto terkenal.', 'Pantai', 0, 1),
('Pantai Samas',           'pantai-samas',           'uploads/destinations/dest_6a50aa558ed7b2.97942211.jpeg', 'Bantul, Yogyakarta',        4.3, 0, '06.00 - 18.00', 5000,  'Pantai berpasir gelap dengan iringan lumbung nelayan.', 'Pantai', 0, 1),
('Gua Pindul',             'gua-pindul',             'uploads/destinations/dest_6a50aa62b84a79.61819764.jpeg', 'Gunungkidul, Yogyakarta',   4.6, 0, '07.00 - 16.00', 50000, 'Cave tubing menyusuri sungai bawah tanah dengan stalaktit.', 'Obyek Wisata', 1, 1),
('Gua Jomblang',           'gua-jomblang',           'uploads/destinations/dest_6a50aa6f56f5a0.24662403.jpeg', 'Gunungkidul, Yogyakarta',   4.8, 0, '07.00 - 14.00', 450000, 'Light of Heaven: lubang cahaya ikonik 60 meter di dasar gua.', 'Obyek Wisata', 1, 1),
('Bukit Bintang',          'bukit-bintang',          'uploads/destinations/dest_6a50cb6aec1795.32401201.jpg',  'Gunungkidul, Yogyakarta',   4.5, 0, '17.00 - 00.00', 10000,  'Bukit panorama malam Yogyakarta dengan deretan kafe gantung.', 'Obyek Wisata', 0, 1),
('Air Terjun Sri Gethuk',  'air-terjun-sri-gethuk',  'uploads/destinations/dest_6a50ea57b4c749.58928980.jpg',  'Gunungkidul, Yogyakarta',   4.6, 0, '07.00 - 17.00', 15000, 'Air terjun eksotis di antara tebing karang dengan perahu rakit.', 'Obyek Wisata', 0, 1),
('Pinus Pengger',          'pinus-pengger',          'uploads/destinations/dest_6a52b4a48cfe95.78169483.jpeg', 'Bantul, Yogyakarta',        4.5, 0, '06.00 - 18.00', 10000,  'Hutan pinus dengan instalasi seni kayu raksasa dan udara sejuk.', 'Obyek Wisata', 1, 1),
('Hutan Pinus Kaliurang',  'hutan-pinus-kaliurang',  'uploads/destinations/dest_6a52b4d1b4cfd6.79607818.png',  'Sleman, Yogyakarta',        4.4, 0, '06.00 - 17.00', 10000, 'Jalur pinus rimbun di kaki Gunung Merapi, cocok untuk santai.', 'Obyek Wisata', 0, 1),
('The Lost World Castle',  'the-lost-world-castle',  'uploads/destinations/dest_6a5464555c3ab5.05287710.png',  'Sleman, Yogyakarta',        4.4, 0, '08.00 - 17.00', 30000, 'Taman istana foto dengan latar Gunung Merapi yang megah.', 'Obyek Wisata', 1, 1),
('Malioboro',              'malioboro',              'uploads/destinations/dest_6a5464cdb46490.49093833.jpg', 'Kota Yogyakarta',           4.6, 0, '24 Jam',        0,     'Jantung kota Yogyakarta: perbelanjaan, kuliner, dan budaya.', 'Obyek Wisata', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Tiket masuk tiap destinasi (nama mengandung "masuk" = wajib, tampil paling atas)
INSERT INTO ticket_types (destination_id, name, price, unit, description, max_qty) VALUES
( 1, 'Tiket Masuk Pantai Kuta',        15000,  'orang', 'Tiket masuk area pantai',              10),
( 2, 'Tiket Masuk Tanjung Aan',        20000,  'orang', 'Tiket masuk area pantai',              10),
( 3, 'Tiket Masuk Parangtritis',       10000,  'orang', 'Tiket masuk area pantai',              10),
( 4, 'Tiket Masuk Ngrenehan',           8000,  'orang', 'Tiket masuk area pantai',              10),
( 5, 'Tiket Masuk Ngobaran',           10000,  'orang', 'Tiket masuk area pantai',              10),
( 6, 'Tiket Masuk Drini',              10000,  'orang', 'Tiket masuk area pantai',              10),
( 7, 'Tiket Masuk Krakal',             10000,  'orang', 'Tiket masuk area pantai',              10),
( 8, 'Tiket Masuk Wediombo',            5000,  'orang', 'Tiket masuk area pantai',              10),
( 9, 'Tiket Masuk Sepanjang',           8000,  'orang', 'Tiket masuk area pantai',              10),
(10, 'Tiket Masuk Sadranan',           10000,  'orang', 'Tiket masuk area pantai',              10),
(11, 'Tiket Masuk Nglambor',           10000,  'orang', 'Tiket masuk area pantai',              10),
(12, 'Tiket Masuk Timang',             10000,  'orang', 'Tiket masuk area pantai',              10),
(13, 'Tiket Masuk Jogan',              10000,  'orang', 'Tiket masuk area pantai',              10),
(14, 'Tiket Masuk Siung',              10000,  'orang', 'Tiket masuk area pantai',              10),
(15, 'Tiket Masuk Gesing',              5000,  'orang', 'Tiket masuk area pantai',              10),
(16, 'Tiket Masuk Pandansimo',          5000,  'orang', 'Tiket masuk area pantai',              10),
(17, 'Tiket Masuk Pantai Baru',         5000,  'orang', 'Tiket masuk area pantai',              10),
(18, 'Tiket Masuk Depok',               5000,  'orang', 'Tiket masuk area pantai',              10),
(19, 'Tiket Masuk Samas',               5000,  'orang', 'Tiket masuk area pantai',              10),
(20, 'Tiket Masuk Gua Pindul',         50000,  'orang', 'Termasuk pelampung dan pemandu',        5),
(21, 'Tiket Masuk Gua Jomblang',      450000,  'orang', 'Termasuk peralatan SRT dan pemandu',    2),
(22, 'Tiket Masuk Bukit Bintang',      10000,  'orang', 'Tiket masuk area gardu pandang',        6),
(23, 'Tiket Masuk Sri Gethuk',         15000,  'orang', 'Termasuk perahu rakit',                 8),
(24, 'Tiket Masuk Pinus Pengger',      10000,  'orang', 'Tiket masuk area hutan pinus',          8),
(25, 'Tiket Masuk Hutan Pinus',        10000,  'orang', 'Tiket masuk area hutan pinus',          8),
(26, 'Tiket Masuk Lost World Castle',  30000,  'orang', 'Tiket masuk area castle',              10),
-- Fasilitas tambahan (contoh di beberapa destinasi populer)
( 3, 'ATV Parangtritis',               150000, 'unit',  'Sewa ATV 30 menit',                     2),
( 5, 'Lokan Bakar Ngobaran',           25000,  'porsi', 'Lokan bakar segar',                     5),
( 8, 'Snorkeling Wediombo',            35000,  'orang', 'Sewa alat snorkeling',                  4),
(10, 'Ban Boat Sadranan',              25000,  'orang', 'Wahana ban boat 15 menit',              5),
(11, 'Snorkeling Nglambor',            35000,  'orang', 'Sewa alat snorkeling + pemandu',        4),
(12, 'Gondola Timang',                 200000, 'orang', 'Kereta gantung ke Pulau Timang',        2),
(12, 'Jembatan Timang',                 50000, 'orang', 'Penyeberangan jembatan tali',           4),
(20, 'Foto Cave Tubing',               20000,  'sesi',  'Sesi foto oleh fotografi lokasi',       10),
(26, 'ATV Lost World',                100000, 'unit',  'Sewa ATV keliling area',                2);
