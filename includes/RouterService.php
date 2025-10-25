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
    public function addRouter(
        string $name,
        string $ipAddress,
        string $username,
        string $password,
        string $notes = '',
        bool $isPppoeServer = false
    ): array
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
            'is_pppoe_server' => $isPppoeServer,
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

    /**
     * Mengumpulkan koneksi PPPoE aktif dari seluruh router yang ditandai
     * sebagai server PPPoE.
     */
    public function getActivePppoeSessions(): array
    {
        $sessions = [];

        foreach ($this->listRouters() as $router) {
            // Lewati router yang tidak bertindak sebagai server PPPoE.
            if (empty($router['is_pppoe_server'])) {
                continue;
            }

            $client = new MikroTikClient($router['ip_address'], $router['username'], $router['password']);

            if (!$client->connect()) {
                // Jika tidak bisa terhubung, lanjutkan ke router berikutnya.
                continue;
            }

            foreach ($client->getActivePppoeSessions() as $session) {
                // Lengkapi data sesi dengan nama dan IP router agar tampil
                // informatif di dashboard.
                $sessions[] = array_merge($session, [
                    'router_name' => $router['name'],
                    'router_ip' => $router['ip_address'],
                ]);
            }
        }

        return $sessions;
    }
}
