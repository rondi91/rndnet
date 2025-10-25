<?php
require_once __DIR__ . '/RouterRepository.php';
require_once __DIR__ . '/MikroTikClient.php';

/**
 * RouterService menghubungkan logika bisnis antara penyimpanan router dan
 * klien Mikrotik. Struktur ini membantu memisahkan tanggung jawab sehingga
 * kode lebih mudah dipahami.
 */
class RouterService
{
    /** @var RouterRepository */
    private RouterRepository $repository;

    /**
     * Konstruktor menerima dependensi RouterRepository melalui injeksi.
     */
    public function __construct(RouterRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Mengambil semua router yang tersedia.
     */
    public function listRouters(): array
    {
        return $this->repository->all();
    }

    /**
     * Menambahkan router baru setelah dilakukan validasi sederhana.
     */
    public function addRouter(string $name, string $ipAddress, string $username, string $password, string $notes = ''): array
    {
        $errors = [];

        // Validasi nama router.
        if (trim($name) === '') {
            $errors[] = 'Nama router wajib diisi.';
        }

        // Validasi IP address dengan filter bawaan PHP.
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $errors[] = 'Alamat IP tidak valid.';
        }

        // Username dan password sederhana.
        if (trim($username) === '' || trim($password) === '') {
            $errors[] = 'Username dan password wajib diisi.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Simpan data router ke repository.
        $this->repository->add([
            'name' => $name,
            'ip_address' => $ipAddress,
            'username' => $username,
            'password' => $password,
            'notes' => $notes,
        ]);

        return ['success' => true];
    }

    /**
     * Mensimulasikan eksekusi perintah ke router dengan memanfaatkan
     * MikroTikClient mock.
     */
    public function runCommand(array $router, string $command): array
    {
        $client = new MikroTikClient($router['ip_address'], $router['username'], $router['password']);

        if (!$client->connect()) {
            return ['success' => false, 'message' => 'Gagal terhubung ke router.'];
        }

        $result = $client->execute($command);

        return [
            'success' => true,
            'data' => $result,
        ];
    }
}
