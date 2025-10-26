 
 ^\+
 
 
 (cd "$(git rev-parse --show-toplevel)" && git apply --3way <<'EOF' 
diff --git a/README.md b/README.md
index f6b3f4bf8791b30d01018453a207e08072e6f7a4..f5c468395270878e0b5708c06e6564bfadcc2d4d 100644
--- a/README.md
+++ b/README.md
@@ -1 +1,28 @@
 # rndnet
+
+Template web sederhana untuk mempelajari PHP dan struktur proyek GitHub dengan studi kasus pengelolaan router Mikrotik ala Winbox.
+
+## Cara Menjalankan
+
+1. Pastikan PHP sudah terpasang di komputer (`php -v`).
+2. Jalankan server bawaan PHP dari folder `public`:
+   ```bash
+   php -S localhost:8000 -t public
+   ```
+3. Buka `http://localhost:8000` melalui browser untuk menambahkan router.
+4. Kunjungi `http://localhost:8000/dashboard.php` untuk melihat dashboard bergaya Winbox.
+
+## Struktur Folder
+
+- `public/index.php` &mdash; Halaman utama untuk menambahkan router, menandai server PPPoE, dan mencoba perintah simulasi.
+- `public/dashboard.php` &mdash; Dashboard dengan navigasi kiri yang menampilkan ringkasan router dan menu PPPoE aktif.
+- `includes/RouterRepository.php` &mdash; Mengelola penyimpanan data router dalam file JSON.
+- `includes/MikroTikClient.php` &mdash; Klien tiruan untuk meniru komunikasi RouterOS API.
+- `includes/RouterService.php` &mdash; Logika bisnis yang menghubungkan repository dengan klien.
+- `data/routers.json` &mdash; Contoh data router bawaan beserta penanda server PPPoE.
+- `assets/style.css` &mdash; Gaya visual sederhana.
+
+## Catatan
+
+- Setiap file PHP dilengkapi komentar untuk memudahkan proses belajar.
+- Anda dapat mengembangkan lebih jauh dengan mengintegrasikan pustaka RouterOS asli bila diperlukan.
 
EOF
)