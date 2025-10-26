<?php
// Endpoint sederhana untuk menyediakan data PPPoE dalam format JSON
// sehingga dashboard dapat menampilkan informasi secara real-time.

require_once __DIR__ . '/../../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../../data/routers.json');
$service = new RouterService($repository);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode($service->getPppoeDashboardData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
