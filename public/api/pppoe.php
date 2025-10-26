
<?php
// Endpoint sederhana untuk menyediakan data PPPoE dalam format JSON
// sehingga dashboard dapat menampilkan informasi secara real-time.

require_once __DIR__ . '/../../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../../data/routers.json');
$service = new RouterService($repository);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true) ?? [];
    $routerIp = (string) ($payload['router_ip'] ?? '');
    $secretId = (string) ($payload['secret_id'] ?? '');

    if ($routerIp === '' || $secretId === '') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Router IP atau ID secret PPPoE tidak valid.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $result = $service->removePppoeSecret($routerIp, $secretId);

    if (!$result['success']) {
        http_response_code(400);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

echo json_encode($service->getPppoeDashboardData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
