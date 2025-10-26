
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
