<?php
header('Content-Type: application/json');
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/Node.php";

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$nodeId = $_GET['node_id'] ?? null;

if (!$nodeId || !is_numeric($nodeId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid node ID']);
    exit;
}

$node = new Node();
$receivers = $node->getNodeReceivers($nodeId);

echo json_encode($receivers);
?> 