
<?php
// Sertakan service agar data router dapat digunakan pada dashboard.
require_once __DIR__ . '/../includes/RouterService.php';

// Inisialisasi repository dan service.
$repository = new RouterRepository(__DIR__ . '/../data/routers.json');
$routerService = new RouterService($repository);

// Ambil data dasar yang akan ditampilkan pada dashboard.
$routers = $routerService->listRouters();

// Beberapa pengguna mungkin masih memiliki versi RouterService lama yang belum
// menyediakan metode getActivePppoeSessions. Untuk menjaga kompatibilitas dan
// menghindari fatal error, kita lakukan pengecekan sebelum memanggil metode
// tersebut lalu menerapkan logika cadangan jika perlu.
$pppoeSessions = [];

if (method_exists($routerService, 'getActivePppoeSessions')) {
    $pppoeSessions = $routerService->getActivePppoeSessions();
} else {
    require_once __DIR__ . '/../includes/MikroTikClient.php';

    foreach ($routers as $router) {
        if (empty($router['is_pppoe_server'])) {
            continue;
        }

        $client = new MikroTikClient(
            $router['ip_address'],
            $router['username'],
            $router['password']
        );

        if (!$client->connect()) {
            continue;
        }

        foreach ($client->getActivePppoeSessions() as $session) {
            $pppoeSessions[] = array_merge($session, [
                'router_name' => $router['name'],
                'router_ip' => $router['ip_address'],
            ]);
        }
    }
}

$totalRouters = count($routers);
$totalPppoeServers = count(array_filter($routers, static function (array $router): bool {
    return !empty($router['is_pppoe_server']);
}));
$totalPppoeSessions = count($pppoeSessions);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard MikroTik</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="dashboard-layout">
    <!-- Navigasi samping untuk memudahkan perpindahan menu -->
    <aside class="sidebar">
        <h2>Router Control</h2>
        <nav>
            <a href="#overview">Ikhtisar</a>
            <a href="#pppoe">PPPoE Aktif</a>
            <a href="index.php">Tambah Router</a>
        </nav>
    </aside>

    <section class="dashboard-content">
        <header class="dashboard-header" id="overview">
            <h1>Dashboard Monitoring MikroTik</h1>
            <p>Tampilan ringkas untuk memantau router dan koneksi PPPoE yang telah Anda registrasikan.</p>
        </header>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Total Router</h3>
                <p><strong><?php echo $totalRouters; ?></strong></p>
                <small>Jumlah seluruh router yang tersimpan di aplikasi.</small>
            </div>
            <div class="dashboard-card">
                <h3>Server PPPoE</h3>
                <p><strong><?php echo $totalPppoeServers; ?></strong></p>
                <small>Router yang ditandai sebagai penyedia layanan PPPoE.</small>
            </div>
            <div class="dashboard-card">
                <h3>Aktif PPPoE</h3>
                <p><strong><?php echo $totalPppoeSessions; ?></strong></p>
                <small>Total koneksi PPPoE aktif yang sedang tersimulasi.</small>
            </div>
        </div>

        <section class="dashboard-card" id="pppoe" style="margin-top: 24px;">
            <h2>Koneksi PPPoE Aktif</h2>
            <p>Data di bawah merupakan hasil simulasi dari <code>MikroTikClient</code>. Gunakan struktur ini sebagai acuan sebelum menghubungkannya ke API asli.</p>

            <?php if (empty($pppoeSessions)): ?>
                <div class="alert alert-error">Belum ada server PPPoE yang ditambahkan. Silakan tambahkan melalui halaman utama.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Router</th>
                            <th>Alamat IP</th>
                            <th>Pengguna</th>
                            <th>Alamat PPPoE</th>
                            <th>Uptime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pppoeSessions as $session): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($session['router_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($session['router_ip'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($session['user'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($session['address'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($session['uptime'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </section>
</div>
</body>
</html>
 
