<?php

// Muat autoloader Composer jika tersedia agar pustaka RouterOS dapat
// digunakan tanpa konfigurasi tambahan.
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

use RouterOS\Client as RouterOSClient;
use RouterOS\Query;

/**
 * MikroTikClient mengelola koneksi ke RouterOS API menggunakan pustaka
 * `evilfreelancer/routeros-api-php`. Kelas ini menangani proses autentikasi,
 * eksekusi perintah, serta pengambilan data PPPoE aktif dari perangkat
 * Mikrotik yang telah diregistrasikan.
 */
class MikroTikClient
{
    /** @var string $host Alamat IP atau hostname router. */
    private string $host;

    /** @var string $username Nama pengguna RouterOS. */
    private string $username;

    /** @var string $password Kata sandi RouterOS. */
    private string $password;

    /** @var int $port Port API RouterOS. Nilai bawaan 8728. */
    private int $port;

    /** @var int $timeout Batas waktu koneksi dalam detik. */
    private int $timeout;

    /** @var RouterOSClient|null $client Instansi klien RouterOS aktif. */
    private $client = null;

    /** @var string|null $lastError Pesan kesalahan terakhir yang terjadi. */
    private ?string $lastError = null;

    /**
     * Konstruktor menyimpan kredensial yang diperlukan untuk mengakses API
     * RouterOS. Parameter port dan timeout bersifat opsional agar mudah
     * disesuaikan sesuai kebutuhan jaringan.
     */
    public function __construct(string $host, string $username, string $password, int $port = 8728, int $timeout = 3)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    /**
     * Menginisialisasi koneksi ke RouterOS. Ketika pustaka belum dipasang atau
     * kredensial tidak valid, metode ini akan mengembalikan false dan menyimpan
     * pesan kesalahan agar dapat ditampilkan ke pengguna.
     */
    public function connect(): bool
    {
        if ($this->client !== null) {
            return true;
        }

        if (!class_exists(RouterOSClient::class)) {
            $this->lastError = 'Pustaka RouterOS belum terpasang. Jalankan `composer install` untuk mengunduh dependensi.';

            return false;
        }

        try {
            $this->client = new RouterOSClient([
                'host' => $this->host,
                'user' => $this->username,
                'pass' => $this->password,
                'port' => $this->port,
                'timeout' => $this->timeout,
            ]);

            $this->lastError = null;

            return true;
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();
            $this->client = null;

            return false;
        }
    }

    /**
     * Mengambil pesan kesalahan terakhir ketika koneksi atau permintaan gagal.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Mengirimkan perintah RouterOS dengan memanfaatkan objek Query bawaan
     * pustaka EvilFreelancer. Perintah dapat ditulis menggunakan format CLI
     * (misal `/system resource print`) dan otomatis dinormalisasi menjadi
     * format API.
     *
     * @throws RuntimeException Ketika koneksi ke router gagal dilakukan.
     */
    public function execute(string $command): array
    {
        if (!$this->connect()) {
            throw new RuntimeException($this->lastError ?? 'Gagal terhubung ke router.');
        }

        $query = $this->buildQueryFromCommand($command);

        try {
            $response = $this->client->query($query)->read();
            $this->lastError = null;

            return $response;
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();

            throw $exception;
        }
    }

    /**
     * Mengambil daftar koneksi PPPoE aktif dari router menggunakan perintah
     * `/ppp/active/print`. Jika koneksi gagal, metode ini mengembalikan array
     * kosong dan menyimpan pesan kesalahan terakhir.
     */
    public function getActivePppoeSessions(): array
    {
        if (!$this->connect()) {
            return [];
        }

        try {
            $query = new Query('/ppp/active/print');
            $response = $this->client->query($query)->read();
            $this->lastError = null;

            return array_map(static function (array $row): array {
                return [
                    'user' => $row['name'] ?? $row['user'] ?? '-',
                    'address' => $row['address'] ?? '-',
                    'profile' => $row['profile'] ?? '',
                    'service' => $row['service'] ?? '',
                    'uptime' => $row['uptime'] ?? '',
                    'encoding' => $row['encoding'] ?? '',
                ];
            }, $response);
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();

            return [];
        }
    }

