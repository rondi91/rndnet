
<?php
// Endpoint untuk menjalankan bandwidth test dari router yang dipilih.

require_once __DIR__ . '/../../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../../data/routers.json');
$service = new RouterService(
    $repository,
    __DIR__ . '/../../data/router_client.json',
    __DIR__ . '/../../data/routers_server.json'
);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    $data = $_POST;
}

$routerIp = trim((string) ($data['router_ip'] ?? ''));

if ($routerIp === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Parameter router_ip wajib diisi.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return;
}

$options = [];

if (isset($data['duration'])) {
    $duration = (int) $data['duration'];
    $options['duration'] = max(1, min(60, $duration));
}

if (isset($data['protocol'])) {
    $protocol = strtolower((string) $data['protocol']);

    if (!in_array($protocol, ['tcp', 'udp'], true)) {
        $protocol = 'tcp';
    }

    $options['protocol'] = $protocol;
}

if (isset($data['direction'])) {
    $direction = strtolower(trim((string) $data['direction']));

    if (!in_array($direction, ['tx', 'rx', 'both'], true)) {
        $direction = 'both';
    }

    $options['direction'] = $direction;
}

if (isset($data['interface'])) {
    $options['interface'] = (string) $data['interface'];
}

$result = $service->runBandwidthTestForRouter($routerIp, $options);

if (empty($result['success'])) {
    http_response_code(422);
} else {
    http_response_code(200);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
