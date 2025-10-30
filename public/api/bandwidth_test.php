<?php
declare(strict_types=1);

// Endpoint untuk menjalankan bandwidth test dari router yang dipilih.

ob_start();

require_once __DIR__ . '/../../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../../data/routers.json');
$service = new RouterService(
    $repository,
    __DIR__ . '/../../data/router_client.json',
    __DIR__ . '/../../data/routers_server.json'
);

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    $data = $_POST;
}

$routerIp = trim((string) ($data['router_ip'] ?? ''));

if ($routerIp === '') {
    $response = json_encode([
        'success' => false,
        'message' => 'Parameter router_ip wajib diisi.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (ob_get_length() > 0) {
        ob_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        http_response_code(400);
    }

    echo $response;

    if (ob_get_level() > 0) {
        ob_end_flush();
    }

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

$statusCode = 200;
$retryAfter = isset($result['retry_after']) ? (int) $result['retry_after'] : 0;

if (empty($result['success'])) {
    if ($retryAfter > 0) {
        $statusCode = 429;
    } else {
        $statusCode = 422;
    }
}

$responseBody = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (ob_get_length() > 0) {
    ob_clean();
}

if (!headers_sent($file, $line)) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    if ($statusCode === 429 && $retryAfter > 0) {
        header('Retry-After: ' . max(1, $retryAfter));
    }
    http_response_code($statusCode);
} else {
    error_log(sprintf(
        'bandwidth_test.php: headers already sent at %s:%d, unable to modify headers',
        (string) $file,
        (int) $line
    ));
}

echo $responseBody;

if (ob_get_level() > 0) {
    ob_end_flush();
}
