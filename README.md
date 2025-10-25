# rndnet

Template web sederhana untuk mempelajari PHP dan struktur proyek GitHub dengan studi kasus pengelolaan router Mikrotik ala Winbox.

## Cara Menjalankan

1. Pastikan PHP sudah terpasang di komputer (`php -v`).
2. Jalankan server bawaan PHP dari folder `public`:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Buka `http://localhost:8000` melalui browser.

## Struktur Folder

- `public/index.php` &mdash; Halaman utama yang menampilkan daftar router, form penambahan, dan simulasi perintah.
- `includes/RouterRepository.php` &mdash; Mengelola penyimpanan data router dalam file JSON.
- `includes/MikroTikClient.php` &mdash; Klien tiruan untuk meniru komunikasi RouterOS API.
- `includes/RouterService.php` &mdash; Logika bisnis yang menghubungkan repository dengan klien.
- `data/routers.json` &mdash; Contoh data router bawaan.
- `assets/style.css` &mdash; Gaya visual sederhana.

## Catatan

- Setiap file PHP dilengkapi komentar untuk memudahkan proses belajar.
- Anda dapat mengembangkan lebih jauh dengan mengintegrasikan pustaka RouterOS asli bila diperlukan.
