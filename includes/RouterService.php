<?php
require_once __DIR__ . '/RouterRepository.php';
if (!class_exists('MikroTikClient', false)) {
    $mikroTikClientPath = __DIR__ . '/MikroTikClient.php';

    if (file_exists($mikroTikClientPath)) {
        require_once $mikroTikClientPath;
    }
}

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

    /** @var string $clientStoragePath Lokasi penyimpanan data klien router. */
    private string $clientStoragePath;

    /** @var string $serverStoragePath Lokasi penyimpanan kredensial bandwidth server. */
    private string $serverStoragePath;

    /** @var array|null Cache kredensial bandwidth server yang sudah dibaca. */
    private ?array $cachedBandwidthServers = null;

    /** @var string Jalur penyimpanan batasan frekuensi bandwidth test. */
    private string $bandwidthRateLimitPath;

    /** @var array|null Cache batasan frekuensi bandwidth test. */
    private ?array $bandwidthRateLimitState = null;

    private const DEFAULT_CLIENT_USERNAME = 'rondi';
    private const DEFAULT_CLIENT_PASSWORD = '21184662';
    private const BANDWIDTH_RATE_LIMIT_SECONDS = 15;

    /**
     * Konstruktor menerima dependensi RouterRepository melalui injeksi.
     */
    public function __construct(
        RouterRepository $repository,
        ?string $clientStoragePath = null,
        ?string $serverStoragePath = null,
        ?string $bandwidthRateLimitPath = null
    )
    {
        $this->repository = $repository;
        $this->clientStoragePath = $clientStoragePath ?? __DIR__ . '/../data/router_client.json';
        $this->serverStoragePath = $serverStoragePath ?? __DIR__ . '/../data/routers_server.json';
        $this->bandwidthRateLimitPath = $bandwidthRateLimitPath ?? __DIR__ . '/../data/bandwidth_rate_limit.json';
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

        $name = trim($name);
        $ipAddress = trim($ipAddress);
        $username = trim($username);
        $password = trim($password);
        $notes = trim($notes);

        if ($username === '') {
            $username = self::DEFAULT_CLIENT_USERNAME;
        }

        if ($password === '') {
            $password = self::DEFAULT_CLIENT_PASSWORD;
        }

        if ($name === '') {
            $name = $ipAddress !== '' ? $ipAddress : 'Router PPPoE';
        }

        // Validasi nama router.
        if ($name === '') {
            $errors[] = 'Nama router wajib diisi.';
        }

        // Validasi IP address dengan filter bawaan PHP.
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $errors[] = 'Alamat IP tidak valid.';
        }

        // Username dan password sederhana.
        if ($username === '' || $password === '') {
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
        $clientError = null;
        $client = $this->makeMikroTikClient($router, $clientError);

        if ($client === null) {
            return [
                'success' => false,
                'message' => $clientError ?? 'Kelas MikroTikClient tidak ditemukan. Pastikan file `includes/MikroTikClient.php` tersedia.',
            ];
        }

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
     * Mengambil informasi trafik interface ethernet dari seluruh router yang
     * telah terdaftar.
     */
    public function getEthernetTrafficByRouter(): array
    {
        $snapshot = $this->getRouterClientSnapshot();
        $clientEntries = [];

        if (isset($snapshot['clients']) && is_array($snapshot['clients'])) {
            foreach ($snapshot['clients'] as $entry) {
                $ipAddress = trim((string) ($entry['ip_address'] ?? $entry['client_address'] ?? ''));

                if ($ipAddress === '') {
                    continue;
                }

                $clientEntries[] = [
                    'name' => $entry['name']
                        ?? $entry['client_name']
                        ?? $entry['pppoe_username']
                        ?? $entry['username']
                        ?? $ipAddress,
                    'ip_address' => $ipAddress,
                    'username' => $entry['username'] ?? self::DEFAULT_CLIENT_USERNAME,
                    'password' => $entry['password'] ?? self::DEFAULT_CLIENT_PASSWORD,
                    'notes' => $entry['notes'] ?? ($entry['comment'] ?? ''),
                    'pppoe_profile' => $entry['profile'] ?? ($entry['pppoe_profile'] ?? ''),
                    'pppoe_username' => $entry['pppoe_username'] ?? ($entry['pppoe_user'] ?? $entry['username'] ?? ''),
                    'server_ip' => $entry['server_ip'] ?? '',
                    'server_name' => $entry['server_name'] ?? '',
                    'client_key' => $entry['client_key'] ?? $this->normaliseRouterClientKey($entry),
                    'preferred_interface' => $entry['preferred_interface'] ?? ($entry['iface'] ?? ''),
                ];
            }
        }

        if (empty($clientEntries) && isset($snapshot['routers']) && is_array($snapshot['routers'])) {
            foreach ($snapshot['routers'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $ipAddress = trim((string) ($entry['ip'] ?? $entry['ip_address'] ?? ''));

                if ($ipAddress === '') {
                    continue;
                }

                $serverInfo = $this->determineBandwidthServer($ipAddress);

                $clientEntries[] = [
                    'name' => $entry['name'] ?? $ipAddress,
                    'ip_address' => $ipAddress,
                    'username' => $entry['user'] ?? $entry['username'] ?? self::DEFAULT_CLIENT_USERNAME,
                    'password' => $entry['pass'] ?? $entry['password'] ?? self::DEFAULT_CLIENT_PASSWORD,
                    'notes' => $entry['notes'] ?? '',
                    'pppoe_profile' => $entry['profile'] ?? '',
                    'pppoe_username' => $entry['pppoe_username'] ?? '',
                    'server_ip' => $serverInfo['ip'] ?? ($entry['server_ip'] ?? ''),
                    'server_name' => $serverInfo['label'] ?? ($entry['server_name'] ?? ''),
                    'server_source' => $serverInfo['source'] ?? null,
                    'client_key' => $entry['client_key'] ?? $this->normaliseRouterClientKey([
                        'ip_address' => $ipAddress,
                        'username' => $entry['user'] ?? self::DEFAULT_CLIENT_USERNAME,
                    ]),
                    'preferred_interface' => $entry['preferred_interface'] ?? ($entry['iface'] ?? ''),
                ];
            }
        }

        $usingClientSnapshot = count($clientEntries) > 0;
        $routers = $usingClientSnapshot ? $clientEntries : $this->listRouters();
        $results = [];
        $timestamp = date('c');
        $totalInterfaces = 0;

        foreach ($routers as $router) {
            $ipAddress = trim((string) ($router['ip_address'] ?? ''));

            if ($ipAddress === '') {
                continue;
            }

            $serverInfo = $this->determineBandwidthServer($ipAddress);

            $clientError = null;
            $client = $this->makeMikroTikClient($router + ['ip_address' => $ipAddress], $clientError);

            if ($client === null) {
                $results[] = [
                'router_name' => $router['name'] ?? $router['router_name'] ?? $ipAddress,
                'router_ip' => $ipAddress,
                'is_pppoe_server' => $usingClientSnapshot ? false : $this->isPppoeServer($router),
                'reachable' => false,
                'error' => $clientError,
                'interfaces' => [],
                'last_refreshed' => $timestamp,
                'notes' => $router['notes'] ?? '',
                'pppoe_profile' => $router['pppoe_profile'] ?? '',
                'pppoe_username' => $router['pppoe_username'] ?? '',
                'server_ip' => $serverInfo['ip'] ?? ($router['server_ip'] ?? ''),
                'server_name' => $serverInfo['label'] ?? ($router['server_name'] ?? ''),
                'server_source' => $serverInfo['source'] ?? null,
                'client_key' => $router['client_key'] ?? null,
                'preferred_interface' => $router['preferred_interface'] ?? ($router['iface'] ?? ''),
                'iface' => $router['preferred_interface'] ?? ($router['iface'] ?? ''),
                'link_capacity_mbps' => null,
                'username' => $router['username'] ?? self::DEFAULT_CLIENT_USERNAME,
                'password' => $router['password'] ?? self::DEFAULT_CLIENT_PASSWORD,
            ];

            continue;
        }

            $interfacesResult = $client->getEthernetInterfaces();
            $interfaces = $interfacesResult['interfaces'] ?? [];

            if (!empty($interfacesResult['success'])) {
                $totalInterfaces += count($interfaces);
            }

            $results[] = [
                'router_name' => $router['name'] ?? $router['router_name'] ?? $ipAddress,
                'router_ip' => $ipAddress,
                'is_pppoe_server' => $usingClientSnapshot ? false : $this->isPppoeServer($router),
                'reachable' => !empty($interfacesResult['success']),
                'error' => empty($interfacesResult['success']) ? ($interfacesResult['error'] ?? $client->getLastError()) : null,
                'interfaces' => $interfaces,
                'last_refreshed' => $timestamp,
                'notes' => $router['notes'] ?? '',
                'pppoe_profile' => $router['pppoe_profile'] ?? '',
                'pppoe_username' => $router['pppoe_username'] ?? '',
                'server_ip' => $serverInfo['ip'] ?? ($router['server_ip'] ?? ''),
                'server_name' => $serverInfo['label'] ?? ($router['server_name'] ?? ''),
                'server_source' => $serverInfo['source'] ?? null,
                'client_key' => $router['client_key'] ?? null,
                'preferred_interface' => $router['preferred_interface'] ?? ($router['iface'] ?? ''),
                'iface' => $router['preferred_interface'] ?? ($router['iface'] ?? ''),
                'link_capacity_mbps' => $this->extractInterfaceCapacityMbps(
                    $interfaces,
                    $router['preferred_interface'] ?? ($router['iface'] ?? '')
                ),
                'username' => $router['username'] ?? self::DEFAULT_CLIENT_USERNAME,
                'password' => $router['password'] ?? self::DEFAULT_CLIENT_PASSWORD,
            ];
        }

        return [
            'generated_at' => $timestamp,
            'total_routers' => count($results),
            'total_interfaces' => $totalInterfaces,
            'routers' => $results,
            'source' => $usingClientSnapshot ? 'router_clients' : 'routers',
            'client_snapshot_generated_at' => $snapshot['generated_at'] ?? null,
            'data_files' => $this->describeInterfaceDataFiles($usingClientSnapshot),
        ];
    }

    private function ensureBandwidthRateLimitFile(): void
    {
        $directory = dirname($this->bandwidthRateLimitPath);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (!file_exists($this->bandwidthRateLimitPath)) {
            file_put_contents($this->bandwidthRateLimitPath, json_encode(new \stdClass()));
        }
    }

    private function getBandwidthRateLimitState(): array
    {
        if ($this->bandwidthRateLimitState !== null) {
            return $this->bandwidthRateLimitState;
        }

        $this->ensureBandwidthRateLimitFile();

        $contents = @file_get_contents($this->bandwidthRateLimitPath);

        if ($contents === false || trim($contents) === '') {
            $this->bandwidthRateLimitState = [];

            return $this->bandwidthRateLimitState;
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $this->bandwidthRateLimitState = $decoded;

        return $this->bandwidthRateLimitState;
    }

    private function persistBandwidthRateLimitState(array $state): void
    {
        $this->bandwidthRateLimitState = $state;
        $this->ensureBandwidthRateLimitFile();

        file_put_contents(
            $this->bandwidthRateLimitPath,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private function updateBandwidthRateLimitRecord(string $routerIp, array $updates): void
    {
        $state = $this->getBandwidthRateLimitState();
        $current = $state[$routerIp] ?? [];
        $state[$routerIp] = array_merge($current, $updates);
        $this->persistBandwidthRateLimitState($state);
    }

    private function getBandwidthRateLimitRecord(string $routerIp): array
    {
        $state = $this->getBandwidthRateLimitState();

        return $state[$routerIp] ?? [];
    }

    private function formatIsoTimestamp(?int $timestamp): ?string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return null;
        }

        return date('c', $timestamp);
    }

    /**
     * Menjalankan bandwidth test terhadap router tertentu dan mengembalikan
     * ringkasan hasilnya sehingga dapat ditampilkan pada dashboard.
     */
    public function runBandwidthTestForRouter(string $routerIp, array $options = []): array
    {
        $routerIp = trim($routerIp);
        $windowSeconds = self::BANDWIDTH_RATE_LIMIT_SECONDS;

        if ($routerIp === '') {
            return [
                'success' => false,
                'message' => 'Alamat IP router tidak valid.',
                'rate_limit' => [
                    'window_seconds' => $windowSeconds,
                ],
            ];
        }

        $router = $this->resolveRouterCredentialsForIp($routerIp);

        if ($router === null) {
            return [
                'success' => false,
                'message' => 'Router tidak ditemukan pada daftar penyimpanan.',
                'rate_limit' => [
                    'window_seconds' => $windowSeconds,
                ],
            ];
        }

        $serverInfo = $this->determineBandwidthServer($routerIp);

        if (empty($serverInfo['ip'])) {
            return [
                'success' => false,
                'router_ip' => $routerIp,
                'message' => 'Server bandwidth test tidak ditemukan untuk router ini.',
                'rate_limit' => [
                    'window_seconds' => $windowSeconds,
                ],
            ];
        }

        if (empty($serverInfo['username']) || empty($serverInfo['password'])) {
            return [
                'success' => false,
                'router_ip' => $routerIp,
                'server_ip' => $serverInfo['ip'],
                'message' => 'Kredensial bandwidth server tidak ditemukan. Perbarui berkas routers_server.json.',
                'rate_limit' => [
                    'window_seconds' => $windowSeconds,
                ],
            ];
        }

        $rateRecord = $this->getBandwidthRateLimitRecord($routerIp);
        $lastStartedAt = isset($rateRecord['last_started_at']) ? (int) $rateRecord['last_started_at'] : null;
        $lastCompletedAt = isset($rateRecord['last_completed_at']) ? (int) $rateRecord['last_completed_at'] : null;
        $nextAvailableAt = isset($rateRecord['next_available_at']) ? (int) $rateRecord['next_available_at'] : null;

        $rateLimitInfo = [
            'window_seconds' => $windowSeconds,
            'last_started_at' => $this->formatIsoTimestamp($lastStartedAt),
            'last_completed_at' => $this->formatIsoTimestamp($lastCompletedAt),
            'next_available_at' => $this->formatIsoTimestamp($nextAvailableAt),
        ];

        $now = time();

        if ($nextAvailableAt !== null && $nextAvailableAt > $now) {
            $retryAfter = max(1, $nextAvailableAt - $now);

            return [
                'success' => false,
                'router_ip' => $routerIp,
                'message' => sprintf('Bandwidth test baru dijalankan. Coba lagi dalam %d detik.', $retryAfter),
                'retry_after' => $retryAfter,
                'rate_limit' => $rateLimitInfo,
            ];
        }

        if ($lastStartedAt !== null && ($now - $lastStartedAt) < $windowSeconds) {
            $retryAfter = max(1, $windowSeconds - ($now - $lastStartedAt));
            $rateLimitInfo['next_available_at'] = $this->formatIsoTimestamp($lastStartedAt + $windowSeconds);

            return [
                'success' => false,
                'router_ip' => $routerIp,
                'message' => sprintf('Bandwidth test baru dijalankan. Coba lagi dalam %d detik.', $retryAfter),
                'retry_after' => $retryAfter,
                'rate_limit' => $rateLimitInfo,
            ];
        }

        $clientError = null;
        $client = $this->makeMikroTikClient($router, $clientError);

        if ($client === null) {
            return [
                'success' => false,
                'router_ip' => $routerIp,
                'message' => $clientError ?? 'Dependensi MikroTikClient belum tersedia.',
                'rate_limit' => $rateLimitInfo,
            ];
        }

        $duration = isset($options['duration']) ? max(1, min(60, (int) $options['duration'])) : null;
        $connectionCount = isset($options['connection_count']) ? max(1, (int) $options['connection_count']) : null;
        $directionInfo = $this->normaliseBandwidthDirection($options['direction'] ?? null);

        $protocol = strtolower((string) ($options['protocol'] ?? 'tcp'));

        if (!in_array($protocol, ['tcp', 'udp'], true)) {
            $protocol = 'tcp';
        }

        $payload = [
            'address' => $serverInfo['ip'],
            'user' => $serverInfo['username'],
            'password' => $serverInfo['password'],
            'protocol' => $protocol,
            'direction' => $directionInfo['router'],
        ];

        if ($duration !== null) {
            $payload['duration'] = (string) $duration;
        }

        if ($connectionCount !== null) {
            $payload['connection-count'] = (string) $connectionCount;
        }

        $startedAt = time();
        $nextAvailableAfterStart = $startedAt + $windowSeconds;

        $this->updateBandwidthRateLimitRecord($routerIp, [
            'last_started_at' => $startedAt,
            'last_started_at_iso' => $this->formatIsoTimestamp($startedAt),
            'next_available_at' => $nextAvailableAfterStart,
            'next_available_at_iso' => $this->formatIsoTimestamp($nextAvailableAfterStart),
            'window_seconds' => $windowSeconds,
            'last_status' => 'running',
            'last_error' => null,
        ]);

        $rateLimitInfo['last_started_at'] = $this->formatIsoTimestamp($startedAt);
        $rateLimitInfo['next_available_at'] = $this->formatIsoTimestamp($nextAvailableAfterStart);

        $result = $client->runBandwidthTest($payload);

        $completedAtFallback = time();
        $resultStartedIso = isset($result['started_at']) ? (string) $result['started_at'] : date('c', $startedAt);
        $resultCompletedIso = isset($result['completed_at']) ? (string) $result['completed_at'] : date('c', $completedAtFallback);
        $parsedCompleted = strtotime($resultCompletedIso);

        if ($parsedCompleted === false) {
            $parsedCompleted = $completedAtFallback;
        }

        $finalNextAvailable = max($nextAvailableAfterStart, $parsedCompleted);

        if (empty($result['success'])) {
            $message = $result['error'] ?? $client->getLastError() ?? 'Bandwidth test gagal dijalankan.';

            $this->updateBandwidthRateLimitRecord($routerIp, [
                'last_completed_at' => $parsedCompleted,
                'last_completed_at_iso' => $this->formatIsoTimestamp($parsedCompleted),
                'next_available_at' => $finalNextAvailable,
                'next_available_at_iso' => $this->formatIsoTimestamp($finalNextAvailable),
                'last_status' => 'error',
                'last_error' => $message,
            ]);

            $rateLimitInfo['last_completed_at'] = $this->formatIsoTimestamp($parsedCompleted);
            $rateLimitInfo['next_available_at'] = $this->formatIsoTimestamp($finalNextAvailable);

            return [
                'success' => false,
                'router_ip' => $routerIp,
                'server_ip' => $serverInfo['ip'],
                'message' => $message,
                'rate_limit' => $rateLimitInfo,
                'raw_response' => $result,
                'raw_replies' => $result['raw_replies'] ?? [],
            ];
        }

        $entries = isset($result['entries']) && is_array($result['entries']) ? $result['entries'] : [];
        $summary = $this->summariseBandwidthEntries($entries, $result);

        $this->updateBandwidthRateLimitRecord($routerIp, [
            'last_completed_at' => $parsedCompleted,
            'last_completed_at_iso' => $this->formatIsoTimestamp($parsedCompleted),
            'next_available_at' => $finalNextAvailable,
            'next_available_at_iso' => $this->formatIsoTimestamp($finalNextAvailable),
            'last_status' => 'success',
            'last_error' => null,
            'last_summary' => [
                'tx_current_bps' => $summary['tx_current_bps'] ?? 0,
                'rx_current_bps' => $summary['rx_current_bps'] ?? 0,
                'tx_total_average_bps' => $summary['tx_total_average_bps'] ?? 0,
                'rx_total_average_bps' => $summary['rx_total_average_bps'] ?? 0,
                'tx_peak_bps' => $summary['tx_peak_bps'] ?? 0,
                'rx_peak_bps' => $summary['rx_peak_bps'] ?? 0,
                'direction' => $directionInfo['value'],
                'protocol' => strtoupper($protocol),
                'duration' => $duration,
            ],
        ]);

        $rateLimitInfo['last_completed_at'] = $this->formatIsoTimestamp($parsedCompleted);
        $rateLimitInfo['next_available_at'] = $this->formatIsoTimestamp($finalNextAvailable);

        return [
            'success' => true,
            'router_ip' => $routerIp,
            'router_name' => $router['name'] ?? $routerIp,
            'server_ip' => $serverInfo['ip'],
            'server_label' => $serverInfo['label'] ?? $serverInfo['ip'],
            'server_source' => $serverInfo['source'] ?? null,
            'server_username' => $serverInfo['username'] ?? null,
            'options' => [
                'protocol' => strtoupper((string) ($payload['protocol'] ?? 'tcp')),
                'direction' => $directionInfo['display'],
                'direction_value' => $directionInfo['value'],
                'duration' => isset($payload['duration']) ? (int) $payload['duration'] : null,
                'connection_count' => isset($payload['connection-count']) ? (int) $payload['connection-count'] : null,
                'server_ip' => $serverInfo['ip'],
                'server_username' => $serverInfo['username'] ?? null,
            ],
            'summary' => $summary,
            'entries' => $entries,
            'started_at' => $resultStartedIso,
            'completed_at' => $resultCompletedIso,
            'interface' => $options['interface'] ?? ($router['preferred_interface'] ?? ''),
            'rate_limit' => $rateLimitInfo,
            'raw_response' => $result,
            'raw_replies' => $result['raw_replies'] ?? [],
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
                    'profiles' => [],
                    'error' => null,
                    'last_refreshed' => $timestamp,
                    'generated_at' => $timestamp,
                ];
            }

            $clientError = null;
            $client = $this->makeMikroTikClient($router, $clientError);

            if ($client === null) {
                $groupedSessions[$serverKey]['reachable'] = false;
                $groupedSessions[$serverKey]['error'] = $clientError;

                continue;
            }

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
            $activeSessionsByUser = [];

            foreach ($sessions as $session) {
                $detailedSession = $this->buildActivePppoeSession($router, $session, $secretsByName);

                $activeUsers[$detailedSession['user']] = true;
                $activeSessionsByUser[$detailedSession['user']] = $detailedSession;
                $flatSessions[] = $detailedSession;
                $groupedSessions[$serverKey]['sessions'][] = $detailedSession;
            }

            $profilesByName = [];

            $ensureProfile = static function (string $profileName) use (&$profilesByName): void {
                if (array_key_exists($profileName, $profilesByName)) {
                    return;
                }

                $label = trim($profileName) !== '' ? $profileName : 'Tanpa Profil';

                $profilesByName[$profileName] = [
                    'profile' => $profileName,
                    'name' => $label,
                    'total_users' => 0,
                    'active_count' => 0,
                    'inactive_count' => 0,
                    'users' => [],
                ];
            };

            foreach ($secrets as $secret) {
                $username = $secret['name'] ?? '';
                $profileKey = $secret['profile'] ?? '';

                if ($username === '') {
                    continue;
                }

                $ensureProfile($profileKey);

                if (isset($activeUsers[$username])) {
                    $sessionData = $activeSessionsByUser[$username] ?? null;

                    $userData = [
                        'user' => $username,
                        'profile' => $profileKey,
                        'service' => $secret['service'] ?? '',
                        'disabled' => (bool) ($secret['disabled'] ?? false),
                        'last_logged_out' => $secret['last_logged_out'] ?? '',
                        'comment' => $secret['comment'] ?? '',
                        'secret_id' => $secret['id'] ?? '',
                        'status' => 'active',
                        'address' => $sessionData['address'] ?? '-',
                        'uptime' => $sessionData['uptime'] ?? '',
                        'uptime_seconds' => $sessionData['uptime_seconds'] ?? 0,
                    ];

                    $profilesByName[$profileKey]['users'][] = $userData;
                    $profilesByName[$profileKey]['total_users']++;
                    $profilesByName[$profileKey]['active_count']++;

                    unset($activeSessionsByUser[$username]);

                    continue;
                }

                $inactiveUser = [
                    'user' => $username,
                    'profile' => $profileKey,
                    'service' => $secret['service'] ?? '',
                    'disabled' => (bool) ($secret['disabled'] ?? false),
                    'last_logged_out' => $secret['last_logged_out'] ?? '',
                    'comment' => $secret['comment'] ?? '',
                    'secret_id' => $secret['id'] ?? '',
                ];

                $groupedSessions[$serverKey]['inactive_users'][] = $inactiveUser;

                $profilesByName[$profileKey]['users'][] = $inactiveUser + [
                    'status' => 'inactive',
                    'address' => '-',
                    'uptime' => '',
                    'uptime_seconds' => 0,
                ];
                $profilesByName[$profileKey]['total_users']++;
                $profilesByName[$profileKey]['inactive_count']++;
            }

            foreach ($activeSessionsByUser as $sessionData) {
                $profileName = $sessionData['profile'] ?? '';

                $ensureProfile($profileName);

                $profilesByName[$profileName]['users'][] = [
                    'user' => $sessionData['user'],
                    'profile' => $profileName,
                    'service' => $sessionData['service'] ?? '',
                    'disabled' => (bool) ($sessionData['disabled'] ?? false),
                    'last_logged_out' => $sessionData['last_logged_out'] ?? '',
                    'comment' => $sessionData['comment'] ?? '',
                    'secret_id' => $sessionData['secret_id'] ?? '',
                    'status' => 'active',
                    'address' => $sessionData['address'] ?? '-',
                    'uptime' => $sessionData['uptime'] ?? '',
                    'uptime_seconds' => $sessionData['uptime_seconds'] ?? 0,
                ];
                $profilesByName[$profileName]['total_users']++;
                $profilesByName[$profileName]['active_count']++;
            }

            foreach ($profilesByName as $profileKey => &$profileSummary) {
                usort($profileSummary['users'], static function (array $a, array $b): int {
                    return strcasecmp($a['user'] ?? '', $b['user'] ?? '');
                });

                // Pastikan nilai status berada pada format yang konsisten.
                foreach ($profileSummary['users'] as &$user) {
                    $user['status'] = $user['status'] ?? 'inactive';
                }
                unset($user);
            }
            unset($profileSummary);

            uasort($profilesByName, static function (array $a, array $b): int {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });

            $groupedSessions[$serverKey]['profiles'] = array_values($profilesByName);

            $groupedSessions[$serverKey]['total_sessions'] = count($groupedSessions[$serverKey]['sessions']);
            $groupedSessions[$serverKey]['total_inactive'] = count($groupedSessions[$serverKey]['inactive_users']);
            $totalActiveSessions += $groupedSessions[$serverKey]['total_sessions'];
            $totalInactiveUsers += $groupedSessions[$serverKey]['total_inactive'];
        }

        $groupedSessionsList = array_values($groupedSessions);

        $this->cachedPppoeData = [
            'flat' => $flatSessions,
            'grouped' => $groupedSessionsList,
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
     * Mengubah data sesi PPPoE terkelompok menjadi direktori klien yang siap
     * dipilih saat menambahkan router.
     */
    private function buildPppoeDirectory(array $groupedSessions): array
    {
        $clients = [];

        foreach ($groupedSessions as $server) {
            $serverIp = $server['router_ip'] ?? '';
            $serverName = $server['router_name'] ?? $serverIp;
            $sessions = $server['sessions'] ?? [];
            $inactiveUsers = $server['inactive_users'] ?? [];

            foreach ($inactiveUsers as $user) {
                $username = $user['user'] ?? '';

                if ($username === '') {
                    continue;
                }

                $entry = [
                    'server_ip' => $serverIp,
                    'server_name' => $serverName,
                    'pppoe_username' => $username,
                    'client_name' => $user['comment'] ?? $username,
                    'address' => $user['address'] ?? '',
                    'profile' => $user['profile'] ?? '',
                    'status' => 'inactive',
                    'uptime' => '',
                    'uptime_seconds' => 0,
                    'last_logged_out' => $user['last_logged_out'] ?? '',
                    'disabled' => (bool) ($user['disabled'] ?? false),
                    'comment' => $user['comment'] ?? '',
                    'secret_id' => $user['secret_id'] ?? '',
                ];

                $entry['client_key'] = $this->normaliseRouterClientKey([
                    'pppoe_username' => $username,
                    'server_ip' => $serverIp,
                    'ip_address' => $entry['address'] ?? '',
                ]);

                $clients[] = $entry;
            }

            foreach ($sessions as $session) {
                $username = $session['user'] ?? '';

                if ($username === '') {
                    continue;
                }

                $existingIndex = null;

                foreach ($clients as $index => $client) {
                    if (
                        isset($client['pppoe_username'], $client['server_ip'])
                        && strcasecmp($client['pppoe_username'], $username) === 0
                        && strcasecmp((string) $client['server_ip'], (string) $serverIp) === 0
                    ) {
                        $existingIndex = $index;
                        break;
                    }
                }

                $entry = [
                    'server_ip' => $serverIp,
                    'server_name' => $serverName,
                    'pppoe_username' => $username,
                    'client_name' => $session['comment'] ?? $session['user'] ?? $username,
                    'address' => $session['address'] ?? '',
                    'profile' => $session['profile'] ?? '',
                    'status' => 'active',
                    'uptime' => $session['uptime'] ?? '',
                    'uptime_seconds' => $session['uptime_seconds'] ?? 0,
                    'last_logged_out' => $session['last_logged_out'] ?? '',
                    'disabled' => (bool) ($session['disabled'] ?? false),
                    'comment' => $session['comment'] ?? '',
                    'secret_id' => $session['secret_id'] ?? '',
                ];

                $entry['client_key'] = $this->normaliseRouterClientKey([
                    'pppoe_username' => $username,
                    'server_ip' => $serverIp,
                    'ip_address' => $entry['address'] ?? '',
                ]);

                if ($existingIndex !== null) {
                    $existingKey = $clients[$existingIndex]['client_key'] ?? $entry['client_key'];
                    $entry['client_key'] = $existingKey;
                    $clients[$existingIndex] = array_merge($clients[$existingIndex], $entry);
                } else {
                    $clients[] = $entry;
                }
            }
        }

        usort($clients, static function (array $a, array $b): int {
            $serverCompare = strcasecmp($a['server_name'] ?? '', $b['server_name'] ?? '');

            if ($serverCompare !== 0) {
                return $serverCompare;
            }

            return strcasecmp($a['client_name'] ?? '', $b['client_name'] ?? '');
        });

        return $clients;
    }

    /**
     * Mengambil direktori akun PPPoE dari seluruh server untuk keperluan
     * pemilihan manual.
     */
    public function getPppoeClientDirectory(bool $refresh = false): array
    {
        if ($refresh) {
            $this->cachedPppoeData = null;
        }

        $pppoeData = $this->collectPppoeSessions();
        $grouped = $pppoeData['grouped'] ?? [];
        $clients = $this->buildPppoeDirectory($grouped);

        return [
            'generated_at' => $pppoeData['totals']['generated_at'] ?? date('c'),
            'total_servers' => count($grouped),
            'total_clients' => count($clients),
            'clients' => $clients,
        ];
    }

    /**
     * Mencari detail PPPoE berdasarkan identifier normalisasi.
     */
    public function findPppoeClientByKey(string $clientKey, bool $refresh = false): ?array
    {
        $clientKey = strtolower(trim($clientKey));

        if ($clientKey === '') {
            return null;
        }

        $directory = $this->getPppoeClientDirectory($refresh);
        $clients = $directory['clients'] ?? [];

        foreach ($clients as $client) {
            if (!is_array($client)) {
                continue;
            }

            $key = strtolower((string) ($client['client_key'] ?? $this->normaliseRouterClientKey($client)));

            if ($key === $clientKey) {
                $client['client_key'] = $key;

                return $client;
            }
        }

        return null;
    }

    /**
     * Mengambil kapasitas interface yang paling relevan untuk sebuah router.
     */
    private function extractInterfaceCapacityMbps(array $interfaces, string $preferredName = ''): ?float
    {
        $preferredName = trim($preferredName);

        if ($preferredName !== '') {
            foreach ($interfaces as $interface) {
                if (strcasecmp((string) ($interface['name'] ?? ''), $preferredName) === 0) {
                    $capacity = $this->normaliseInterfaceCapacityMbps($interface);

                    if ($capacity !== null) {
                        return $capacity;
                    }
                }
            }
        }

        foreach ($interfaces as $interface) {
            $capacity = $this->normaliseInterfaceCapacityMbps($interface);

            if ($capacity !== null) {
                return $capacity;
            }
        }

        return null;
    }

    /**
     * Membersihkan nilai kapasitas interface menjadi angka Mbps.
     */
    private function normaliseInterfaceCapacityMbps(array $interface): ?float
    {
        $candidates = [
            $interface['if_speed_mbps'] ?? null,
            $interface['link_capacity_mbps'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                $numeric = (float) $candidate;

                if ($numeric > 0) {
                    return round($numeric, 2);
                }
            }
        }

        $bpsCandidates = [
            $interface['if_speed_bps'] ?? null,
        ];

        foreach ($bpsCandidates as $candidate) {
            if (is_numeric($candidate)) {
                $numeric = (float) $candidate;

                if ($numeric > 0) {
                    return round($numeric / 1_000_000, 2);
                }
            }
        }

        if (!empty($interface['if_speed'])) {
            $parsed = $this->parseCapacityLabelToMbps($interface['if_speed']);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Mengonversi string kapasitas RouterOS (mis. "1Gbps") menjadi Mbps.
     */
    private function parseCapacityLabelToMbps($value): ?float
    {
        if (is_numeric($value)) {
            $numeric = (float) $value;

            if ($numeric <= 0) {
                return null;
            }

            // Angka besar dianggap bps dan diubah ke Mbps.
            if ($numeric > 10_000) {
                return round($numeric / 1_000_000, 2);
            }

            return round($numeric, 2);
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (!preg_match('/([\d.,]+)/', $text, $matches)) {
            return null;
        }

        $numeric = (float) str_replace(',', '.', $matches[1]);

        if ($numeric <= 0) {
            return null;
        }

        $lower = strtolower($text);
        $multiplier = 1_000_000; // default Mbps

        if (strpos($lower, 'tbps') !== false || strpos($lower, 'tbit') !== false || preg_match('/t$/', $lower)) {
            $multiplier = 1_000_000_000_000;
        } elseif (strpos($lower, 'gbps') !== false || strpos($lower, 'gbit') !== false || preg_match('/g$/', $lower)) {
            $multiplier = 1_000_000_000;
        } elseif (strpos($lower, 'mbps') !== false || strpos($lower, 'mbit') !== false || preg_match('/m$/', $lower)) {
            $multiplier = 1_000_000;
        } elseif (strpos($lower, 'kbps') !== false || strpos($lower, 'kbit') !== false || preg_match('/k$/', $lower)) {
            $multiplier = 1_000;
        } elseif (strpos($lower, 'bps') !== false || strpos($lower, 'bit') !== false) {
            $multiplier = 1;
        }

        $bps = $numeric * $multiplier;

        if ($bps <= 0) {
            return null;
        }

        return round($bps / 1_000_000, 2);
    }

    private function normaliseRouterClientKey(array $data): string
    {
        if (!empty($data['client_key'])) {
            return strtolower((string) $data['client_key']);
        }

        $username = strtolower((string) ($data['pppoe_username'] ?? $data['username'] ?? ''));
        $serverIp = strtolower((string) ($data['server_ip'] ?? ''));

        if ($username !== '') {
            return $serverIp !== '' ? $username . '@' . $serverIp : $username;
        }

        $ipAddress = strtolower((string) ($data['ip_address'] ?? $data['client_address'] ?? ''));

        if ($ipAddress !== '') {
            return $ipAddress;
        }

        return strtolower(uniqid('client_', true));
    }

    private function resolveRouterCredentialsForIp(string $ipAddress): ?array
    {
        $ipAddress = trim($ipAddress);

        if ($ipAddress === '') {
            return null;
        }

        $snapshot = $this->getRouterClientSnapshot();

        $normalise = function (array $entry, string $ip) {
            $username = $entry['user']
                ?? $entry['username']
                ?? $entry['pppoe_username']
                ?? self::DEFAULT_CLIENT_USERNAME;
            $password = $entry['pass']
                ?? $entry['password']
                ?? self::DEFAULT_CLIENT_PASSWORD;

            $record = [
                'name' => $entry['name']
                    ?? $entry['router_name']
                    ?? $entry['client_name']
                    ?? $ip,
                'ip_address' => $ip,
                'username' => $username,
                'password' => $password,
                'notes' => $entry['notes'] ?? $entry['comment'] ?? '',
                'server_ip' => $entry['server_ip'] ?? '',
                'server_name' => $entry['server_name'] ?? '',
                'preferred_interface' => $entry['preferred_interface'] ?? ($entry['iface'] ?? ''),
            ];

            if (isset($entry['bandwidth_user'])) {
                $record['bandwidth_user'] = $entry['bandwidth_user'];
            }

            if (isset($entry['bandwidth_password'])) {
                $record['bandwidth_password'] = $entry['bandwidth_password'];
            }

            $record['client_key'] = $entry['client_key'] ?? $this->normaliseRouterClientKey([
                'ip_address' => $ip,
                'username' => $username,
                'server_ip' => $record['server_ip'],
            ]);

            return $record;
        };

        if (isset($snapshot['routers']) && is_array($snapshot['routers'])) {
            foreach ($snapshot['routers'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryIp = trim((string) ($entry['ip'] ?? $entry['ip_address'] ?? ''));

                if ($entryIp === $ipAddress) {
                    return $normalise($entry, $entryIp);
                }
            }
        }

        if (isset($snapshot['clients']) && is_array($snapshot['clients'])) {
            foreach ($snapshot['clients'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryIp = trim((string) ($entry['ip_address'] ?? $entry['client_address'] ?? ''));

                if ($entryIp === $ipAddress) {
                    return $normalise($entry, $entryIp);
                }
            }
        }

        $repositoryRouter = $this->findRouterByIp($ipAddress);

        if ($repositoryRouter !== null) {
            return $normalise($repositoryRouter, $ipAddress);
        }

        return null;
    }

    private function determineBandwidthServer(string $routerIp): array
    {
        $serverIp = $this->mapRouterIpToServerIp($routerIp);

        if ($serverIp === null) {
            return ['ip' => null, 'label' => null, 'source' => null];
        }

        $server = $this->findBandwidthServerByIp($serverIp);

        if ($server === null) {
            return [
                'ip' => $serverIp,
                'label' => $serverIp,
                'source' => 'network_prefix',
                'username' => null,
                'password' => null,
            ];
        }

        return [
            'ip' => $serverIp,
            'label' => $server['name'] ?? $serverIp,
            'source' => 'network_prefix',
            'username' => $server['username'] ?? null,
            'password' => $server['password'] ?? null,
        ];
    }

    private function mapRouterIpToServerIp(string $routerIp): ?string
    {
        $routerIp = trim($routerIp);

        if ($routerIp === '') {
            return null;
        }

        if (preg_match('/^172\.16\.30\./', $routerIp)) {
            return '172.16.30.1';
        }

        if (preg_match('/^172\.16\.40\./', $routerIp)) {
            return '172.16.40.1';
        }

        return null;
    }

    private function findBandwidthServerByIp(string $serverIp): ?array
    {
        $servers = $this->loadBandwidthServers();

        return $servers[$serverIp] ?? null;
    }

    private function loadBandwidthServers(): array
    {
        if ($this->cachedBandwidthServers !== null) {
            return $this->cachedBandwidthServers;
        }

        if (!file_exists($this->serverStoragePath)) {
            $this->cachedBandwidthServers = [];

            return $this->cachedBandwidthServers;
        }

        $raw = file_get_contents($this->serverStoragePath);
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            $this->cachedBandwidthServers = [];

            return $this->cachedBandwidthServers;
        }

        $servers = [];
        $entries = [];

        if (isset($decoded['servers']) && is_array($decoded['servers'])) {
            $entries = $decoded['servers'];
        } elseif (isset($decoded[0])) {
            $entries = $decoded;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $ip = trim((string) ($entry['ip'] ?? $entry['address'] ?? ''));

            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }

            $servers[$ip] = [
                'name' => $entry['name'] ?? $entry['label'] ?? $ip,
                'username' => $entry['username'] ?? $entry['user'] ?? null,
                'password' => $entry['password'] ?? $entry['pass'] ?? null,
            ];
        }

        $this->cachedBandwidthServers = $servers;

        return $this->cachedBandwidthServers;
    }

    private function summariseBandwidthEntries(array $entries, array $result): array
    {
        $txCurrent = (int) ($result['tx_current_bps'] ?? 0);
        $rxCurrent = (int) ($result['rx_current_bps'] ?? 0);
        $txAverage = (int) ($result['tx_total_average_bps'] ?? 0);
        $rxAverage = (int) ($result['rx_total_average_bps'] ?? 0);
        $txPeak = (int) ($result['tx_peak_bps'] ?? 0);
        $rxPeak = (int) ($result['rx_peak_bps'] ?? 0);

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryTxCandidates = [
                (int) ($entry['tx_current_bps'] ?? 0),
                (int) ($entry['tx_total_average_bps'] ?? 0),
                (int) ($entry['tcp_write_bps'] ?? 0),
                (int) ($entry['udp_write_bps'] ?? 0),
            ];
            $entryRxCandidates = [
                (int) ($entry['rx_current_bps'] ?? 0),
                (int) ($entry['rx_total_average_bps'] ?? 0),
                (int) ($entry['tcp_read_bps'] ?? 0),
                (int) ($entry['udp_read_bps'] ?? 0),
            ];

            $entryTxCurrent = max($entryTxCandidates);
            $entryRxCurrent = max($entryRxCandidates);
            $entryTxAverage = max((int) ($entry['tx_total_average_bps'] ?? 0), (int) ($entry['tcp_write_bps'] ?? 0), (int) ($entry['udp_write_bps'] ?? 0));
            $entryRxAverage = max((int) ($entry['rx_total_average_bps'] ?? 0), (int) ($entry['tcp_read_bps'] ?? 0), (int) ($entry['udp_read_bps'] ?? 0));

            if ($entryTxCurrent > 0) {
                $txCurrent = $entryTxCurrent;
            }

            if ($entryRxCurrent > 0) {
                $rxCurrent = $entryRxCurrent;
            }

            if ($entryTxAverage > 0) {
                $txAverage = $entryTxAverage;
            }

            if ($entryRxAverage > 0) {
                $rxAverage = $entryRxAverage;
            }

            $txPeak = max($txPeak, ...$entryTxCandidates);
            $rxPeak = max($rxPeak, ...$entryRxCandidates);
        }

        return [
            'tx_current_bps' => $txCurrent,
            'tx_current_label' => $this->formatBitsPerSecond($txCurrent),
            'rx_current_bps' => $rxCurrent,
            'rx_current_label' => $this->formatBitsPerSecond($rxCurrent),
            'tx_total_average_bps' => $txAverage,
            'tx_total_average_label' => $this->formatBitsPerSecond($txAverage),
            'rx_total_average_bps' => $rxAverage,
            'rx_total_average_label' => $this->formatBitsPerSecond($rxAverage),
            'tx_peak_bps' => $txPeak,
            'tx_peak_label' => $this->formatBitsPerSecond($txPeak),
            'rx_peak_bps' => $rxPeak,
            'rx_peak_label' => $this->formatBitsPerSecond($rxPeak),
        ];
    }

    private function normaliseBandwidthDirection($direction): array
    {
        $value = strtolower(trim((string) $direction));

        switch ($value) {
            case 'tx':
            case 'transmit':
                return [
                    'value' => 'tx',
                    'router' => 'transmit',
                    'display' => 'TX',
                ];
            case 'rx':
            case 'receive':
                return [
                    'value' => 'rx',
                    'router' => 'receive',
                    'display' => 'RX',
                ];
            case 'both':
            case 'txrx':
            case 'rtx':
                return [
                    'value' => 'both',
                    'router' => 'both',
                    'display' => 'BOTH',
                ];
        }

        if ($value === '') {
            return [
                'value' => 'both',
                'router' => 'both',
                'display' => 'BOTH',
            ];
        }

        return [
            'value' => 'both',
            'router' => 'both',
            'display' => strtoupper($value),
        ];
    }

    private function formatBitsPerSecond(int $bitsPerSecond): string
    {
        if ($bitsPerSecond <= 0) {
            return '0 bps';
        }

        $units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        $exponent = (int) floor(log($bitsPerSecond, 1000));
        $exponent = max(0, min($exponent, count($units) - 1));
        $value = $bitsPerSecond / (1000 ** $exponent);

        return sprintf('%s %s', $exponent === 0 ? number_format($value, 0, '.', '') : number_format($value, 2, '.', ''), $units[$exponent]);
    }

    private function ensureRouterClientKey(array &$client, array &$usedKeys): string
    {
        $baseKey = $this->normaliseRouterClientKey($client);
        $candidate = $baseKey;
        $suffix = 1;

        while (in_array($candidate, $usedKeys, true)) {
            $candidate = $baseKey . '#' . $suffix;
            $suffix++;
        }

        $usedKeys[] = $candidate;
        $client['client_key'] = $candidate;

        return $candidate;
    }

    /**
     * Membaca isi penyimpanan router client dari file JSON.
     */
    private function readRouterClientStorage(): array
    {
        $empty = [
            'generated_at' => null,
            'total_clients' => 0,
            'total_routers' => 0,
            'clients' => [],
            'routers' => [],
        ];

        if (!is_file($this->clientStoragePath)) {
            return $empty;
        }

        $contents = @file_get_contents($this->clientStoragePath);

        if ($contents === false) {
            return $empty;
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return $empty;
        }

        $clients = [];
        $routers = [];
        $usedKeys = [];
        $needsRewrite = false;

        $sourceEntries = [];

        if (isset($decoded['routers']) && is_array($decoded['routers'])) {
            $sourceEntries = $decoded['routers'];
        } elseif (isset($decoded['clients']) && is_array($decoded['clients'])) {
            $sourceEntries = $decoded['clients'];
        }

        foreach ($sourceEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $normalised = $this->normaliseRouterClientRecord($entry, $usedKeys);

            if ($normalised === null) {
                continue;
            }

            $clients[] = $normalised['client'];
            $routers[] = $normalised['router'];

            if (!empty($normalised['rewritten'])) {
                $needsRewrite = true;
            }
        }

        if (empty($sourceEntries) && isset($decoded['clients']) && is_array($decoded['clients'])) {
            // Kompatibilitas lama: salin entri apa adanya ketika tidak ada data
            // yang valid setelah normalisasi.
            $clients = $decoded['clients'];
        }

        $snapshot = [
            'generated_at' => $decoded['generated_at'] ?? null,
            'total_clients' => $decoded['total_clients'] ?? count($clients),
            'total_routers' => $decoded['total_routers'] ?? count($routers),
            'clients' => $clients,
            'routers' => $routers,
        ];

        if ($needsRewrite) {
            $this->writeRouterClientStorage($snapshot);
        }

        return $snapshot;
    }

    /**
     * Menyatukan struktur entri router client dari berbagai format JSON.
     *
     * @return array{client: array, router: array, rewritten?: bool}|null
     */
    private function normaliseRouterClientRecord(array $entry, array &$usedKeys): ?array
    {
        $client = $entry;

        $ipAddress = trim((string) ($entry['ip'] ?? $entry['ip_address'] ?? $entry['client_address'] ?? $entry['address'] ?? ''));

        if ($ipAddress === '') {
            return null;
        }

        $name = (string) ($entry['name'] ?? $entry['client_name'] ?? $entry['router_name'] ?? $ipAddress);
        $username = (string) ($entry['user'] ?? $entry['username'] ?? self::DEFAULT_CLIENT_USERNAME);
        $password = (string) ($entry['pass'] ?? $entry['password'] ?? self::DEFAULT_CLIENT_PASSWORD);
        $iface = (string) ($entry['iface'] ?? $entry['preferred_interface'] ?? '');

        $client['name'] = $name;
        $client['client_name'] = $client['client_name'] ?? $name;
        $client['ip_address'] = $ipAddress;
        $client['username'] = $username;
        $client['password'] = $password;
        $client['iface'] = $iface;

        if (!isset($client['preferred_interface']) && $iface !== '') {
            $client['preferred_interface'] = $iface;
        }

        $existingKey = strtolower((string) ($client['client_key'] ?? ''));
        $rewritten = false;

        if ($existingKey === '' || in_array($existingKey, $usedKeys, true)) {
            $this->ensureRouterClientKey($client, $usedKeys);
            $rewritten = true;
        } else {
            $usedKeys[] = $existingKey;
            $client['client_key'] = $existingKey;
        }

        $router = [
            'ip' => $ipAddress,
            'user' => $username,
            'pass' => $password,
            'name' => $name,
            'iface' => $iface,
            'notes' => $client['notes'] ?? '',
            'client_key' => $client['client_key'],
        ];

        $preferredInterface = $client['preferred_interface'] ?? $iface;

        if ($preferredInterface !== null && $preferredInterface !== '') {
            $router['preferred_interface'] = $preferredInterface;
        }

        foreach ([
            'server_ip',
            'server_name',
            'pppoe_username',
            'profile',
            'status',
            'address',
            'comment',
            'last_logged_out',
            'secret_id',
        ] as $field) {
            if (array_key_exists($field, $client) && $client[$field] !== '' && $client[$field] !== null) {
                $router[$field] = $client[$field];
            }
        }

        return [
            'client' => $client,
            'router' => $router,
            'rewritten' => $rewritten,
        ];
    }

    /**
     * Menuliskan data router client ke penyimpanan JSON.
     */
    private function writeRouterClientStorage(array $payload): void
    {
        $directory = dirname($this->clientStoragePath);

        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0777, true) && !is_dir($directory)) {
                return;
            }
        }

        $clients = array_values($payload['clients'] ?? []);

        usort($clients, static function (array $a, array $b): int {
            $serverCompare = strcasecmp($a['server_name'] ?? '', $b['server_name'] ?? '');

            if ($serverCompare !== 0) {
                return $serverCompare;
            }

            $labelA = $a['name'] ?? $a['client_name'] ?? $a['pppoe_username'] ?? '';
            $labelB = $b['name'] ?? $b['client_name'] ?? $b['pppoe_username'] ?? '';

            return strcasecmp($labelA, $labelB);
        });

        $routers = $this->buildRouterEntriesFromClients($clients);

        $persistPayload = [
            'generated_at' => $payload['generated_at'] ?? date('c'),
            'total_clients' => count($clients),
            'total_routers' => count($routers),
            'routers' => $routers,
        ];

        @file_put_contents(
            $this->clientStoragePath,
            json_encode($persistPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Membentuk ulang entri router dari daftar klien yang tersimpan.
     *
     * @param array<int, array> $clients
     * @return array<int, array>
     */
    private function buildRouterEntriesFromClients(array $clients): array
    {
        $routers = [];

        foreach ($clients as $client) {
            if (!is_array($client)) {
                continue;
            }

            $ip = trim((string) ($client['ip'] ?? $client['ip_address'] ?? $client['address'] ?? ''));

            if ($ip === '') {
                continue;
            }

            $username = (string) ($client['user'] ?? $client['username'] ?? self::DEFAULT_CLIENT_USERNAME);
            $password = (string) ($client['pass'] ?? $client['password'] ?? self::DEFAULT_CLIENT_PASSWORD);
            $name = (string) ($client['name'] ?? $client['client_name'] ?? $ip);
            $iface = (string) ($client['iface'] ?? $client['preferred_interface'] ?? '');

            $router = [
                'ip' => $ip,
                'user' => $username,
                'pass' => $password,
                'name' => $name,
                'iface' => $iface,
                'notes' => $client['notes'] ?? '',
                'client_key' => $client['client_key'] ?? $this->normaliseRouterClientKey($client),
            ];

            if ($iface !== '') {
                $router['preferred_interface'] = $client['preferred_interface'] ?? $iface;
            } else {
                unset($router['preferred_interface']);
            }

            foreach ([
                'server_ip',
                'server_name',
                'pppoe_username',
                'profile',
                'status',
                'address',
                'comment',
                'last_logged_out',
                'secret_id',
            ] as $field) {
                if (array_key_exists($field, $client) && $client[$field] !== '' && $client[$field] !== null) {
                    $router[$field] = $client[$field];
                }
            }

            $routers[] = $router;
        }

        usort($routers, static function (array $a, array $b): int {
            $nameCompare = strcasecmp($a['name'] ?? '', $b['name'] ?? '');

            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return strcasecmp($a['ip'] ?? '', $b['ip'] ?? '');
        });

        return $routers;
    }

    /**
     * Menyelaraskan preferensi interface pada repository utama.
     */
    private function repositoryUpdatePreferredInterface(string $ipAddress, string $interfaceName): void
    {
        $ipAddress = trim($ipAddress);

        if ($ipAddress === '' || !method_exists($this->repository, 'updatePreferredInterface')) {
            return;
        }

        $preferred = trim($interfaceName);

        $this->repository->updatePreferredInterface(
            $ipAddress,
            $preferred !== '' ? $preferred : null
        );
    }

    /**
     * Mendaftarkan router client baru berdasarkan pilihan PPPoE pengguna.
     */
    public function registerRouterClient(array $routerData, array $pppoeData = []): array
    {
        $snapshot = $this->readRouterClientStorage();
        $clients = $snapshot['clients'] ?? [];
        $clientsByKey = [];

        foreach ($clients as $client) {
            if (!is_array($client)) {
                continue;
            }

            $key = strtolower((string) ($client['client_key'] ?? ''));

            if ($key === '') {
                $key = $this->normaliseRouterClientKey($client);
            }

            $client['client_key'] = $key;
            $clientsByKey[$key] = $client;
        }

        $name = $routerData['name'] ?? $pppoeData['client_name'] ?? ($pppoeData['pppoe_username'] ?? ($routerData['ip_address'] ?? 'Router'));
        $ipAddress = $routerData['ip_address'] ?? $pppoeData['address'] ?? '';

        $preferredInterface = $routerData['preferred_interface']
            ?? $routerData['iface']
            ?? $pppoeData['preferred_interface']
            ?? $pppoeData['interface']
            ?? $pppoeData['iface']
            ?? '';

        $entry = [
            'name' => $name,
            'client_name' => $pppoeData['client_name'] ?? $name,
            'ip_address' => $ipAddress,
            'username' => $routerData['username'] ?? self::DEFAULT_CLIENT_USERNAME,
            'password' => $routerData['password'] ?? self::DEFAULT_CLIENT_PASSWORD,
            'notes' => $routerData['notes'] ?? '',
            'server_ip' => $pppoeData['server_ip'] ?? '',
            'server_name' => $pppoeData['server_name'] ?? '',
            'pppoe_username' => $pppoeData['pppoe_username'] ?? ($pppoeData['username'] ?? ''),
            'profile' => $pppoeData['profile'] ?? '',
            'status' => $pppoeData['status'] ?? '',
            'address' => $pppoeData['address'] ?? '',
            'comment' => $pppoeData['comment'] ?? '',
            'last_logged_out' => $pppoeData['last_logged_out'] ?? '',
            'secret_id' => $pppoeData['secret_id'] ?? '',
            'added_at' => date('c'),
        ];

        if ($preferredInterface !== '') {
            $entry['preferred_interface'] = $preferredInterface;
            $entry['iface'] = $preferredInterface;
        }

        if ($entry['ip_address'] === '' && $entry['address'] !== '') {
            $entry['ip_address'] = $entry['address'];
        }

        if (!empty($pppoeData['client_key'])) {
            $entry['client_key'] = strtolower((string) $pppoeData['client_key']);
        }

        $entryKey = $this->normaliseRouterClientKey($entry);
        $candidateKey = $entryKey;
        $suffix = 1;

        while (isset($clientsByKey[$candidateKey])) {
            $candidateKey = $entryKey . '#' . $suffix;
            $suffix++;
        }

        $entry['client_key'] = $candidateKey;
        $clientsByKey[$candidateKey] = array_merge($clientsByKey[$candidateKey] ?? [], $entry);

        $snapshot['clients'] = array_values($clientsByKey);
        $snapshot['generated_at'] = date('c');
        $snapshot['total_clients'] = count($snapshot['clients']);

        $this->writeRouterClientStorage($snapshot);

        return [
            'success' => true,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * Memperbarui interface pilihan untuk router client yang tersimpan.
     */
    public function updateRouterClientPreferredInterface(
        ?string $clientKey,
        ?string $ipAddress,
        string $interfaceName
    ): array {
        $clientKey = strtolower(trim((string) ($clientKey ?? '')));
        $ipAddress = trim((string) ($ipAddress ?? ''));
        $interfaceName = (string) $interfaceName;

        if ($clientKey === '' && $ipAddress === '') {
            return [
                'success' => false,
                'message' => 'Client key atau alamat IP wajib diisi.',
            ];
        }

        $snapshot = $this->readRouterClientStorage();
        $clients = $snapshot['clients'] ?? [];
        $matchedIndex = null;

        foreach ($clients as $index => $client) {
            if (!is_array($client)) {
                continue;
            }

            $normalisedKey = strtolower((string) ($client['client_key'] ?? $this->normaliseRouterClientKey($client)));
            $clientIp = trim((string) ($client['ip_address'] ?? $client['address'] ?? $client['ip'] ?? ''));

            if ($normalisedKey !== '') {
                $clients[$index]['client_key'] = $normalisedKey;
            }

            $keyMatches = $clientKey !== '' && $normalisedKey === $clientKey;
            $ipMatches = !$keyMatches
                && $clientKey === ''
                && $ipAddress !== ''
                && $clientIp !== ''
                && strcasecmp($clientIp, $ipAddress) === 0;

            if (!$keyMatches && !$ipMatches) {
                continue;
            }

            $matchedIndex = $index;
            $preferred = trim($interfaceName);

            if ($preferred === '') {
                unset($clients[$index]['preferred_interface'], $clients[$index]['iface']);
            } else {
                $clients[$index]['preferred_interface'] = $preferred;
                $clients[$index]['iface'] = $preferred;
            }

            $clients[$index]['updated_at'] = date('c');

            if ($ipAddress === '' && $clientIp !== '') {
                $ipAddress = $clientIp;
            }

            break;
        }

        if ($matchedIndex === null) {
            return [
                'success' => false,
                'message' => 'Router client tidak ditemukan.',
            ];
        }

        $snapshot['clients'] = $clients;
        $snapshot['routers'] = $this->buildRouterEntriesFromClients($clients);
        $snapshot['generated_at'] = date('c');
        $snapshot['total_clients'] = count($clients);
        $snapshot['total_routers'] = count($snapshot['routers']);

        $this->writeRouterClientStorage($snapshot);

        if ($ipAddress !== '') {
            $this->repositoryUpdatePreferredInterface($ipAddress, trim($interfaceName));
        }

        return [
            'success' => true,
            'message' => trim($interfaceName) === ''
                ? 'Pilihan interface dihapus.'
                : 'Pilihan interface disimpan.',
            'snapshot' => $this->readRouterClientStorage(),
        ];
    }

    /**
     * Menghapus router client dari snapshot penyimpanan manual.
     */
    public function removeRouterClient(string $clientKey, ?string $ipAddress = null): array
    {
        $clientKey = strtolower(trim($clientKey));

        if ($clientKey === '') {
            return [
                'success' => false,
                'message' => 'Identifier router client tidak valid.',
            ];
        }

        $snapshot = $this->readRouterClientStorage();
        $clients = $snapshot['clients'] ?? [];
        $filtered = [];
        $removed = false;

        foreach ($clients as $client) {
            if (!is_array($client)) {
                continue;
            }

            $key = strtolower((string) ($client['client_key'] ?? $this->normaliseRouterClientKey($client)));

            if ($key === $clientKey) {
                $removed = true;
                continue;
            }

            $filtered[] = $client;
        }

        if (!$removed) {
            return [
                'success' => false,
                'message' => 'Router client tidak ditemukan.',
            ];
        }

        $payload = [
            'generated_at' => date('c'),
            'clients' => $filtered,
        ];

        $this->writeRouterClientStorage($payload);

        if (!empty($ipAddress)) {
            $this->repository->removeByIp($ipAddress);
        }

        return ['success' => true];
    }

    /**
     * Mengambil snapshot router client tanpa penyegaran otomatis dari server
     * PPPoE.
     */
    public function getRouterClientSnapshot(bool $refresh = false): array
    {
        if ($refresh) {
            // Mode refresh kini hanya mengembalikan isi file tanpa mengambil
            // ulang dari server PPPoE secara otomatis.
        }

        return $this->readRouterClientStorage();
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

        $secret = $secretsByName[$username] ?? [];
        $secretId = $secret['id'] ?? '';

        $disabled = (bool) ($secret['disabled'] ?? false);
        $lastLoggedOut = $secret['last_logged_out'] ?? '';
        $comment = $secret['comment'] ?? '';

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
            'disabled' => $disabled,
            'last_logged_out' => $lastLoggedOut,
            'comment' => $comment,
            'status' => 'active',
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

        $clientError = null;
        $client = $this->makeMikroTikClient($router, $clientError);

        if ($client === null) {
            return [
                'success' => false,
                'message' => $clientError ?? 'Kelas MikroTikClient tidak ditemukan.',
            ];
        }

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
     * Membuat instansi MikroTikClient secara aman. Method ini memastikan class
     * sudah dimuat serta kredensial minimum tersedia. Apabila dependensi belum
     * ada, maka akan mengembalikan null dan menyertakan pesan kesalahan.
     *
     * @return MikroTikClient|null
     */
    private function makeMikroTikClient(array $router, ?string &$error = null)
    {
        if (!class_exists('MikroTikClient', false)) {
            $clientPath = __DIR__ . '/MikroTikClient.php';

            if (file_exists($clientPath)) {
                require_once $clientPath;
            }
        }

        if (!class_exists('MikroTikClient')) {
            $error = 'Dependensi MikroTikClient belum dimuat. Pastikan file `includes/MikroTikClient.php` tersedia.';

            return null;
        }

        $ipAddress = trim((string) ($router['ip_address'] ?? ''));

        if ($ipAddress === '') {
            $error = 'Alamat IP router tidak tersedia.';

            return null;
        }

        $username = (string) ($router['username'] ?? self::DEFAULT_CLIENT_USERNAME);
        $password = (string) ($router['password'] ?? self::DEFAULT_CLIENT_PASSWORD);
        $port = isset($router['port']) ? (int) $router['port'] : 8728;
        $timeout = isset($router['timeout']) ? (int) $router['timeout'] : 3;

        return new MikroTikClient($ipAddress, $username, $password, $port, $timeout);
    }

    /**
     * Menyusun daftar file yang terlibat saat memuat data trafik interface.
     */
    private function describeInterfaceDataFiles(bool $usingClientSnapshot): array
    {
        $projectRoot = realpath(__DIR__ . '/..');
        $rootPath = $projectRoot !== false ? $projectRoot : dirname(__DIR__);

        $paths = [
            'client_snapshot' => $this->clientStoragePath,
            'router_storage' => method_exists($this->repository, 'getStoragePath')
                ? $this->repository->getStoragePath()
                : null,
            'api_endpoint' => $rootPath . '/public/api/interfaces.php',
            'service_class' => __FILE__,
            'client_class' => __DIR__ . '/MikroTikClient.php',
        ];

        $display = [];

        foreach ($paths as $key => $path) {
            if (!$path) {
                continue;
            }

            $display[$key] = $this->formatRelativePath($path);
        }

        $display['active_source'] = $usingClientSnapshot ? 'router_client.json' : 'routers.json';

        return $display;
    }

    /**
     * Mengubah path absolut menjadi relatif terhadap akar proyek.
     */
    private function formatRelativePath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $projectRoot = realpath(__DIR__ . '/..');

        if ($projectRoot !== false) {
            $normalizedRoot = str_replace('\\', '/', $projectRoot);

            if (strpos($normalizedPath, $normalizedRoot) === 0) {
                $relative = ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');

                return $relative !== '' ? $relative : basename($normalizedPath);
            }
        }

        return $normalizedPath;
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
