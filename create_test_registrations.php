<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h1>Test-Anmeldungen erstellen</h1>";

try {
    $conn = getDbConnection();
    echo "<p>✅ Database connection established</p>";
    
    // Get all registration events
    $stmt = $conn->query("SELECT * FROM registration_events WHERE is_active = 1 ORDER BY event_date ASC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Verfügbare Events:</h2>";
    foreach ($events as $event) {
        echo "<p>- ID: {$event['id']}, Name: {$event['name']}, Datum: {$event['event_date']}</p>";
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $event_id = $_POST['event_id'] ?? 0;
        $num_boats = $_POST['num_boats'] ?? 5;
        $boat_types = $_POST['boat_types'] ?? ['2x'];
        
        echo "<h2>Erstelle Test-Anmeldungen...</h2>";
        
        $clubs = [
            'RV Hoya - 1926',
            'ASS - Nienburg', 
            'RG WSV Hannover',
            'Ruderclub Minden',
            'RV Weser Hameln',
            'RG Bückeburg',
            'RV Schaumburg',
            'RG Stadthagen'
        ];
        
        $first_names = [
            'Max', 'Anna', 'Tom', 'Lisa', 'Finn', 'Emma', 'Lukas', 'Sarah',
            'Paul', 'Marie', 'Jan', 'Sophie', 'Tim', 'Laura', 'Felix', 'Julia',
            'Niklas', 'Hannah', 'Leon', 'Lea', 'Jonas', 'Mia', 'Julian', 'Lena'
        ];
        
        $last_names = [
            'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer',
            'Wagner', 'Becker', 'Schulz', 'Hoffmann', 'Schäfer', 'Koch',
            'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann',
            'Schwarz', 'Zimmermann', 'Braun', 'Krüger', 'Hofmann', 'Hartmann'
        ];
        
        $created_count = 0;
        
        foreach ($boat_types as $boat_type) {
            for ($i = 0; $i < $num_boats; $i++) {
                $club = $clubs[array_rand($clubs)];
                $boat_name = $club . ' ' . $boat_type;
                
                // Generate crew members based on boat type
                $crew_count = getRequiredCrewCount($boat_type);
                $crew_members = [];
                
                for ($j = 0; $j < $crew_count; $j++) {
                    $crew_members[] = [
                        'first_name' => $first_names[array_rand($first_names)],
                        'last_name' => $last_names[array_rand($last_names)],
                        'birth_year' => rand(1995, 2008)
                    ];
                }
                
                // Create boat registration
                $stmt = $conn->prepare("INSERT INTO registration_boats (
                    event_id, club_name, boat_name, boat_type, captain_name, 
                    captain_birth_year, crew_members, contact_email, contact_phone, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $captain_name = $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
                $contact_email = strtolower($first_names[array_rand($first_names)]) . '@test.de';
                $contact_phone = '0' . rand(100, 999) . ' ' . rand(1000000, 9999999);
                
                $stmt->execute([
                    $event_id,
                    $club,
                    $boat_name,
                    $boat_type,
                    $captain_name,
                    rand(1970, 1990), // Captain birth year
                    json_encode($crew_members),
                    $contact_email,
                    $contact_phone,
                    'approved' // Set to approved for testing
                ]);
                
                $created_count++;
                echo "<p>✅ Erstellt: {$boat_name} - {$captain_name}</p>";
            }
        }
        
        echo "<h3>🎉 {$created_count} Test-Anmeldungen erfolgreich erstellt!</h3>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

function getRequiredCrewCount($boat_type) {
    switch ($boat_type) {
        case '1x': return 1;
        case '2x': return 2;
        case '3x+': return 3;
        case '4x': return 4;
        default: return 1;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test-Anmeldungen erstellen</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, input { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
        .checkbox-group { margin: 10px 0; }
        .checkbox-group label { display: inline; font-weight: normal; margin-left: 5px; }
    </style>
</head>
<body>
    <form method="POST">
        <div class="form-group">
            <label for="event_id">Event auswählen:</label>
            <select name="event_id" id="event_id" required>
                <option value="">Bitte wählen Sie ein Event</option>
                <?php foreach ($events as $event): ?>
                <option value="<?= $event['id'] ?>">
                    <?= htmlspecialchars($event['name']) ?> (<?= date('d.m.Y', strtotime($event['event_date'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="num_boats">Anzahl Boote pro Typ:</label>
            <input type="number" name="num_boats" id="num_boats" value="5" min="1" max="20" required>
        </div>
        
        <div class="form-group">
            <label>Boot-Typen:</label>
            <div class="checkbox-group">
                <input type="checkbox" name="boat_types[]" value="1x" id="1x">
                <label for="1x">1x (Einer)</label>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" name="boat_types[]" value="2x" id="2x" checked>
                <label for="2x">2x (Zweier)</label>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" name="boat_types[]" value="3x+" id="3x+">
                <label for="3x+">3x+ (Dreier und mehr)</label>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" name="boat_types[]" value="4x" id="4x">
                <label for="4x">4x (Vierer)</label>
            </div>
        </div>
        
        <button type="submit">Test-Anmeldungen erstellen</button>
    </form>
    
    <hr>
    
    <h3>Links:</h3>
    <p><a href="pages/race_creator.php">→ Race Creator</a></p>
    <p><a href="pages/registration_admin.php">→ Meldeportal Admin</a></p>
    <p><a href="pages/registration.php">→ Meldeportal</a></p>
    
    <h3>Beispiel-Verwendung:</h3>
    <ul>
        <li><strong>Event auswählen</strong>: Wählen Sie das Event für die Test-Anmeldungen</li>
        <li><strong>Anzahl Boote</strong>: Wie viele Boote pro Typ erstellt werden sollen</li>
        <li><strong>Boot-Typen</strong>: Welche Boot-Typen erstellt werden sollen</li>
        <li><strong>Status</strong>: Alle Test-Anmeldungen werden automatisch als "approved" erstellt</li>
    </ul>
    
    <h3>Generierte Daten:</h3>
    <ul>
        <li><strong>Vereine</strong>: Zufällig aus 8 verschiedenen Vereinen</li>
        <li><strong>Namen</strong>: Zufällige Kombinationen aus 24 Vor- und Nachnamen</li>
        <li><strong>Geburtsjahre</strong>: Zufällig zwischen 1995-2008 (Crew) und 1970-1990 (Melder)</li>
        <li><strong>Kontaktdaten</strong>: Generierte E-Mail-Adressen und Telefonnummern</li>
    </ul>
</body>
</html> 