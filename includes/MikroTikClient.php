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
}
 
