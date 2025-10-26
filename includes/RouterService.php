
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
     * Cache sederhana untuk menampung hasil perhitungan PPPoE agar tidak
     * perlu melakukan koneksi berulang ketika diminta beberapa kali dalam
     * satu request.
     */
    private ?array $cachedPppoeData = null;

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

        // Reset cache PPPoE agar data terbaru langsung terbaca.
        $this->cachedPppoeData = null;

        return ['success' => true];
    }

    /**
     * Menjalankan perintah RouterOS menggunakan pustaka EvilFreelancer.
     * Ketika koneksi gagal atau perintah tidak valid, informasi kesalahan
     * akan dikembalikan agar dapat ditampilkan di antarmuka.
     */
    public function runCommand(array $router, string $command): array
    {
        $client = new MikroTikClient(
            $router['ip_address'],
            $router['username'],
            $router['password']
        );

        try {
            $result = $client->execute($command);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Mengumpulkan koneksi PPPoE aktif dari seluruh router yang ditandai
     * sebagai server PPPoE.
     */
    public function getActivePppoeSessions(): array
    {
        $pppoeData = $this->collectPppoeSessions();

        return $pppoeData['flat'];
    }

    /**
     * Mengelompokkan koneksi PPPoE berdasarkan router. Informasi ini
     * memudahkan antarmuka menampilkan sesi aktif per-server sekaligus
     * memberikan status konektivitas setiap router.
     */
    public function getActivePppoeSessionsByRouter(): array
    {
        $pppoeData = $this->collectPppoeSessions();

        return $pppoeData['grouped'];
    }

    /**
     * Menentukan apakah sebuah entri router bertindak sebagai server PPPoE.
     * Router lama mungkin belum memiliki properti `is_pppoe_server`, sehingga
     * secara default diasumsikan bertindak sebagai server agar data tetap
     * muncul pada dashboard.
     */
    private function isPppoeServer(array $router): bool
    {
        if (!array_key_exists('is_pppoe_server', $router)) {
            return true;
        }

        return (bool) $router['is_pppoe_server'];
    }

    /**
     * Mengumpulkan data PPPoE aktif dalam bentuk rata dan terkelompok.
     */
    private function collectPppoeSessions(): array
    {
        if ($this->cachedPppoeData !== null) {
            return $this->cachedPppoeData;
        }

        $flatSessions = [];
        $groupedSessions = [];

        foreach ($this->listRouters() as $router) {
            if (!$this->isPppoeServer($router)) {
                continue;
            }

            $serverKey = $router['ip_address'];

            if (!isset($groupedSessions[$serverKey])) {
                $groupedSessions[$serverKey] = [
                    'router_name' => $router['name'],
                    'router_ip' => $router['ip_address'],
                    'notes' => $router['notes'] ?? '',
                    'reachable' => false,
                    'sessions' => [],
                    'total_sessions' => 0,
                    'error' => null,
                ];
            }

            $client = new MikroTikClient($router['ip_address'], $router['username'], $router['password']);

            if (!$client->connect()) {
                $groupedSessions[$serverKey]['reachable'] = false;
                $groupedSessions[$serverKey]['error'] = $client->getLastError();

                continue;
            }

            $groupedSessions[$serverKey]['reachable'] = true;

            $sessions = $client->getActivePppoeSessions();

            if ($client->getLastError() !== null) {
                $groupedSessions[$serverKey]['error'] = $client->getLastError();
            }

            foreach ($sessions as $session) {
                $detailedSession = array_merge($session, [
                    'router_name' => $router['name'],
                    'router_ip' => $router['ip_address'],
                ]);

                $flatSessions[] = $detailedSession;
                $groupedSessions[$serverKey]['sessions'][] = $detailedSession;
            }

            $groupedSessions[$serverKey]['total_sessions'] = count($groupedSessions[$serverKey]['sessions']);
        }

        $this->cachedPppoeData = [
            'flat' => $flatSessions,
            'grouped' => array_values($groupedSessions),
        ];

        return $this->cachedPppoeData;
    }
}