    /**
     * Mengambil seluruh PPPoE secret yang terdaftar pada router. Data ini
     * diperlukan untuk mengetahui profil pengguna serta mendeteksi pengguna
     * yang tidak sedang aktif.
     */
    public function getPppoeSecrets(): array
    {
        if (!$this->connect()) {
            return [];
        }

        try {
            $query = new Query('/ppp/secret/print');
            $response = $this->client->query($query)->read();
            $this->lastError = null;

            return array_map(static function (array $row): array {
                return [
                    'id' => $row['.id'] ?? '',
                    'name' => $row['name'] ?? '-',
                    'profile' => $row['profile'] ?? '',
                    'service' => $row['service'] ?? '',
                    'last_logged_out' => $row['last-logged-out'] ?? '',
                    'comment' => $row['comment'] ?? '',
                    'disabled' => isset($row['disabled']) ? filter_var($row['disabled'], FILTER_VALIDATE_BOOLEAN) : false,
                ];
            }, $response);
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();

            return [];
        }
    }

    /**
     * Mengambil daftar interface ethernet lengkap dengan informasi trafik.
     */
    /**
     * Mengambil daftar interface ethernet (dan interface lain yang relevan)
     * dari router. Secara bawaan method ini akan mencoba mengambil data
     * realtime melalui `/interface/monitor-traffic` untuk setiap interface.
     *
     * Agar permintaan lebih ringan, panggilan dapat memberikan opsi
     * `monitor_all => false` dan `monitor_targets => ["ether1", ...]` sehingga
     * hanya interface tertentu yang diminta data realtime-nya. Ketika opsi
     * tersebut diberikan dan daftar target kosong, nilai laju akan diambil
     * dari field standar RouterOS (mis. `rx-rate`) tanpa mengirim perintah
     * monitor tambahan.
     */
    public function getEthernetInterfaces(array $options = []): array
    {
        if (!$this->connect()) {
            return [
                'success' => false,
                'interfaces' => [],
                'error' => $this->lastError,
            ];
        }

        try {
            $ethernetQuery = new Query('/interface/ethernet/print');
            $ethernetResponse = $this->client->query($ethernetQuery)->read();

            $generalResponse = [];
            $generalError = null;

            try {
                $generalQuery = new Query('/interface/print');
                $generalResponse = $this->client->query($generalQuery)->read();
            } catch (\Throwable $exception) {
                // Endpoint /interface/print tidak wajib tersedia di seluruh
                // perangkat (mis. akun dengan hak terbatas). Simpan error
                // sebagai informasi tambahan namun lanjutkan dengan data
                // ethernet yang sudah ada.
                $generalError = $exception->getMessage();
            }

            $this->lastError = null;

            $monitorAll = array_key_exists('monitor_all', $options)
                ? (bool) $options['monitor_all']
                : true;
            $monitorTargets = [];

            if (isset($options['monitor_targets']) && is_array($options['monitor_targets'])) {
                foreach ($options['monitor_targets'] as $targetName) {
                    $trimmed = trim((string) $targetName);

                    if ($trimmed === '') {
                        continue;
                    }

                    $monitorTargets[$trimmed] = true;
                }
            }

            $monitorDefaultFirst = !$monitorAll
                && !empty($options['monitor_default_first']);

            $ethernetMap = [];

            foreach ($ethernetResponse as $row) {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $ethernetMap[$name] = $row;
            }

            $generalMap = [];
            $orderedNames = [];

            foreach ($generalResponse as $row) {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $generalMap[$name] = $row;

                if (!in_array($name, $orderedNames, true)) {
                    $orderedNames[] = $name;
                }
            }

            foreach ($ethernetMap as $name => $_row) {
                if (!in_array($name, $orderedNames, true)) {
                    $orderedNames[] = $name;
                }
            }

            $interfaces = [];

            foreach ($orderedNames as $name) {
                $ethernetRow = $ethernetMap[$name] ?? [];
                $generalRow = $generalMap[$name] ?? [];
                $row = $ethernetRow + $generalRow;
                $fallbackRow = $generalRow + $ethernetRow;

                $displayName = trim((string) ($row['name'] ?? $fallbackRow['name'] ?? $name));
                $displayName = $displayName !== '' ? $displayName : $name;

                $running = $this->normalizeBoolean($row['running'] ?? $fallbackRow['running'] ?? null);
                $disabled = $this->normalizeBoolean($row['disabled'] ?? $fallbackRow['disabled'] ?? null);
                $status = $disabled ? 'disabled' : ($running ? 'running' : 'stopped');

                $rxBytes = $this->parseIntegerField(
                    $row['rx-byte'] ?? $fallbackRow['rx-byte'] ?? 0
                );
                $txBytes = $this->parseIntegerField(
                    $row['tx-byte'] ?? $fallbackRow['tx-byte'] ?? 0
                );
                $rxPackets = $this->parseIntegerField(
                    $row['rx-packet'] ?? $fallbackRow['rx-packet'] ?? 0
                );
                $txPackets = $this->parseIntegerField(
                    $row['tx-packet'] ?? $fallbackRow['tx-packet'] ?? 0
                );

                $rawSpeed = $row['speed']
                    ?? $fallbackRow['speed']
                    ?? $fallbackRow['actual-data-rate']
                    ?? null;
                $speedMbps = $this->parseInterfaceSpeedMbps($rawSpeed);

                $rxBps = $this->parseBitsPerSecondValue(
                    $row['rx-rate'] ?? $fallbackRow['rx-rate'] ?? 0
                );
                $txBps = $this->parseBitsPerSecondValue(
                    $row['tx-rate'] ?? $fallbackRow['tx-rate'] ?? 0
                );

                $rxRateLabel = $this->formatBitsPerSecond($rxBps);
                $txRateLabel = $this->formatBitsPerSecond($txBps);

                $interfaces[] = [
                    'name' => $displayName,
                    'default_name' => $fallbackRow['default-name'] ?? null,
                    'mac_address' => $row['mac-address'] ?? $fallbackRow['mac-address'] ?? '',
                    'type' => $fallbackRow['type'] ?? ($ethernetRow !== [] ? 'ethernet' : null),
                    'running' => $running,
                    'disabled' => $disabled,
                    'status' => $status,
                    'mtu' => $row['mtu'] ?? $fallbackRow['mtu'] ?? '',
                    'last_link_up_time' => $row['last-link-up-time'] ?? $fallbackRow['last-link-up-time'] ?? '',
                    'link_partner' => $row['link-partner'] ?? $fallbackRow['link-partner'] ?? '',
                    'rx_byte' => $rxBytes,
                    'tx_byte' => $txBytes,
                    'rx_packet' => $rxPackets,
                    'tx_packet' => $txPackets,
                    'rx_rate' => $rxRateLabel,
                    'tx_rate' => $txRateLabel,
                    'rx_bps' => $rxBps,
                    'tx_bps' => $txBps,
                    'rx_mbps' => round($rxBps / 1_000_000, 2),
                    'tx_mbps' => round($txBps / 1_000_000, 2),
                    'monitor_timestamp' => null,
                    'monitor_error' => null,
                    'monitor_requested' => $monitorAll || isset($monitorTargets[$displayName]),
                    'monitor_sampled' => false,
                    'if_speed' => $rawSpeed !== null ? trim((string) $rawSpeed) : null,
                    'if_speed_mbps' => $speedMbps,
                    'link_capacity_mbps' => $speedMbps,
                    'if_speed_bps' => $speedMbps !== null ? (int) round($speedMbps * 1_000_000) : null,
                    'comment' => $row['comment'] ?? $fallbackRow['comment'] ?? '',
                ];
            }

            if (!empty($generalError) && empty($interfaces)) {
                $this->lastError = $generalError;
            }

            if (!$monitorAll && $monitorDefaultFirst && empty($monitorTargets) && !empty($interfaces)) {
                $firstName = $interfaces[0]['name'] ?? null;

                if ($firstName) {
                    $monitorTargets[$firstName] = true;
                }
            }

            if ($monitorAll || !empty($monitorTargets)) {
                foreach ($interfaces as $index => $interface) {
                    $name = $interface['name'] ?? null;

                    if (!$name) {
                        continue;
                    }

                    $shouldMonitor = $monitorAll || isset($monitorTargets[$name]);

                    if (!$shouldMonitor) {
                        continue;
                    }

                    $monitor = $this->fetchInterfaceMonitorStats($name);

                    $interfaces[$index]['monitor_requested'] = true;
                    $interfaces[$index]['monitor_timestamp'] = $monitor['timestamp'] ?? null;
                    $interfaces[$index]['monitor_error'] = $monitor['error'] ?? null;
                    $interfaces[$index]['monitor_sampled'] = !empty($monitor['success']);

                    if (!empty($monitor['success'])) {
                        $rxBps = (int) ($monitor['rx_bps'] ?? 0);
                        $txBps = (int) ($monitor['tx_bps'] ?? 0);

                        $interfaces[$index]['rx_bps'] = $rxBps;
                        $interfaces[$index]['tx_bps'] = $txBps;
                        $interfaces[$index]['rx_rate'] = $monitor['rx_rate_label']
                            ?? $this->formatBitsPerSecond($rxBps);
                        $interfaces[$index]['tx_rate'] = $monitor['tx_rate_label']
                            ?? $this->formatBitsPerSecond($txBps);
                        $interfaces[$index]['rx_mbps'] = round($rxBps / 1_000_000, 2);
                        $interfaces[$index]['tx_mbps'] = round($txBps / 1_000_000, 2);
                    }
                }
            }

            return [
                'success' => true,
                'interfaces' => $interfaces,
                'monitor_all' => $monitorAll,
                'monitor_targets' => $monitorAll ? array_column($interfaces, 'name') : array_keys($monitorTargets),
            ];
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();

            return [
                'success' => false,
                'interfaces' => [],
                'error' => $this->lastError,
            ];
        }
    }

