<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);

include_once 'config/database.php';
$conn = getDbConnection();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Get all upcoming races grouped by event - EXACTLY matching the original query
    $stmt = $conn->prepare("
        SELECT r.*, e.name as event_name, e.event_date, y.year, COUNT(rp.id) as participant_count
        FROM races r
        JOIN events e ON r.event_id = e.id
        JOIN years y ON e.year_id = y.id
        LEFT JOIN race_participants rp ON r.id = rp.race_id
        WHERE r.status = 'upcoming'
        GROUP BY r.id
        ORDER BY r.start_time ASC
    ");
    $stmt->execute();
    $races = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("AJAX Query: Found " . count($races) . " upcoming races");
    foreach ($races as $race) {
        error_log("Race: " . $race['name'] . " - Status: " . $race['status'] . " - Start: " . $race['start_time']);
    }
    
    
    // Organize races by date
    $racesByDate = [];
    foreach ($races as $race) {
        $date = date('Y-m-d', strtotime($race['start_time']));
        if (!isset($racesByDate[$date])) {
            $racesByDate[$date] = [];
        }
        $racesByDate[$date][] = $race;
    }
    
    // Add current server time for reference
    $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
    
    echo json_encode([
        'success' => true,
        'races' => $racesByDate,
        'server_time' => $now->format('Y-m-d H:i:s'),
        'total_races' => count($races)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
