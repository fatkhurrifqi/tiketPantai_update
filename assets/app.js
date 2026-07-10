/* =============================================================
   TiketPantai — Micro-interactions
   1) Navbar shadow saat di-scroll
   2) Hero slideshow (autoplay + dots)
   3) Mobile nav toggle (hamburger)
   Dimuat di semua halaman publik.

   Catatan: fitur "scroll reveal" (IntersectionObserver + class
   .reveal/.is-visible) sudah dihapus dari sini karena style CSS
   untuk .reveal sudah dinonaktifkan sebelumnya — jadi kode itu
   cuma menambah class tanpa efek visual apa pun (kerja sia-sia).
   ============================================================= */
(function () {
  'use strict';

  /* ---- 1) Navbar shadow on scroll ----
     Menambah class 'tp-nav--scrolled' begitu halaman discroll lebih
     dari 8px, supaya navbar dapat shadow/background solid. Class ini
     dihapus lagi kalau user scroll balik ke paling atas. Dipakai
     'passive: true' supaya event scroll tidak menghambat performa
     scroll di perangkat mobile. */
  function initNav() {
    var nav = document.querySelector('.tp-nav');
    if (!nav) return;
    var onScroll = function () {
      if (window.scrollY > 8) nav.classList.add('tp-nav--scrolled');
      else nav.classList.remove('tp-nav--scrolled');
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---- 2) Hero slideshow (autoplay + dots) ----
     Mengatur slideshow foto destinasi di hero:
     - show(n): pindah ke slide ke-n, sinkronkan class 'is-active' pada
       slide & dot, dan update teks caption sesuai nama destinasi.
     - start()/stop(): jalankan/hentikan auto-play tiap 5 detik.
     - Klik salah satu dot langsung lompat ke slide itu & reset timer.
     - Auto-play dijeda saat tab browser tidak aktif (document.hidden),
       supaya tidak buang resource saat user pindah tab. */
  function initSlideshow() {
    var root = document.querySelector('.tp-slides');
    if (!root) return;
    var slides = Array.prototype.slice.call(root.querySelectorAll('.tp-slide'));
    var dots = Array.prototype.slice.call(document.querySelectorAll('.tp-dot'));
    var caption = document.querySelector('.tp-caption');
    if (slides.length === 0) return;

    var idx = 0;
    var INTERVAL = 5000;
    var timer = null;

    function show(n) {
      idx = (n + slides.length) % slides.length;
      slides.forEach(function (s, i) {
        s.classList.toggle('is-active', i === idx);
      });
      dots.forEach(function (d, i) {
        d.classList.toggle('is-active', i === idx);
      });
      if (caption && dots[idx] && dots[idx].dataset.name) {
        caption.textContent = dots[idx].dataset.name;
      }
    }

    function start() {
      stop();
      if (slides.length > 1)
        timer = setInterval(function () {
          show(idx + 1);
        }, INTERVAL);
    }
    function stop() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    // Klik dot → lompat ke slide & reset timer
    dots.forEach(function (d, i) {
      d.addEventListener('click', function () {
        show(i);
        start();
      });
    });

    // Jeda saat tab tidak aktif (hemat resource)
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stop();
      else start();
    });

    show(0);
    start();
  }

  /* ---- 3) Mobile nav toggle (hamburger) ----
     Membuka/menutup panel menu mobile saat tombol hamburger diklik.
     Ikon berubah dari 'bars' ke 'xmark' (dan sebaliknya) sesuai status
     buka/tutup, plus atribut aria-expanded diperbarui untuk aksesibilitas
     (pembaca layar). Ini fungsi inti navigasi di layar kecil, wajib ada. */
  function initMobileNav() {
    var btns = document.querySelectorAll('[data-nav-toggle]');
    if (!btns.length) return;
    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-nav-toggle');
        var menu = document.getElementById(id);
        if (!menu) return;
        var isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        btn.setAttribute('aria-expanded', String(!isOpen));
        var icon = btn.querySelector('[data-nav-icon]');
        if (icon) icon.className = isOpen ? 'fa-solid fa-bars' : 'fa-solid fa-xmark';
      });
    });
  }

  /* ---- 4) Toggle lihat/sembunyikan password ----
     Saat tombol mata pada field password diklik, tipe input berubah dari
     'password' ke 'text' (dan sebaliknya) dan ikon mata ikut berganti antara
     fa-eye / fa-eye-slash. Dipakai di halaman login & register. Setiap tombol
     menunjuk field-nya lewat atribut data-toggle-password="<id input>". */
  function initPasswordToggle() {
    var btns = document.querySelectorAll('[data-toggle-password]');
    if (!btns.length) return;
    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        var icon = btn.querySelector('i');
        if (icon) icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
      });
    });
  }

  // Jalankan semua fitur setelah DOM siap (atau langsung jika sudah siap)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initNav();
      initSlideshow();
      initMobileNav();
      initPasswordToggle();
    });
  } else {
    initNav();
    initSlideshow();
    initMobileNav();
    initPasswordToggle();
  }
})();
