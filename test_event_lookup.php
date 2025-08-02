<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h1>Test: Event Lookup</h1>";

try {
    $conn = getDbConnection();
    echo "<p>✅ Database connection established</p>";
    
    // Simulate race_creator.php logic
    $selected_event_id = $_GET['event_id'] ?? null;
    echo "<h2>Race Creator Logic:</h2>";
    echo "<p>selected_event_id: " . ($selected_event_id ?: 'NULL') . "</p>";
    
    // Test POST data
    echo "<h2>POST Data:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    // Test GET data
    echo "<h2>GET Data:</h2>";
    echo "<pre>";
    print_r($_GET);
    echo "</pre>";
    
    // Test event_id logic (like in race_creator.php)
    $event_id = $_POST['event_id'] ?? $selected_event_id ?? 0;
    echo "<h2>Event ID Logic (Race Creator):</h2>";
    echo "<p>POST event_id: " . ($_POST['event_id'] ?? 'NULL') . "</p>";
    echo "<p>selected_event_id: " . ($selected_event_id ?: 'NULL') . "</p>";
    echo "<p>Final event_id: " . $event_id . "</p>";
    
    // Test registration events
    echo "<h2>Registration Events:</h2>";
    $stmt = $conn->query("SELECT * FROM registration_events WHERE is_active = 1 ORDER BY id");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $event) {
        echo "<p>- ID: {$event['id']}, Name: '{$event['name']}', Main Event: {$event['main_event_id']}</p>";
    }
    
    // Test specific event lookup
    if ($event_id) {
        echo "<h2>Looking for Event ID: {$event_id}</h2>";
        
        $stmt = $conn->prepare("SELECT * FROM registration_events WHERE id = ?");
        $stmt->execute([$event_id]);
        $registration_event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registration_event) {
            echo "<p>✅ Registration Event gefunden:</p>";
            echo "<pre>";
            print_r($registration_event);
            echo "</pre>";
        } else {
            echo "<p>❌ Registration Event NICHT gefunden für ID: {$event_id}</p>";
        }
    } else {
        echo "<p>⚠️ Keine Event ID verfügbar</p>";
    }
    
    // Test form simulation
    echo "<h2>Form Test:</h2>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='event_id' value='2'>";
    echo "<input type='hidden' name='action' value='create_race'>";
    echo "<input type='hidden' name='boat_type' value='2x'>";
    echo "<input type='hidden' name='race_name' value='Test Rennen'>";
    echo "<input type='hidden' name='distance' value='1000'>";
    echo "<input type='hidden' name='start_time' value='10:00'>";
    echo "<input type='hidden' name='distance_markers' value='250,500,750'>";
    echo "<input type='hidden' name='participant_ids' value='[]'>";
    echo "<button type='submit'>Test Rennen erstellen</button>";
    echo "</form>";
    
    echo "<p><a href='pages/race_creator.php'>← Zurück zur Rennenerstellung</a></p>";
    echo "<p><a href='pages/race_creator.php?event_id=2'>→ Race Creator mit Event ID 2</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 