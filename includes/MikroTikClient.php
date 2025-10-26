
<?php
/**
 * MikroTikClient adalah kelas tiruan (mock) yang meniru perilaku koneksi ke
 * RouterOS API. Kelas ini tidak benar-benar terhubung ke router, namun
 * memberikan gambaran bagaimana struktur kode PHP dapat dibangun ketika
 * ingin berkomunikasi dengan perangkat Mikrotik.
 */
class MikroTikClient
{
    /** @var string $host Alamat IP atau hostname router. */
    private string $host;

    /** @var string $username Nama pengguna RouterOS. */
    private string $username;

    /** @var string $password Kata sandi RouterOS. */
    private string $password;

    /**
     * Konstruktor menyimpan data kredensial agar dapat digunakan ketika
     * menjalankan metode lain seperti connect atau execute.
     */
    public function __construct(string $host, string $username, string $password)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Metode connect di sini hanya mensimulasikan proses autentikasi ke
     * perangkat Mikrotik.
     */
    public function connect(): bool
    {
        // Dalam aplikasi nyata, Anda bisa menggunakan RouterOS PHP Client.
        // Di contoh ini kita menganggap kredensial selalu benar.
        return true;
    }

    /**
     * Metode execute mensimulasikan pengiriman perintah ke router dan
     * mengembalikan data contoh.
     */
    public function execute(string $command): array
    {
        // Simulasi output. Dalam implementasi asli, Anda akan mengirimkan
        // perintah ke API dan menerima hasil berupa array.
        return [
            'command' => $command,
            'status' => 'success',
            'response' => 'Perintah dijalankan pada router ' . $this->host,
        ];
    }

    /**
     * Mengembalikan daftar koneksi PPPoE aktif dalam bentuk data contoh.
     *
     * Dalam implementasi nyata, Anda dapat menggunakan RouterOS API untuk
     * menjalankan perintah `/ppp active print` dan memetakan hasilnya. Di sini
     * kita membangun data statis yang deterministik berdasarkan alamat host
     * agar tampilan dashboard tetap konsisten setiap kali halaman dimuat.
     */
    public function getActivePppoeSessions(): array
    {
        // Gunakan hash dari host agar setiap router memiliki nilai unik
        // tanpa harus bergantung pada database atau API sungguhan.
        $hash = md5($this->host);

        $firstSegment = hexdec(substr($hash, 0, 2)) % 254 + 1;
        $secondSegment = hexdec(substr($hash, 2, 2)) % 254 + 1;
        $thirdSegment = hexdec(substr($hash, 4, 2)) % 254 + 1;

        return [
            [
                'user' => 'cust-' . substr($hash, 0, 4),
                'address' => sprintf('10.%d.%d.%d', $firstSegment, $secondSegment, $thirdSegment),
                'service' => 'pppoe',
                'uptime' => '01:23:45',
                'host' => $this->host,
            ],
            [
                'user' => 'vip-' . substr($hash, 4, 4),
                'address' => sprintf('10.%d.%d.%d', $secondSegment, $thirdSegment, $firstSegment),
                'service' => 'pppoe',
                'uptime' => '12:34:56',
                'host' => $this->host,
            ],
        ];
    }
}
