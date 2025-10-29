
<?php
// Contoh mandiri untuk menjalankan bandwidth test RouterOS menggunakan
// pustaka evilfreelancer/routeros-api-php sebagaimana diminta pengguna.
// Jalankan script ini dari root proyek setelah memasang dependensi Composer.

require __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

$ip = '172.16.30.8'; // IP router AP yang akan menjalankan bandwidth-test
$apUser = 'rondi';
$apPass = '21184662';

$target = '172.16.30.1'; // IP bandwidth server tujuan
$svrUser = 'rondi';
$svrPass = '21184662';

$direction = 'transmit'; // transmit | receive | both
$protocol = 'tcp'; // tcp | udp
$duration = 10; // dalam detik (1-60 detik)

try {
    $client = new Client([
        'host' => $ip,
        'user' => $apUser,
        'pass' => $apPass,
        'timeout' => 10,
    ]);

    $query = new Query('/tool/bandwidth-test');
    $query->equal('address', $target)
        ->equal('user', $svrUser)
        ->equal('password', $svrPass)
        ->equal('direction', $direction)
        ->equal('protocol', $protocol)
        ->equal('duration', (string) $duration);

    $result = $client->query($query)->read();
    print_r($result);
} catch (Throwable $e) {
    error_log('Bandwidth test gagal: ' . $e->getMessage() . PHP_EOL);

    exit(1);
}
