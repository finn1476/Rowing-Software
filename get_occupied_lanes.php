<?php
require_once 'config/database.php';

// Headers für JSON-Antwort
header('Content-Type: application/json');

// Race ID prüfen
$raceId = isset($_GET['race_id']) ? (int)$_GET['race_id'] : 0;
$excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

if (!$raceId) {
    echo json_encode([
        'success' => false,
        'message' => 'Race ID is required',
        'occupied_lanes' => []
    ]);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Abfrage der belegten Lanes für das gewählte Rennen
    $sql = "
        SELECT rp.lane, p.name as participant_name
        FROM race_participants rp
        JOIN participants p ON rp.participant_id = p.id
        WHERE rp.race_id = :race_id
    ";
    
    // Wenn eine exclude_id angegeben wurde, diesen Teilnehmer ausschließen
    if ($excludeId > 0) {
        $sql .= " AND rp.id != :exclude_id";
    }
    
    $sql .= " ORDER BY rp.lane";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':race_id', $raceId);
    
    if ($excludeId > 0) {
        $stmt->bindParam(':exclude_id', $excludeId);
    }
    
    $stmt->execute();
    
    $occupiedLanes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'occupied_lanes' => $occupiedLanes
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'occupied_lanes' => []
    ]);
} 