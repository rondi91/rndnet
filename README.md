diff --git a/README.md b/README.md
index f6b3f4bf8791b30d01018453a207e08072e6f7a4..26ce5ee62db9944af3385bc932269e71f5ae88cb 100644
--- a/README.md
++ b/README.md
@@ -1 +1,

32 @@
 # rndnet
^\+ remove +


Template web sederhana untuk mempelajari PHP dan struktur proyek GitHub dengan studi kasus pengelolaan router Mikrotik ala Winbox.

## Cara Menjalankan

1. Pastikan PHP sudah terpasang di komputer (`php -v`).
2. Pasang dependensi RouterOS API:
   ```bash
   composer install
   ```
3. Jalankan server bawaan PHP dari folder `public`:
   ```bash
   php -S localhost:8000 -t public
   ```
4. Buka `http://localhost:8000` melalui browser untuk menambahkan router.
5. Kunjungi `http://localhost:8000/dashboard.php` untuk melihat dashboard bergaya Winbox.

## Struktur Folder

- `public/index.php` &mdash; Halaman utama untuk menambahkan router, menandai server PPPoE, dan menguji perintah RouterOS melalui API.
- `public/dashboard.php` &mdash; Dashboard dengan navigasi kiri yang menampilkan ringkasan router dan menu PPPoE aktif.
- `includes/RouterRepository.php` &mdash; Mengelola penyimpanan data router dalam file JSON.
- `includes/MikroTikClient.php` &mdash; Klien RouterOS yang memanfaatkan pustaka `evilfreelancer/routeros-api-php`.
- `includes/RouterService.php` &mdash; Logika bisnis yang menghubungkan repository dengan klien.
- `data/routers.json` &mdash; Contoh data router bawaan beserta penanda server PPPoE.
- `assets/style.css` &mdash; Gaya visual sederhana.

## Catatan

- Setiap file PHP dilengkapi komentar untuk memudahkan proses belajar.
- Pastikan perangkat Mikrotik mengizinkan koneksi API (port 8728) agar data PPPoE dapat terbaca.
