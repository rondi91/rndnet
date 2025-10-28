
<?php
/**
 * RouterRepository bertugas mengelola data router Mikrotik yang disimpan
 * dalam file JSON sederhana. Pendekatan ini memudahkan proses belajar karena
 * tidak perlu menyiapkan database sungguhan.
 */
class RouterRepository
{
    /** @var string $storagePath Lokasi file penyimpanan data router. */
    private string $storagePath;

    /**
     * Konstruktor menyimpan lokasi file JSON agar bisa digunakan kembali.
     *
     * @param string $storagePath Jalur lengkap menuju file data JSON.
     */
    public function __construct(string $storagePath)
    {
        $this->storagePath = $storagePath;

        // Jika file belum ada maka buat dengan isi array kosong.
        if (!file_exists($this->storagePath)) {
            file_put_contents($this->storagePath, json_encode([]));
        }
    }

    /**
     * Mengambil seluruh data router dari file JSON.
     *
     * @return array Daftar router yang tersimpan.
     */
    public function all(): array
    {
        $content = file_get_contents($this->storagePath);

        // json_decode akan mengubah string JSON menjadi array PHP.
        return json_decode($content, true) ?? [];
    }

    /**
     * Menyimpan daftar router ke dalam file JSON.
     *
     * @param array $routers Data router dalam bentuk array.
     */
    private function persist(array $routers): void
    {
        file_put_contents($this->storagePath, json_encode($routers, JSON_PRETTY_PRINT));
    }

    /**
     * Menghapus router berdasarkan alamat IP yang tersimpan.
     */
    public function removeByIp(string $ipAddress): bool
    {
        $ipAddress = trim($ipAddress);

        if ($ipAddress === '') {
            return false;
        }

        $routers = $this->all();
        $filtered = [];
        $removed = false;

        foreach ($routers as $router) {
            if (($router['ip_address'] ?? '') === $ipAddress) {
                $removed = true;
                continue;
            }

            $filtered[] = $router;
        }

        if ($removed) {
            $this->persist($filtered);
        }

        return $removed;
    }

    /**
     * Menambahkan router baru ke dalam penyimpanan.
     *
     * @param array $router Data router (nama, alamat IP, dan catatan).
     */
    public function add(array $router): void
    {
        $routers = $this->all();

        // Tambahkan router baru di akhir array.
        $routers[] = $router;

        $this->persist($routers);
    }

    /**
     * Mencari router berdasarkan alamat IP yang tersimpan.
     */
    public function findByIp(string $ipAddress): ?array
    {
        foreach ($this->all() as $router) {
            if (($router['ip_address'] ?? '') === $ipAddress) {
                return $router;
            }
        }

        return null;
    }

    /**
     * Mengembalikan jalur penyimpanan JSON yang digunakan repository.
     */
    public function getStoragePath(): string
    {
        return $this->storagePath;
    }
}
