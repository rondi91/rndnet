<?php
// Endpoint untuk menyediakan data trafik interface ethernet dalam format JSON.

require_once __DIR__ . '/../../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../../data/routers.json');
$service = new RouterService($repository);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode($service->getEthernetTrafficByRouter(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
