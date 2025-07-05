<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

$role = $_POST['role'] ?? '';
$marker_distance = $_POST['marker_distance'] ?? null;
$role = strtolower(trim($role));
if (!in_array($role, ['start','marker','finish'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}
if ($role === 'marker') {
    if ($marker_distance === null || $marker_distance === '') {
        http_response_code(400);
        echo json_encode(['error' => 'marker_distance required for marker']);
        exit;
    }
    $marker_distance = trim($marker_distance);
} else {
    $marker_distance = null;
}

try {
    $db = getDbConnection();
    $stmt = $db->prepare('INSERT INTO live_status (role, marker_distance, last_ping) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_ping = NOW()');
    $stmt->execute([$role, $marker_distance]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 