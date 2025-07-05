<?php
// Include database connection
include_once 'config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if race ID is provided
if (!isset($_GET['race_id']) || empty($_GET['race_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Race ID is required'
    ]);
    exit;
}

// Get race ID
$raceId = (int)$_GET['race_id'];

try {
    $conn = getDbConnection();
    
    // Get race details
    $raceStmt = $conn->prepare("
        SELECT r.*, e.name as event_name
        FROM races r
        JOIN events e ON r.event_id = e.id
        WHERE r.id = :race_id
    ");
    $raceStmt->bindParam(':race_id', $raceId);
    $raceStmt->execute();
    $race = $raceStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$race) {
        echo json_encode([
            'success' => false,
            'message' => 'Race not found'
        ]);
        exit;
    }
    
    // Get participants for this race
    $participantsStmt = $conn->prepare("
        SELECT rp.*, t.name as team_name
        FROM race_participants rp
        JOIN teams t ON rp.team_id = t.id
        WHERE rp.race_id = :race_id
        ORDER BY rp.lane ASC
    ");
    $participantsStmt->bindParam(':race_id', $raceId);
    $participantsStmt->execute();
    $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return data
    echo json_encode([
        'success' => true,
        'race_id' => $raceId,
        'race_name' => $race['name'] . ' (' . $race['event_name'] . ')',
        'participants' => $participants
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 