<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated as admin']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

$message_id = intval($_POST['message_id'] ?? ($_GET['message_id'] ?? 0));
if ($message_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'message_id required']);
    exit;
}

try {
    $db = getDbConnection();
    $stmt = $db->prepare('DELETE FROM chat_messages WHERE id = ?');
    $stmt->execute([$message_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 