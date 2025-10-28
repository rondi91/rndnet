
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
    public function getEthernetInterfaces(): array
    {
        if (!$this->connect()) {
            return [
                'success' => false,
                'interfaces' => [],
                'error' => $this->lastError,
            ];
        }

        try {
            $query = new Query('/interface/ethernet/print');
            $response = $this->client->query($query)->read();
            $this->lastError = null;

            $interfaces = [];

            foreach ($response as $row) {
                $name = $row['name'] ?? '';
                $running = $this->normalizeBoolean($row['running'] ?? null);
                $disabled = $this->normalizeBoolean($row['disabled'] ?? null);
                $rxBytes = $this->parseIntegerField($row['rx-byte'] ?? 0);
                $txBytes = $this->parseIntegerField($row['tx-byte'] ?? 0);
                $rxPackets = $this->parseIntegerField($row['rx-packet'] ?? 0);
                $txPackets = $this->parseIntegerField($row['tx-packet'] ?? 0);

                $monitor = $this->fetchInterfaceMonitorStats($name);
                $rxBps = $monitor['rx_bps'] ?? $this->parseBitsPerSecondValue($row['rx-rate'] ?? 0);
                $txBps = $monitor['tx_bps'] ?? $this->parseBitsPerSecondValue($row['tx-rate'] ?? 0);
                $rxRateLabel = $monitor['rx_rate_label'] ?? $this->formatBitsPerSecond($rxBps);
                $txRateLabel = $monitor['tx_rate_label'] ?? $this->formatBitsPerSecond($txBps);

                $interfaces[] = [
                    'name' => $name !== '' ? $name : '-',
                    'mac_address' => $row['mac-address'] ?? '',
                    'running' => $running,
                    'disabled' => $disabled,
                    'status' => $disabled ? 'disabled' : ($running ? 'running' : 'stopped'),
                    'mtu' => $row['mtu'] ?? '',
                    'last_link_up_time' => $row['last-link-up-time'] ?? '',
                    'link_partner' => $row['link-partner'] ?? '',
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
                    'monitor_timestamp' => $monitor['timestamp'] ?? null,
                    'monitor_error' => $monitor['error'] ?? null,
                    'if_speed' => $row['speed'] ?? null,
                    'comment' => $row['comment'] ?? '',
                ];
            }

            return [
                'success' => true,
                'interfaces' => $interfaces,
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
