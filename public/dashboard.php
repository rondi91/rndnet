
<?php
// Sertakan service agar data router dapat digunakan pada dashboard.
require_once __DIR__ . '/../includes/RouterService.php';

// Inisialisasi repository dan service.
$repository = new RouterRepository(__DIR__ . '/../data/routers.json');
$routerService = new RouterService($repository);

// Ambil data dasar yang akan ditampilkan pada dashboard.
$routers = $routerService->listRouters();

$pppoeSessions = $routerService->getActivePppoeSessions();
$pppoeServers = $routerService->getActivePppoeSessionsByRouter();

$totalRouters = count($routers);
$totalPppoeServers = count($pppoeServers);
$totalPppoeSessions = array_sum(array_map(static function (array $server): int {
    return $server['total_sessions'] ?? 0;
}, $pppoeServers));
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
                <small>Total koneksi PPPoE aktif yang sedang terhubung.</small>
            </div>
        </div>

        <section class="dashboard-card" id="pppoe" style="margin-top: 24px;">
            <h2>Koneksi PPPoE Aktif</h2>
            <p>Bagian ini mengambil data langsung dari RouterOS melalui API <code>evilfreelancer/routeros-api-php</code>.</p>

            <?php if (empty($pppoeServers)): ?>
                <div class="alert alert-error">Belum ada server PPPoE yang ditambahkan. Silakan registrasikan melalui halaman utama.</div>
            <?php else: ?>
                <div class="pppoe-server-grid">
                    <?php foreach ($pppoeServers as $server): ?>
                        <article class="pppoe-server-card">
                            <header class="pppoe-server-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($server['router_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="pppoe-server-meta">
                                        <span>IP: <?php echo htmlspecialchars($server['router_ip'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($server['notes'])): ?>
                                            <span>Catatan: <?php echo htmlspecialchars($server['notes'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="pppoe-server-stat">
                                    <strong><?php echo (int) ($server['total_sessions'] ?? 0); ?></strong>
                                    <span>Aktif</span>
                                </div>
                            </header>

                            <?php if (empty($server['reachable'])): ?>
                                <div class="alert alert-error">
                                    Tidak dapat terhubung ke server PPPoE ini.
                                    <?php if (!empty($server['error'])): ?>
                                        <br><small><?php echo htmlspecialchars($server['error'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (!empty($server['error'])): ?>
                                <div class="alert alert-error">
                                    Terjadi kesalahan saat membaca data PPPoE.<br>
                                    <small><?php echo htmlspecialchars($server['error'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                            <?php elseif (empty($server['sessions'])): ?>
                                <div class="alert alert-info">Belum ada koneksi PPPoE yang aktif saat ini.</div>
                            <?php else: ?>
                                <table class="pppoe-session-table">
                                    <thead>
                                        <tr>
                                            <th>Pengguna</th>
                                            <th>Alamat PPPoE</th>
                                            <th>Uptime</th>
                                            <th>Caller ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($server['sessions'] as $session): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($session['user'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($session['address'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($session['uptime'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($session['caller_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>
</div>
</body>
</html>
