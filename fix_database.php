<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Fix</h1>";

try {
    require_once 'config/database.php';
    echo "<p>✅ Database config loaded</p>";
    
    $conn = getDbConnection();
    echo "<p>✅ Database connection established</p>";
    
    // Force database initialization to update structure
    echo "<p>🔄 Updating database structure...</p>";
    initializeDatabase();
    echo "<p>✅ Database structure updated</p>";
    
    // Test the registration again
    echo "<h2>Testing registration...</h2>";
    
    $_POST = [
        'action' => 'register_boat',
        'event_id' => '1',
        'boat_type' => '2x',
        'team_id' => '1',
        'melder_name' => 'Finn Rückemann',
        'contact_email' => '',
        'contact_phone' => '',
        'crew_first_names' => ['Finn', 'Linn'],
        'crew_last_names' => ['Rückemann', 'Rückemann'],
        'crew_birth_years' => ['2004', '2006']
    ];
    
    // Test database insertion
    try {
        $event_id = $_POST['event_id'];
        $boat_type = $_POST['boat_type'];
        $team_id = $_POST['team_id'];
        $melder_name = $_POST['melder_name'];
        $contact_email = $_POST['contact_email'] ?? '';
        $contact_phone = $_POST['contact_phone'] ?? '';
        
        // Get team name
        $stmt = $conn->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        $club_name = $team ? $team['name'] : '';
        
        // Process crew
        $crew_members = [];
        if (isset($_POST['crew_first_names']) && is_array($_POST['crew_first_names'])) {
            foreach ($_POST['crew_first_names'] as $index => $first_name) {
                if (!empty($first_name) && !empty($_POST['crew_last_names'][$index]) && !empty($_POST['crew_birth_years'][$index])) {
                    $crew_members[] = [
                        'first_name' => $first_name,
                        'last_name' => $_POST['crew_last_names'][$index],
                        'birth_year' => $_POST['crew_birth_years'][$index]
                    ];
                }
            }
        }
        
        $boat_name = $club_name . ' ' . $boat_type;
        
        $stmt = $conn->prepare("INSERT INTO registration_boats (event_id, boat_name, boat_type, club_name, captain_name, captain_birth_year, crew_members, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$event_id, $boat_name, $boat_type, $club_name, $melder_name, null, json_encode($crew_members), $contact_email, $contact_phone]);
        
        echo "<p>✅ Registration test successful!</p>";
        echo "<p>Boat name: $boat_name</p>";
        echo "<p>Crew members: " . count($crew_members) . "</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Registration test failed: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Fatal error: " . $e->getMessage() . "</p>";
}
?> 