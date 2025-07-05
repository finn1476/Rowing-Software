<?php
// Include database connection
include_once 'config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check if required fields are provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Race participant ID is required'
    ]);
    exit;
}

// Get data
$id = (int)$_POST['id'];
$finishTime = isset($_POST['finish_time']) ? trim($_POST['finish_time']) : null;
$position = isset($_POST['position']) && !empty($_POST['position']) ? (int)$_POST['position'] : null;

try {
    $conn = getDbConnection();
    
    // Calculate seconds for sorting and calculations
    $finishSeconds = $finishTime ? timeToSeconds($finishTime) : null;
    
    // Update race participant
    $stmt = $conn->prepare("
        UPDATE race_participants 
        SET finish_time = :finish_time, finish_seconds = :finish_seconds, position = :position 
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':finish_time', $finishTime);
    $stmt->bindParam(':finish_seconds', $finishSeconds);
    $stmt->bindParam(':position', $position);
    $stmt->execute();
    
    // If position is set, also update the race status to completed
    if ($position) {
        $getRaceStmt = $conn->prepare("
            SELECT race_id FROM race_participants WHERE id = :id
        ");
        $getRaceStmt->bindParam(':id', $id);
        $getRaceStmt->execute();
        $raceId = $getRaceStmt->fetchColumn();
        
        if ($raceId) {
            $updateRaceStmt = $conn->prepare("
                UPDATE races SET status = 'completed' WHERE id = :race_id
            ");
            $updateRaceStmt->bindParam(':race_id', $raceId);
            $updateRaceStmt->execute();
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Result saved successfully',
        'data' => [
            'id' => $id,
            'finish_time' => $finishTime,
            'finish_seconds' => $finishSeconds,
            'formatted_time' => formatRaceTime($finishTime),
            'position' => $position
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 