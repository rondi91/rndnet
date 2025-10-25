<?php
// Sertakan file yang dibutuhkan agar kelas dapat digunakan.
require_once __DIR__ . '/../includes/RouterService.php';

// Siapkan repository dan service utama aplikasi.
$repository = new RouterRepository(__DIR__ . '/../data/routers.json');
$routerService = new RouterService($repository);

// Variabel untuk menyimpan pesan sukses atau error.
$successMessage = null;
$errorMessages = [];
$commandResult = null;

// Tangani request POST untuk menambah router atau menjalankan perintah.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_router') {
        // Ambil nilai dari form penambahan router.
        $name = $_POST['name'] ?? '';
        $ipAddress = $_POST['ip_address'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $isPppoeServer = isset($_POST['is_pppoe_server']) && $_POST['is_pppoe_server'] === '1';

        // Panggil service untuk menambah router.
        $result = $routerService->addRouter($name, $ipAddress, $username, $password, $notes, $isPppoeServer);

        if ($result['success']) {
            $successMessage = 'Router baru berhasil ditambahkan.';
        } else {
            $errorMessages = $result['errors'];
        }
    }

    if ($action === 'run_command') {
        // Mengambil index router yang dipilih.
        $routerIndex = (int) ($_POST['router_index'] ?? -1);
        $command = $_POST['command'] ?? '';

        $routers = $routerService->listRouters();

        if (!isset($routers[$routerIndex])) {
            $errorMessages[] = 'Router tidak ditemukan.';
        } elseif (trim($command) === '') {
            $errorMessages[] = 'Perintah tidak boleh kosong.';
        } else {
            // Jalankan perintah menggunakan service.
            $runResult = $routerService->runCommand($routers[$routerIndex], $command);

            if ($runResult['success']) {
                $successMessage = 'Perintah berhasil dieksekusi.';
                $commandResult = $runResult['data'];
            } else {
                $errorMessages[] = $runResult['message'] ?? 'Perintah gagal dieksekusi.';
            }
        }
    }
}

// Ambil daftar router untuk ditampilkan pada halaman.
$routers = $routerService->listRouters();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Winbox Web</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header>
    <h1>Panel Registrasi Router MikroTik</h1>
    <p>Gunakan halaman ini untuk menambahkan server router baru sebelum masuk ke dashboard monitoring.</p>
</header>
<main class="container">
    <!-- Bagian judul form yang menjelaskan tujuan halaman -->
    <div class="form-header">
        <div>
            <h2>Tambah Router</h2>
            <p>Isikan kredensial router yang ingin Anda kelola. Router yang ditandai sebagai server PPPoE akan muncul pada menu PPPoE di dashboard.</p>
        </div>
        <div>
            <a class="button" href="dashboard.php">Buka Dashboard</a>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!empty($errorMessages)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errorMessages as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Tata letak dua kolom: form input dan daftar router -->
    <section class="form-layout">
        <div class="form-column">
            <form method="post" class="card">
                <input type="hidden" name="action" value="add_router">

                <label for="name">Nama Router</label>
                <input type="text" id="name" name="name" placeholder="Contoh: Router Kantor" required>

                <label for="ip_address">Alamat IP</label>
                <input type="text" id="ip_address" name="ip_address" placeholder="192.168.1.1" required>

                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="admin" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="*****" required>

                <label for="notes">Catatan</label>
                <textarea id="notes" name="notes" rows="3" placeholder="Catatan tambahan"></textarea>

                <label class="checkbox">
                    <input type="checkbox" name="is_pppoe_server" value="1">
                    Jadikan router ini sebagai server PPPoE
                </label>

                <button type="submit">Simpan Router</button>
            </form>
        </div>
        <div class="form-column">
            <section class="card">
                <h3>Daftar Router Tersimpan</h3>
                <p>Router yang telah Anda tambahkan akan muncul di sini.</p>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Alamat IP</th>
                            <th>PPPoE</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($routers as $index => $router): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($router['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($router['ip_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo !empty($router['is_pppoe_server']) ? 'Ya' : 'Tidak'; ?></td>
                                <td><?php echo htmlspecialchars($router['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </section>

    <!-- Form tambahan untuk mencoba perintah CLI sederhana -->
    <section class="card">
        <h3>Simulasi Perintah RouterOS</h3>
        <p>Gunakan formulir ini untuk mencoba perintah CLI sederhana dan melihat bagaimana respon mock dari <code>MikroTikClient</code>.</p>
        <form method="post">
            <input type="hidden" name="action" value="run_command">

            <label for="router_index">Pilih Router</label>
            <select id="router_index" name="router_index">
                <?php foreach ($routers as $index => $router): ?>
                    <option value="<?php echo $index; ?>">
                        <?php echo htmlspecialchars($router['name'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($router['ip_address'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="command">Perintah RouterOS</label>
            <input type="text" id="command" name="command" placeholder="/system resource print">

            <button type="submit">Kirim Perintah</button>
        </form>

        <?php if ($commandResult): ?>
            <div class="alert alert-success" style="margin-top:16px;">
                <h3>Hasil Simulasi:</h3>
                <pre><?php echo htmlspecialchars(print_r($commandResult, true), ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
