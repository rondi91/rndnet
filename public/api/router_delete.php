
<?php
// Endpoint JSON untuk menghapus router client dari daftar pemantauan interface.

require_once __DIR__ . '/../../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../../data/routers.json');
$service = new RouterService($repository);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metode tidak diizinkan.',
    ]);

    exit;
}

$rawInput = file_get_contents('php://input');
$payload = [];

if ($rawInput !== false && trim($rawInput) !== '') {
    $decoded = json_decode($rawInput, true);

    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if (empty($payload)) {
    $payload = $_POST;
}

$clientKey = $payload['client_key'] ?? '';
$ipAddress = $payload['ip_address'] ?? '';

try {
    $result = $service->removeRouterClient($clientKey, $ipAddress);
} catch (\Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan internal saat menghapus router: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

if ($result['success'] ?? false) {
    echo json_encode([
        'success' => true,
        'message' => 'Router client berhasil dihapus.',
    ]);

    exit;
}

http_response_code(400);

echo json_encode([
    'success' => false,
    'message' => $result['message'] ?? 'Router client gagal dihapus.',
]);