    /**
     * Menjalankan bandwidth test RouterOS terhadap alamat tujuan tertentu.
     * Method ini akan mengirim perintah `/tool/bandwidth-test` dan
     * mengembalikan ringkasan hasil pengujian.
     */
    public function runBandwidthTest(array $parameters): array
    {
        if (!$this->connect()) {
            return [
                'success' => false,
                'error' => $this->lastError ?? 'Gagal terhubung ke router.',
            ];
        }

        $address = trim((string) ($parameters['address'] ?? ''));

        if ($address === '') {
            return [
                'success' => false,
                'error' => 'Alamat server bandwidth test wajib diisi.',
            ];
        }

        $direction = $this->normaliseBandwidthDirection($parameters['direction'] ?? 'both');
        $protocol = $this->normaliseBandwidthProtocol($parameters['protocol'] ?? 'tcp');
        $username = trim((string) ($parameters['user'] ?? $this->username));
        $password = (string) ($parameters['password'] ?? $this->password);
        $durationSeconds = $this->normaliseBandwidthDurationSeconds(
            $parameters['duration'] ?? $parameters['duration_seconds'] ?? null
        );
        $connectionCount = $this->normaliseBandwidthConnectionCount(
            $parameters['connection-count'] ?? $parameters['connection_count'] ?? null
        );

        $payload = [
            'address' => $address,
            'user' => $username !== '' ? $username : $this->username,
            'password' => $password,
            'direction' => $direction,
            'protocol' => $protocol,
        ];

        if ($durationSeconds !== null) {
            $payload['duration'] = (string) $durationSeconds;
            $payload['duration_seconds'] = $durationSeconds;
        }

        if ($connectionCount !== null) {
            $payload['connection-count'] = (string) $connectionCount;
        }

        if (isset($parameters['local-test']) || isset($parameters['local_test'])) {
            $localTest = $parameters['local-test'] ?? $parameters['local_test'];
            $payload['local-test'] = $this->isTruthy($localTest) ? 'yes' : 'no';
        }

        $query = new Query('/tool/bandwidth-test');
        $query->equal('address', $payload['address']);
        $query->equal('user', $payload['user']);

        if ($payload['password'] !== '') {
            $query->equal('password', $payload['password']);
        }

        $query->equal('direction', $payload['direction']);
        $query->equal('protocol', $payload['protocol']);

        if (isset($payload['duration'])) {
            $query->equal('duration', $payload['duration']);
        }

        if (isset($payload['connection-count'])) {
            $query->equal('connection-count', $payload['connection-count']);
        }

        if (isset($payload['local-test'])) {
            $query->equal('local-test', $payload['local-test']);
        }

        $startedAt = date('c');

        try {
            $response = $this->client->query($query)->read();
            $this->lastError = null;
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();

            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        $entries = [];
        $txPeak = 0;
        $rxPeak = 0;
        $errors = [];

        foreach ($response as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowData = $this->unwrapBandwidthTestReply($row, $errors);

            if ($rowData === null || !is_array($rowData) || $rowData === []) {
                continue;
            }

            $normalised = $this->normaliseBandwidthTestRow($rowData);
            $entries[] = $normalised;

            $txPeak = max($txPeak, $normalised['tx_current_bps'], $normalised['tx_total_average_bps']);
            $rxPeak = max($rxPeak, $normalised['rx_current_bps'], $normalised['rx_total_average_bps']);
        }

        if (!empty($errors) && empty($entries)) {
            $message = implode(' | ', array_unique(array_filter($errors)));

            if ($message === '') {
                $message = 'Router mengembalikan kesalahan saat menjalankan bandwidth test.';
            }

            $this->lastError = $message;

            return [
                'success' => false,
                'error' => $message,
                'payload' => $payload,
                'address' => $address,
                'raw_replies' => $response,
            ];
        }

        if (empty($entries)) {
            $message = 'Router tidak mengembalikan data hasil bandwidth test. Pastikan bandwidth-server aktif dan kredensial benar.';

            $this->lastError = $message;

            return [
                'success' => false,
                'error' => $message,
                'payload' => $payload,
                'address' => $address,
                'raw_replies' => $response,
            ];
        }

        $lastEntry = end($entries) ?: [
            'tx_current_bps' => 0,
            'rx_current_bps' => 0,
            'tx_total_average_bps' => 0,
            'rx_total_average_bps' => 0,
        ];

        return [
            'success' => true,
            'entries' => $entries,
            'payload' => $payload,
            'address' => $address,
            'tx_peak_bps' => $txPeak,
            'rx_peak_bps' => $rxPeak,
            'tx_peak_label' => $this->formatBitsPerSecond($txPeak),
            'rx_peak_label' => $this->formatBitsPerSecond($rxPeak),
            'tx_current_bps' => $lastEntry['tx_current_bps'] ?? 0,
            'rx_current_bps' => $lastEntry['rx_current_bps'] ?? 0,
            'tx_current_label' => $lastEntry['tx_current_label'] ?? '0 bps',
            'rx_current_label' => $lastEntry['rx_current_label'] ?? '0 bps',
            'tx_total_average_bps' => $lastEntry['tx_total_average_bps'] ?? 0,
            'rx_total_average_bps' => $lastEntry['rx_total_average_bps'] ?? 0,
            'tx_total_average_label' => $lastEntry['tx_total_average_label'] ?? '0 bps',
            'rx_total_average_label' => $lastEntry['rx_total_average_label'] ?? '0 bps',
            'started_at' => $startedAt,
            'completed_at' => date('c'),
            'raw_replies' => $response,
        ];
    }

    /**
     * Mengambil statistik realtime interface menggunakan perintah
     * `/interface/monitor-traffic` sebagaimana pada contoh `traffic_lib.php`.
     */
    private function fetchInterfaceMonitorStats(string $interfaceName): array
    {
        $name = trim($interfaceName);

        if ($name === '') {
            return [
                'success' => false,
                'rx_bps' => 0,
                'tx_bps' => 0,
                'error' => null,
            ];
        }

        try {
            $query = (new Query('/interface/monitor-traffic'))
                ->equal('interface', $name)
                ->equal('once', 'true');

            $result = $this->client->query($query)->read();
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'rx_bps' => 0,
                'tx_bps' => 0,
                'error' => $exception->getMessage(),
            ];
        }

        $row = $result[0] ?? [];
        $rxBps = $this->parseIntegerField($row['rx-bits-per-second'] ?? $row['rx-current'] ?? 0);
        $txBps = $this->parseIntegerField($row['tx-bits-per-second'] ?? $row['tx-current'] ?? 0);

        return [
            'success' => true,
            'rx_bps' => $rxBps,
            'tx_bps' => $txBps,
            'rx_rate_label' => $this->formatBitsPerSecond($rxBps),
            'tx_rate_label' => $this->formatBitsPerSecond($txBps),
            'timestamp' => date('c'),
            'error' => null,
        ];
    }

