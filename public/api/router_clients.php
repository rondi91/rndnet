
<?php
// Endpoint JSON untuk mengambil daftar klien router yang tersimpan dari
// koneksi PPPoE aktif.

require_once __DIR__ . '/../../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../../data/routers.json');
$service = new RouterService($repository, __DIR__ . '/../../data/router_client.json');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
$directoryMode = isset($_GET['directory']) && $_GET['directory'] === '1';

if ($directoryMode) {
    echo json_encode(
        $service->getPppoeClientDirectory($refresh),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    return;
}

echo json_encode(
    $service->getRouterClientSnapshot(),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
