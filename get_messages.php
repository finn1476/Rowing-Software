<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
header('Content-Type: application/json');

$chat_password = $_POST['chat_password'] ?? $_GET['chat_password'] ?? '';
if (!defined('CHAT_PASSWORD') || $chat_password !== CHAT_PASSWORD) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid chat password']);
    exit;
}

try {
    $db = getDbConnection();
    $stmt = $db->query('SELECT id, sender_name, message, created_at FROM chat_messages ORDER BY created_at DESC, id DESC LIMIT 100');
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $messages = array_reverse($messages); // Älteste zuerst
    echo json_encode(['messages' => $messages]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 