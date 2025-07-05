<?php
// Include database connection
include_once 'config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if participant ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Race participant ID is required'
    ]);
    exit;
}

// Get participant ID
$participantId = (int)$_GET['id'];

try {
    $conn = getDbConnection();
    
    // Get participant details with race info
    $stmt = $conn->prepare("
        SELECT rp.*, r.*, e.name as event_name, t.name as team_name
        FROM race_participants rp
        JOIN races r ON rp.race_id = r.id
        JOIN events e ON r.event_id = e.id
        JOIN teams t ON rp.team_id = t.id
        WHERE rp.id = :id
    ");
    $stmt->bindParam(':id', $participantId);
    $stmt->execute();
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participant) {
        echo json_encode([
            'success' => false,
            'message' => 'Race participant not found'
        ]);
        exit;
    }
    
    // Get existing distance times for this participant
    $distanceStmt = $conn->prepare("
        SELECT dt.*
        FROM distance_times dt
        WHERE dt.race_participant_id = :race_participant_id
        ORDER BY dt.distance ASC
    ");
    $distanceStmt->bindParam(':race_participant_id', $participantId);
    $distanceStmt->execute();
    $distanceTimes = $distanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the participant data for response
    $raceData = [
        'id' => $participant['race_id'],
        'name' => $participant['name'],
        'event_name' => $participant['event_name'],
        'distance' => $participant['distance'],
        'distance_markers' => $participant['distance_markers'],
        'status' => $participant['status']
    ];
    
    $participantData = [
        'id' => $participant['id'],
        'team_id' => $participant['team_id'],
        'team_name' => $participant['team_name'],
        'lane' => $participant['lane'],
        'finish_time' => $participant['finish_time'],
        'formatted_finish_time' => $participant['finish_time'] ? formatRaceTime($participant['finish_time']) : null,
        'position' => $participant['position']
    ];
    
    // Return data
    echo json_encode([
        'success' => true,
        'race' => $raceData,
        'participant' => $participantData,
        'distance_times' => $distanceTimes
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 