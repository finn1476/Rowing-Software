<?php
require_once 'config/database.php';

// Headers für JSON-Antwort
header('Content-Type: application/json');

// Parameter prüfen
$raceId = isset($_GET['race_id']) ? (int)$_GET['race_id'] : 0;
$lane = isset($_GET['lane']) ? (int)$_GET['lane'] : 0;
$excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

if (!$raceId || !$lane) {
    echo json_encode([
        'success' => false,
        'message' => 'Race ID and lane are required',
        'is_occupied' => false
    ]);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Prüfen, ob die Lane bereits belegt ist, aber den aktuellen Teilnehmer ausschließen
    $sql = "
        SELECT rp.id, p.name as participant_name
        FROM race_participants rp
        JOIN participants p ON rp.participant_id = p.id
        WHERE rp.race_id = :race_id AND rp.lane = :lane
    ";
    
    // Wenn eine exclude_id angegeben wurde, diesen Teilnehmer ausschließen
    if ($excludeId > 0) {
        $sql .= " AND rp.id != :exclude_id";
    }
    
    $sql .= " LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':race_id', $raceId);
    $stmt->bindParam(':lane', $lane);
    
    if ($excludeId > 0) {
        $stmt->bindParam(':exclude_id', $excludeId);
    }
    
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'is_occupied' => true,
            'participant_name' => $result['participant_name']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'is_occupied' => false
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'is_occupied' => false
    ]);
} 