<?php
// Endpoint JSON untuk menambahkan router baru melalui permintaan AJAX dari
// halaman antarmuka.

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

$name = $payload['name'] ?? '';
$ipAddress = $payload['ip_address'] ?? '';
$username = $payload['username'] ?? '';
$password = $payload['password'] ?? '';
$notes = $payload['notes'] ?? '';
$isPppoeServer = !empty($payload['is_pppoe_server']);
$pppoeClient = [];

if (isset($payload['pppoe_client']) && is_array($payload['pppoe_client'])) {
    $pppoeClient = $payload['pppoe_client'];
}

$result = $service->addRouter($name, $ipAddress, $username, $password, $notes, $isPppoeServer);

if ($result['success']) {
    if (!empty($pppoeClient)) {
        $service->registerRouterClient(
            [
                'name' => $name,
                'ip_address' => $ipAddress,
                'username' => $username,
                'password' => $password,
                'notes' => $notes,
            ],
            $pppoeClient
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Router berhasil ditambahkan.',
    ]);

    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Router gagal ditambahkan.',
    'errors' => $result['errors'] ?? [],
]);
