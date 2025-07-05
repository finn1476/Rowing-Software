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
if (!isset($_POST['race_participant_id']) || empty($_POST['race_participant_id']) ||
    !isset($_POST['distance']) || empty($_POST['distance']) ||
    !isset($_POST['time']) || empty($_POST['time'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Race participant ID, distance, and time are required'
    ]);
    exit;
}

// Get data
$raceParticipantId = (int)$_POST['race_participant_id'];
$distance = (int)$_POST['distance'];
$time = trim($_POST['time']);

try {
    $conn = getDbConnection();
    
    // Calculate seconds elapsed
    $secondsElapsed = timeToSeconds($time);
    
    // First check if this distance time already exists
    $checkStmt = $conn->prepare("
        SELECT id FROM distance_times 
        WHERE race_participant_id = :race_participant_id AND distance = :distance
    ");
    $checkStmt->bindParam(':race_participant_id', $raceParticipantId);
    $checkStmt->bindParam(':distance', $distance);
    $checkStmt->execute();
    $existingId = $checkStmt->fetchColumn();
    
    if ($existingId) {
        // Update existing record
        $stmt = $conn->prepare("
            UPDATE distance_times 
            SET time = :time, seconds_elapsed = :seconds_elapsed 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $existingId);
        $stmt->bindParam(':time', $time);
        $stmt->bindParam(':seconds_elapsed', $secondsElapsed);
        $stmt->execute();
        
        $message = "Distance time updated successfully";
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO distance_times (race_participant_id, distance, time, seconds_elapsed)
            VALUES (:race_participant_id, :distance, :time, :seconds_elapsed)
        ");
        $stmt->bindParam(':race_participant_id', $raceParticipantId);
        $stmt->bindParam(':distance', $distance);
        $stmt->bindParam(':time', $time);
        $stmt->bindParam(':seconds_elapsed', $secondsElapsed);
        $stmt->execute();
        
        $message = "Distance time added successfully";
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'race_participant_id' => $raceParticipantId,
            'distance' => $distance,
            'time' => $time,
            'seconds_elapsed' => $secondsElapsed,
            'formatted_time' => formatRaceTime($time)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 