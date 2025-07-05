<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

$sender = trim($data['sender_name'] ?? '');
$message = trim($data['message'] ?? '');
$chat_password = $data['chat_password'] ?? '';

if ($sender === '' || $message === '' || $chat_password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'sender_name, message und chat_password required']);
    exit;
}

if (!defined('CHAT_PASSWORD') || $chat_password !== CHAT_PASSWORD) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid chat password']);
    exit;
}

try {
    $db = getDbConnection();
    $stmt = $db->prepare('INSERT INTO chat_messages (sender_name, message) VALUES (?, ?)');
    $stmt->execute([$sender, $message]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 