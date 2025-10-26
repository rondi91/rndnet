
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
     * Mengambil satu router berdasarkan alamat IP.
     */
    public function findRouterByIp(string $ipAddress): ?array
    {
        return $this->repository->findByIp($ipAddress);
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
     * Menghasilkan data lengkap untuk tampilan dashboard PPPoE.
     */
    public function getPppoeDashboardData(): array
    {
        $pppoeData = $this->collectPppoeSessions();

        return [
            'servers' => $pppoeData['grouped'],
            'totals' => $pppoeData['totals'],
        ];
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
        $totalActiveSessions = 0;
        $totalInactiveUsers = 0;
        $routers = $this->listRouters();
        $timestamp = date('c');

        foreach ($routers as $router) {
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
                    'total_inactive' => 0,
                    'inactive_users' => [],
                    'error' => null,
                    'last_refreshed' => $timestamp,
                    'generated_at' => $timestamp,
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
            $secrets = $client->getPppoeSecrets();

            if ($client->getLastError() !== null) {
                $groupedSessions[$serverKey]['error'] = $client->getLastError();
            }

            $secretsByName = [];
            foreach ($secrets as $secret) {
                $secretsByName[$secret['name'] ?? ''] = $secret;
            }

            $activeUsers = [];

            foreach ($sessions as $session) {
                $detailedSession = $this->buildActivePppoeSession($router, $session, $secretsByName);

                $activeUsers[$detailedSession['user']] = true;
                $flatSessions[] = $detailedSession;
                $groupedSessions[$serverKey]['sessions'][] = $detailedSession;
            }

            foreach ($secrets as $secret) {
                $username = $secret['name'] ?? '';

                if ($username === '' || isset($activeUsers[$username])) {
                    continue;
                }

                $inactiveUser = [
                    'user' => $username,
                    'profile' => $secret['profile'] ?? '',
                    'service' => $secret['service'] ?? '',
                    'disabled' => (bool) ($secret['disabled'] ?? false),
                    'last_logged_out' => $secret['last_logged_out'] ?? '',
                    'comment' => $secret['comment'] ?? '',
                    'secret_id' => $secret['id'] ?? '',
                ];

                $groupedSessions[$serverKey]['inactive_users'][] = $inactiveUser;
            }

            $groupedSessions[$serverKey]['total_sessions'] = count($groupedSessions[$serverKey]['sessions']);
            $groupedSessions[$serverKey]['total_inactive'] = count($groupedSessions[$serverKey]['inactive_users']);
            $totalActiveSessions += $groupedSessions[$serverKey]['total_sessions'];
            $totalInactiveUsers += $groupedSessions[$serverKey]['total_inactive'];
        }

        $this->cachedPppoeData = [
            'flat' => $flatSessions,
            'grouped' => array_values($groupedSessions),
            'totals' => [
                'routers' => count($routers),
                'pppoe_servers' => count($groupedSessions),
                'active_sessions' => $totalActiveSessions,
                'inactive_users' => $totalInactiveUsers,
                'generated_at' => $timestamp,
            ],
        ];

        return $this->cachedPppoeData;
    }

    /**
     * Membentuk struktur sesi PPPoE aktif yang dilengkapi dengan profil dan
     * informasi pendukung lainnya.
     */
    private function buildActivePppoeSession(array $router, array $session, array $secretsByName): array
    {
        $username = $session['user'] ?? '-';
        $profile = $session['profile'] ?? '';

        if ($profile === '' && isset($secretsByName[$username])) {
            $profile = $secretsByName[$username]['profile'] ?? '';
        }

        $uptime = $session['uptime'] ?? '';

        $secretId = $secretsByName[$username]['id'] ?? '';

        return [
            'router_name' => $router['name'],
            'router_ip' => $router['ip_address'],
            'user' => $username,
            'profile' => $profile,
            'address' => $session['address'] ?? '-',
            'uptime' => $uptime,
            'uptime_seconds' => $this->parseDurationToSeconds($uptime),
            'service' => $session['service'] ?? '',
            'secret_id' => $secretId,
        ];
    }

    /**
     * Menghapus PPPoE secret pada router tertentu.
     */
    public function removePppoeSecret(string $routerIp, string $secretId): array
    {
        $router = $this->findRouterByIp($routerIp);

        if ($router === null) {
            return [
                'success' => false,
                'message' => 'Router tidak ditemukan.',
            ];
        }

        $client = new MikroTikClient($router['ip_address'], $router['username'], $router['password']);

        if (!$client->connect()) {
            return [
                'success' => false,
                'message' => $client->getLastError() ?? 'Gagal terhubung ke router.',
            ];
        }

        if ($secretId === '') {
            return [
                'success' => false,
                'message' => 'ID secret PPPoE tidak valid.',
            ];
        }

        $result = $client->removePppoeSecret($secretId);

        if ($result === false) {
            return [
                'success' => false,
                'message' => $client->getLastError() ?? 'Gagal menghapus secret PPPoE.',
            ];
        }

        // Reset cache agar data terbaru terbaca pada permintaan berikutnya.
        $this->cachedPppoeData = null;

        return ['success' => true];
    }

    /**
     * Mengonversi format durasi RouterOS menjadi total detik agar proses
     * penyortiran di sisi antarmuka lebih mudah dilakukan.
     */
    private function parseDurationToSeconds(string $duration): int
    {
        $duration = trim($duration);

        if ($duration === '') {
            return 0;
        }

        if (preg_match_all('/(\d+)([wdhms])/', $duration, $matches, PREG_SET_ORDER)) {
            $seconds = 0;

            foreach ($matches as $match) {
                $value = (int) $match[1];

                switch ($match[2]) {
                    case 'w':
                        $seconds += $value * 604800;
                        break;
                    case 'd':
                        $seconds += $value * 86400;
                        break;
                    case 'h':
                        $seconds += $value * 3600;
                        break;
                    case 'm':
                        $seconds += $value * 60;
                        break;
                    case 's':
                        $seconds += $value;
                        break;
                }
            }

            return $seconds;
        }

        if (preg_match('/^(\d+):(\d+):(\d+)$/', $duration, $matches)) {
            return ((int) $matches[1] * 3600) + ((int) $matches[2] * 60) + (int) $matches[3];
        }

        return 0;
    }
}
