<?php
// Endpoint JSON untuk memperbarui pilihan interface router client.

require_once __DIR__ . '/../../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../../data/routers.json');
$service = new RouterService(
    $repository,
    __DIR__ . '/../../data/router_client.json',
    __DIR__ . '/../../data/routers_server.json',
    __DIR__ . '/../../data/bandwidth_rate_limit.json'
);

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

$clientKey = isset($payload['client_key']) ? strtolower(trim((string) $payload['client_key'])) : '';
$ipAddress = trim((string) ($payload['ip_address'] ?? $payload['router_ip'] ?? ''));
$interfaceName = trim((string) ($payload['interface'] ?? $payload['preferred_interface'] ?? ''));

if ($clientKey === '' && $ipAddress === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Client key atau alamat IP wajib disertakan.',
    ]);

    exit;
}

try {
    $result = $service->updateRouterClientPreferredInterface($clientKey, $ipAddress, $interfaceName);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan internal: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

if (!($result['success'] ?? false)) {
    http_response_code(400);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