    /**
     * Mengubah nilai bit per detik menjadi label ramah baca.
     */
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

    /**
     * Membersihkan angka yang dikirim RouterOS sehingga aman dikonversi ke int.
     */
    private function parseIntegerField($value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value);

        if ($normalized === '' || $normalized === '-' || $normalized === null) {
            return 0;
        }

        return (int) round((float) $normalized);
    }

    /**
     * Mengonversi nilai laju yang mungkin memiliki satuan (Mbps/Kbps) ke bps.
     */
    private function parseBitsPerSecondValue($value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        $text = trim((string) $value);

        if ($text === '') {
            return 0;
        }

        if (!preg_match('/([\d.,]+)/', $text, $matches)) {
            return 0;
        }

        $numeric = (float) str_replace(',', '.', $matches[1]);

        if ($numeric <= 0) {
            return 0;
        }

        $lower = strtolower($text);
        $multiplier = 1;

        if (strpos($lower, 'tbps') !== false || strpos($lower, 'tbit') !== false) {
            $multiplier = 1_000_000_000_000;
        } elseif (strpos($lower, 'gbps') !== false || strpos($lower, 'gbit') !== false) {
            $multiplier = 1_000_000_000;
        } elseif (strpos($lower, 'mbps') !== false || strpos($lower, 'mbit') !== false) {
            $multiplier = 1_000_000;
        } elseif (strpos($lower, 'kbps') !== false || strpos($lower, 'kbit') !== false) {
            $multiplier = 1_000;
        } elseif (strpos($lower, 'bps') !== false || strpos($lower, 'bit') !== false) {
            $multiplier = 1;
        } elseif (preg_match('/([kmgt])(?:bps|bit|b)?$/', $lower, $unitMatch)) {
            switch ($unitMatch[1]) {
                case 't':
                    $multiplier = 1_000_000_000_000;
                    break;
                case 'g':
                    $multiplier = 1_000_000_000;
                    break;
                case 'm':
                    $multiplier = 1_000_000;
                    break;
                case 'k':
                    $multiplier = 1_000;
                    break;
            }
        }

        return (int) round($numeric * $multiplier);
    }

    /**
     * Mengonversi informasi kapasitas interface (mis. "1Gbps") menjadi Mbps.
     */
    private function parseInterfaceSpeedMbps($value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;

            if ($numeric <= 0) {
                return null;
            }

            // Nilai yang besar diasumsikan masih dalam bps lalu dikonversi ke Mbps.
            if ($numeric > 10_000) {
                return round($numeric / 1_000_000, 2);
            }

            return round($numeric, 2);
        }

        $bps = $this->parseBitsPerSecondValue($value);

        if ($bps <= 0) {
            return null;
        }

        return round($bps / 1_000_000, 2);
    }

    private function unwrapBandwidthTestReply(array $reply, array &$errors): ?array
    {
        $errorMessage = $this->detectBandwidthReplyError($reply);

        if ($errorMessage !== null) {
            $errors[] = $errorMessage;

            return null;
        }

        if (isset($reply['!re']) && is_array($reply['!re'])) {
            $clean = $this->stripRouterOsControlFields($reply['!re']);

            return $clean === [] ? null : $clean;
        }

        if (isset($reply['!done']) && is_array($reply['!done'])) {
            $clean = $this->stripRouterOsControlFields($reply['!done']);

            return $clean === [] ? null : $clean;
        }

        $clean = $this->stripRouterOsControlFields($reply);

        return $clean === [] ? null : $clean;
    }

    private function detectBandwidthReplyError(array $reply): ?string
    {
        foreach (['!fatal', '!trap'] as $errorKey) {
            if (array_key_exists($errorKey, $reply)) {
                $segment = $reply[$errorKey];
                $message = $this->extractRouterOsMessage($segment);

                if ($message === null) {
                    $message = $this->extractRouterOsMessage($reply);
                }

                return $message ?? 'RouterOS mengembalikan kesalahan saat menjalankan bandwidth test.';
            }
        }

        if (isset($reply['error']) && trim((string) $reply['error']) !== '') {
            return trim((string) $reply['error']);
        }

        return null;
    }

    private function extractRouterOsMessage($segment): ?string
    {
        if (is_string($segment) && trim($segment) !== '') {
            return trim($segment);
        }

        if (!is_array($segment)) {
            return null;
        }

        $candidates = ['message', 'error', 'reason', '=message', '=error', '=reason'];

        foreach ($candidates as $key) {
            if (isset($segment[$key]) && trim((string) $segment[$key]) !== '') {
                return trim((string) $segment[$key]);
            }
        }

        $values = [];

        foreach ($segment as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $values[] = trim((string) $value);
            }
        }

        if (!empty($values)) {
            return implode(' | ', array_unique($values));
        }

        return null;
    }

    private function stripRouterOsControlFields(array $row): array
    {
        $clean = [];

        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if ($key === 'tag' || $key === '.tag') {
                continue;
            }

            if ($key !== '' && $key[0] === '!') {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Menghapus PPPoE secret berdasarkan ID unik yang diberikan RouterOS.
     */
    public function removePppoeSecret(string $secretId): bool
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $query = (new Query('/ppp/secret/remove'))
                ->equal('.id', $secretId);

            $this->client->query($query)->read();
            $this->lastError = null;

            return true;
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();

            return false;
        }
    }

    /**
     * Mengubah nilai boolean RouterOS ke dalam bentuk boolean PHP murni.
     */
    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);

        return in_array($normalized, ['1', 'true', 'yes', 'on', 'running'], true);
    }

    /**
     * Menyederhanakan hasil balikan `/tool/bandwidth-test` ke struktur yang
     * konsisten berupa angka bps dan label siap pakai.
     */
    private function normaliseBandwidthTestRow(array $row): array
    {
        [$txCurrent, $txCurrentSource] = $this->extractBandwidthRate($row, [
            'tx-current',
            'tx-current-bits-per-second',
            'tx-current-bps',
            'tx-bits-per-second',
            'tx-bit-rate',
            'tx-rate',
            'tcp-write',
            'udp-write',
            'tx-total-average',
            'tx-total-average-bits-per-second',
        ]);

        [$rxCurrent, $rxCurrentSource] = $this->extractBandwidthRate($row, [
            'rx-current',
            'rx-current-bits-per-second',
            'rx-current-bps',
            'rx-bits-per-second',
            'rx-bit-rate',
            'rx-rate',
            'tcp-read',
            'udp-read',
            'rx-total-average',
            'rx-total-average-bits-per-second',
        ]);

        [$txTotalAverage, $txAverageSource] = $this->extractBandwidthRate($row, [
            'tx-total-average',
            'tx-total-average-bits-per-second',
            'tx-total-bps',
            'tx-total-rate',
            'tcp-write',
            'udp-write',
            'tx-current',
            'tx-current-bits-per-second',
            'tx-bits-per-second',
        ]);

        [$rxTotalAverage, $rxAverageSource] = $this->extractBandwidthRate($row, [
            'rx-total-average',
            'rx-total-average-bits-per-second',
            'rx-total-bps',
            'rx-total-rate',
            'tcp-read',
            'udp-read',
            'rx-current',
            'rx-current-bits-per-second',
            'rx-bits-per-second',
        ]);

        $tcpWriteBps = $this->parseBitsPerSecondValue($row['tcp-write'] ?? 0);
        $tcpReadBps = $this->parseBitsPerSecondValue($row['tcp-read'] ?? 0);
        $udpWriteBps = $this->parseBitsPerSecondValue($row['udp-write'] ?? 0);
        $udpReadBps = $this->parseBitsPerSecondValue($row['udp-read'] ?? 0);

        return [
            'status' => $row['status'] ?? null,
            'direction' => $row['direction'] ?? null,
            'protocol' => $row['protocol'] ?? null,
            'time_remaining' => $row['time-remaining'] ?? $row['time_remaining'] ?? null,
            'tx_current_bps' => $txCurrent,
            'rx_current_bps' => $rxCurrent,
            'tx_total_average_bps' => $txTotalAverage,
            'rx_total_average_bps' => $rxTotalAverage,
            'tx_current_source' => $txCurrentSource,
            'rx_current_source' => $rxCurrentSource,
            'tx_average_source' => $txAverageSource,
            'rx_average_source' => $rxAverageSource,
            'tx_current_label' => $this->formatBitsPerSecond($txCurrent),
            'rx_current_label' => $this->formatBitsPerSecond($rxCurrent),
            'tx_total_average_label' => $this->formatBitsPerSecond($txTotalAverage),
            'rx_total_average_label' => $this->formatBitsPerSecond($rxTotalAverage),
            'tcp_read' => $row['tcp-read'] ?? null,
            'tcp_write' => $row['tcp-write'] ?? null,
            'udp_read' => $row['udp-read'] ?? null,
            'udp_write' => $row['udp-write'] ?? null,
            'tcp_read_bps' => $tcpReadBps,
            'tcp_write_bps' => $tcpWriteBps,
            'udp_read_bps' => $udpReadBps,
            'udp_write_bps' => $udpWriteBps,
            'tcp_read_label' => $this->formatBitsPerSecond($tcpReadBps),
            'tcp_write_label' => $this->formatBitsPerSecond($tcpWriteBps),
            'udp_read_label' => $this->formatBitsPerSecond($udpReadBps),
            'udp_write_label' => $this->formatBitsPerSecond($udpWriteBps),
            'local_address' => $row['local-address'] ?? null,
            'remote_address' => $row['remote-address'] ?? $row['address'] ?? null,
            'when' => date('c'),
        ];
    }

    /**
     * Memilih nilai laju bandwidth terbaik dari beberapa kandidat field yang
     * mungkin dikirim RouterOS. Mengembalikan pasangan [nilai_bps, sumber].
     */
    private function extractBandwidthRate(array $row, array $candidateKeys): array
    {
        $bestValue = 0;
        $bestSource = null;

        foreach ($candidateKeys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->parseBitsPerSecondValue($row[$key]);

            if ($value > $bestValue) {
                $bestValue = $value;
                $bestSource = $key;
            }
        }

        return [$bestValue, $bestSource];
    }

    private function normaliseBandwidthProtocol($value): string
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized === 'udp' ? 'udp' : 'tcp';
    }

    private function normaliseBandwidthDirection($value): string
    {
        $normalized = strtolower(trim((string) $value));

        switch ($normalized) {
            case 'tx':
            case 'transmit':
                return 'transmit';
            case 'rx':
            case 'receive':
                return 'receive';
            default:
                return 'both';
        }
    }

    private function normaliseBandwidthDurationSeconds($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $seconds = $value;
        } else {
            $stringValue = trim((string) $value);

            if ($stringValue === '') {
                return null;
            }

            if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $stringValue, $matches)) {
                $seconds = ((int) $matches[1] * 3600)
                    + ((int) $matches[2] * 60)
                    + (int) $matches[3];
            } elseif (preg_match('/^(\d+)(s)?$/i', $stringValue, $matches)) {
                $seconds = (int) $matches[1];
            } elseif (is_numeric($stringValue)) {
                $seconds = (int) $stringValue;
            } else {
                return null;
            }
        }

        if ($seconds <= 0) {
            return null;
        }

        return max(1, min(60, $seconds));
    }

    private function normaliseBandwidthConnectionCount($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        $count = (int) $value;

        if ($count <= 0) {
            return null;
        }

        return max(1, min(100, $count));
    }

    private function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Mengonversi perintah CLI RouterOS menjadi format Query yang diterima
     * pustaka EvilFreelancer.
     */
    private function buildQueryFromCommand(string $command): Query
    {
        $command = trim($command);

        if ($command === '') {
            throw new InvalidArgumentException('Perintah RouterOS tidak boleh kosong.');
        }

        $parts = preg_split('/\s+/', $command);
        $parts = array_filter($parts, static fn (string $part): bool => $part !== '');

        $pathSegments = [];
        $equals = [];
        $conditions = [];

        foreach ($parts as $part) {
            $prefix = $part[0] ?? '';

            if ($prefix === '=') {
                $equals[] = substr($part, 1);

                continue;
            }

            if ($prefix === '?') {
                $conditions[] = substr($part, 1);

                continue;
            }

            $pathSegments[] = $part;
        }

        $path = implode('/', $pathSegments);

        if ($path === '') {
            throw new InvalidArgumentException('Perintah RouterOS tidak dikenal.');
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $query = new Query($path);

        foreach ($equals as $expression) {
            if ($expression === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $expression, 2), 2, '');
            if ($key === '') {
                continue;
            }

            $query->equal($key, $value);
        }

        foreach ($conditions as $expression) {
            if ($expression === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $expression, 2), 2, '');
            if ($key === '') {
                continue;
            }

            $query->where($key, $value);
        }

        return $query;
    }
}
