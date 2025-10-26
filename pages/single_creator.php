<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$conn = getDbConnection();

// Debug variable to collect all debug information
$debug_info = [];
$debug_enabled = isset($_GET['debug']) && $_GET['debug'] == '1';

// Debug function to add messages to debug info
function addDebug($message) {
    global $debug_info, $debug_enabled;
    if ($debug_enabled) {
        $debug_info[] = date('H:i:s') . ' - ' . $message;
    }
    error_log($message); // Always log to error log
}

// Safe debug function for arrays
function addDebugArray($label, $array) {
    global $debug_info, $debug_enabled;
    if ($debug_enabled) {
        $debug_info[] = date('H:i:s') . ' - ' . $label . ': ' . json_encode($array, JSON_PRETTY_PRINT);
    }
    error_log($label . ': ' . json_encode($array));
}

// Get selected event from URL
$selected_event_id = $_GET['event_id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_boat_from_singles':
                $event_id = $_POST['event_id'] ?? $selected_event_id ?? 0;
                $boat_type = $_POST['boat_type'];
                $participant_ids = $_POST['participant_ids'] ?? '';
                // Convert comma-separated string to array
                if (is_string($participant_ids) && !empty($participant_ids)) {
                    $participant_ids = explode(',', $participant_ids);
                } elseif (empty($participant_ids)) {
                    $participant_ids = [];
                }
                // DEBUG: Log all participant IDs
                addDebug("=== SINGLE CREATOR DEBUG START ===");
                addDebug("DEBUG: Participant IDs: " . implode(', ', $participant_ids));
                
                // Get club name from first participant
                $stmt_participant = $conn->prepare("SELECT club_name FROM registration_singles WHERE id = ?");
                $stmt_participant->execute([$participant_ids[0]]);
                $first_participant = $stmt_participant->fetch(PDO::FETCH_ASSOC);
                
                addDebug("DEBUG: First participant ID: {$participant_ids[0]}");
                addDebugArray("DEBUG: First participant data", $first_participant);
                
                // Try to get club name from first participant, if null try to get from any participant
                $club_name = $first_participant['club_name'] ?? null;
                addDebug("DEBUG: Initial club_name from first participant: '" . ($club_name ?? 'NULL') . "'");
                
                if (empty($club_name)) {
                    addDebug("DEBUG: First participant club_name is empty, searching other participants...");
                    // Try to get club name from any of the participants
                    foreach ($participant_ids as $pid) {
                        $stmt_club = $conn->prepare("SELECT club_name FROM registration_singles WHERE id = ? AND club_name IS NOT NULL AND club_name != ''");
                        $stmt_club->execute([$pid]);
                        $club_data = $stmt_club->fetch(PDO::FETCH_ASSOC);
                        addDebugArray("DEBUG: Participant $pid club search result", $club_data);
                        if ($club_data && !empty($club_data['club_name'])) {
                            $club_name = $club_data['club_name'];
                            addDebug("DEBUG: Found club_name '$club_name' from participant $pid");
                            break;
                        }
                    }
                }
                
                // Final fallback - use a default club name
                if (empty($club_name)) {
                    $club_name = 'RC Stolzenau'; // Default club name
                    addDebug("DEBUG: Using fallback club_name: '$club_name'");
                }
                
                addDebug("DEBUG: Final club_name for boat: '$club_name'");
                
                
                if (empty($participant_ids)) {
                    $message = "Keine Teilnehmer ausgewählt!";
                    $messageType = "error";
                    break;
                }
                
                // Validate participant count matches boat type
                $required_count = 0;
                switch ($boat_type) {
                    case '1x': $required_count = 1; break;
                    case '2x': $required_count = 2; break;
                    case '3x+': $required_count = 3; break;
                    case '4x': $required_count = 4; break;
                }
                
                if (count($participant_ids) !== $required_count) {
                    $message = "Anzahl der Teilnehmer muss für {$boat_type} genau {$required_count} sein!";
                    $messageType = "error";
                    break;
                }
                
                try {
                    $conn->beginTransaction();
                    
                    // Generate boat name automatically
                    $boat_name = $club_name . ' ' . $boat_type;
                    
                    // Create boat registration
                    $stmt = $conn->prepare("INSERT INTO registration_boats (
                        event_id, club_name, boat_name, boat_type, captain_name, 
                        captain_birth_year, crew_members, contact_email, contact_phone, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    // Use first participant as captain
                    $stmt_participant = $conn->prepare("SELECT name, birth_year, club_name FROM registration_singles WHERE id = ?");
                    $stmt_participant->execute([$participant_ids[0]]);
                    $captain = $stmt_participant->fetch(PDO::FETCH_ASSOC);
                    
                    // Create crew members array
                    $crew_members = [];
                    addDebug("DEBUG: Starting crew members creation...");
                    
                    foreach ($participant_ids as $participant_id) {
                        addDebug("DEBUG: Processing participant ID: $participant_id");
                        
                        $stmt_participant->execute([$participant_id]);
                        $participant = $stmt_participant->fetch(PDO::FETCH_ASSOC);
                        
                        addDebugArray("DEBUG: Participant $participant_id data", $participant);
                        
                        $name_parts = explode(' ', $participant['name'], 2);
                        
                        // Use individual club_name for each crew member
                        $participant_club = $participant['club_name'] ?? 'Unbekannt';
                        addDebug("DEBUG: Participant $participant_id club_name: '" . ($participant['club_name'] ?? 'NULL') . "' -> Final: '$participant_club'");
                        
                        $crew_member = [
                            'first_name' => $name_parts[0] ?? '',
                            'last_name' => $name_parts[1] ?? '',
                            'birth_year' => $participant['birth_year'],
                            'club_name' => $participant_club
                        ];
                        
                        addDebugArray("DEBUG: Created crew member for $participant_id", $crew_member);
                        $crew_members[] = $crew_member;
                    }
                    
                    addDebugArray("DEBUG: Final crew members array", $crew_members);
                    
                    
                    addDebug("DEBUG: About to execute boat creation with:");
                    addDebug("DEBUG: - event_id: $event_id");
                    addDebug("DEBUG: - club_name: '$club_name'");
                    addDebug("DEBUG: - boat_name: '$boat_name'");
                    addDebug("DEBUG: - boat_type: '$boat_type'");
                    addDebug("DEBUG: - captain name: '{$captain['name']}'");
                    addDebug("DEBUG: - captain birth_year: '{$captain['birth_year']}'");
                    addDebug("DEBUG: - crew_members JSON: " . json_encode($crew_members));
                    
                    $stmt->execute([
                        $event_id,
                        $club_name,
                        $boat_name,
                        $boat_type,
                        $captain['name'],
                        $captain['birth_year'],
                        json_encode($crew_members),
                        'auto-generated@example.com',
                        '0000000000',
                        'approved'
                    ]);
                    
                    addDebug("DEBUG: Boat creation executed successfully!");
                    
                    // Update singles races_completed count
                    foreach ($participant_ids as $participant_id) {
                        $stmt = $conn->prepare("UPDATE registration_singles SET races_completed = races_completed + 1 WHERE id = ?");
                        $stmt->execute([$participant_id]);
                        
                        // Check if participant has completed all desired races
                        $stmt = $conn->prepare("SELECT races_completed, desired_races FROM registration_singles WHERE id = ?");
                        $stmt->execute([$participant_id]);
                        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($participant && $participant['races_completed'] >= $participant['desired_races']) {
                            // Mark as used if all races completed
                            $stmt = $conn->prepare("UPDATE registration_singles SET status = 'used' WHERE id = ?");
                            $stmt->execute([$participant_id]);
                        }
                    }
                    
                    $conn->commit();
                    addDebug("DEBUG: Transaction committed successfully!");
                    $message = "Boot erfolgreich aus Einzel-Anmeldungen erstellt!";
                    $messageType = "success";
                    addDebug("=== SINGLE CREATOR DEBUG END ===");
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    $message = "Fehler beim Erstellen des Boots: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get all registration events for dropdown
$stmt = $conn->query("SELECT * FROM registration_events ORDER BY event_date DESC");
$registration_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available singles for selected event (only those who haven't completed all desired races)
if ($selected_event_id) {
    addDebug("DEBUG: Loading singles for event ID: $selected_event_id");
    $stmt = $conn->prepare("SELECT * FROM registration_singles WHERE event_id = ? AND status = 'approved' AND races_completed < desired_races ORDER BY name");
    $stmt->execute([$selected_event_id]);
    $available_singles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    addDebug("DEBUG: Found " . count($available_singles) . " available singles");
    foreach ($available_singles as $single) {
        addDebug("DEBUG: Single ID: {$single['id']}, Name: '{$single['name']}', Club: '" . ($single['club_name'] ?? 'NULL') . "'");
    }
} else {
    $available_singles = [];
    addDebug("DEBUG: No event selected, no singles loaded");
}

// Get existing boats created from singles
if ($selected_event_id) {
    $stmt = $conn->prepare("SELECT * FROM registration_boats WHERE event_id = ? AND status != 'rejected' ORDER BY created_at DESC");
    $stmt->execute([$selected_event_id]);
    $existing_boats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $existing_boats = [];
}

// Get all unique clubs from registration_boats table
$stmt = $conn->query("SELECT DISTINCT club_name FROM registration_boats WHERE club_name IS NOT NULL AND club_name != '' ORDER BY club_name");
$available_clubs = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Single Creator - Regatta System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .creator-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .nav-links {
            margin-top: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            padding: 8px 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #4facfe;
            font-size: 1.5em;
        }
        
        .event-selector {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .event-selector select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .drag-container {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .available-singles, .boat-creation, .existing-boats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            min-height: 400px;
        }
        
        .available-singles h3, .boat-creation h3, .existing-boats h3 {
            color: #4facfe;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .single-item {
            background: white;
            border: 2px solid #4facfe;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: grab;
            transition: all 0.3s ease;
        }
        
        .single-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(79, 172, 254, 0.3);
        }
        
        .single-item.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }
        
        .single-item.selected {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        
        .boat-form {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(79, 172, 254, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .selected-participants {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .selected-participant {
            background: white;
            border: 1px solid #4facfe;
            border-radius: 4px;
            padding: 5px 10px;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .remove-participant {
            background: #f44336;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .boat-item {
            background: white;
            border: 2px solid #4facfe;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .boat-item h4 {
            margin: 0 0 10px 0;
            color: #4facfe;
        }
        
        .participant-list {
            font-size: 14px;
            color: #666;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .placeholder {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 40px 20px;
        }
    </style>
</head>
<body>
    <div class="creator-container">
        <div class="header">
            <h1>👥 Single Creator</h1>
            <p>Erstellen Sie Boote aus Einzel-Anmeldungen</p>
            <?php if (!$debug_enabled): ?>
            <p style="font-size: 0.9em; opacity: 0.8;">💡 <strong>Debug-Tipp:</strong> Fügen Sie <code>?debug=1</code> zur URL hinzu für Debug-Informationen</p>
            <?php endif; ?>
            <div class="nav-links">
                <a href="registration_admin.php">← Zurück zum Admin</a>
                <a href="race_creator.php">🚣‍♀️ Race Creator</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </div>

        <?php if (isset($message)): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($debug_info) && $debug_enabled): ?>
        <div class="section" style="background: #f8f9fa; border: 2px solid #dc3545;">
            <h2 style="color: #dc3545;">🔍 Debug Information</h2>
            <div style="background: white; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;">
                <?php foreach ($debug_info as $debug_line): ?>
                <div style="margin-bottom: 5px; padding: 2px; border-bottom: 1px solid #eee;">
                    <?= htmlspecialchars($debug_line) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Event Selection -->
        <div class="event-selector">
            <h3 style="color: #4facfe; margin-bottom: 15px;">📅 Event auswählen</h3>
            <select id="eventSelect" onchange="changeEvent()">
                <option value="">Bitte wählen Sie ein Event aus</option>
                <?php foreach ($registration_events as $event): ?>
                <option value="<?= $event['id'] ?>" <?= $selected_event_id == $event['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($event['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($selected_event_id): ?>
        <div class="drag-container">
            <!-- Available Singles -->
            <div class="available-singles">
                <h3>📋 Verfügbare Einzel-Anmeldungen</h3>
                <?php if (empty($available_singles)): ?>
                <div class="placeholder">
                    Keine verfügbaren Einzel-Anmeldungen für dieses Event
                </div>
                <?php else: ?>
                <?php foreach ($available_singles as $single): ?>
                <div class="single-item" draggable="true" data-id="<?= $single['id'] ?>" data-name="<?= htmlspecialchars($single['name']) ?>" data-birth="<?= $single['birth_year'] ?>" data-club="<?= htmlspecialchars($single['club_name'] ?? 'Nicht angegeben') ?>" data-additional="<?= htmlspecialchars($single['additional_info'] ?? '') ?>" onclick="showSingleDetails(<?= $single['id'] ?>)">
                    <strong><?= htmlspecialchars($single['name']) ?></strong><br>
                    <small>Verein: <?= htmlspecialchars($single['club_name'] ?? 'Nicht angegeben') ?></small><br>
                    <small>Geburtsjahr: <?= $single['birth_year'] ?></small><br>
                    <small>Bevorzugt: <?= 
                        is_string($single['preferred_boat_types']) ? 
                        htmlspecialchars($single['preferred_boat_types']) : 
                        htmlspecialchars(implode(', ', json_decode($single['preferred_boat_types'], true) ?: []))
                    ?></small><br>
                    <small>Erfahrung: <?= 
                        $single['experience_level'] === 'beginner' ? 'Anfänger' :
                        ($single['experience_level'] === 'intermediate' ? 'Fortgeschritten' : 'Erfahren')
                    ?></small><br>
                    <small>Rennen: <?= $single['races_completed'] ?>/<?= $single['desired_races'] ?></small>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Boat Creation -->
            <div class="boat-creation">
                <h3>🚣‍♀️ Boot erstellen</h3>
                <div class="boat-form">
                    <form method="POST" id="boatForm">
                        <input type="hidden" name="action" value="create_boat_from_singles">
                        <input type="hidden" name="event_id" value="<?= $selected_event_id ?>">
                        <input type="hidden" name="participant_ids" id="participantIds">
                        
                        <div class="form-group">
                            <label for="boat_type">Boot-Typ:</label>
                            <select name="boat_type" id="boatType" required onchange="updateRequiredCount()">
                                <option value="">Bitte wählen</option>
                                <option value="1x">1x - Einer</option>
                                <option value="2x">2x - Zweier</option>
                                <option value="3x+">3x+ - Dreier und mehr</option>
                                <option value="4x">4x - Vierer</option>
                            </select>
                        </div>
                        
                        <div class="selected-participants" id="selectedParticipants">
                            <h4>Ausgewählte Teilnehmer:</h4>
                            <div id="participantList">
                                <div class="placeholder">Ziehen Sie Teilnehmer hierher</div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="createBoatBtn" disabled>
                            Boot erstellen
                        </button>
                    </form>
                </div>
            </div>

            <!-- Existing Boats -->
            <div class="existing-boats">
                <h3>📋 Erstellte Boote</h3>
                <?php if (empty($existing_boats)): ?>
                <div class="placeholder">
                    Noch keine Boote erstellt
                </div>
                <?php else: ?>
                <?php foreach ($existing_boats as $boat): ?>
                <div class="boat-item">
                    <h4><?= htmlspecialchars($boat['boat_name']) ?></h4>
                    <p><strong>Typ:</strong> <?= $boat['boat_type'] ?></p>
                    <p><strong>Verein:</strong> <?= htmlspecialchars($boat['club_name']) ?></p>
                    <p><strong>Melder:</strong> <?= htmlspecialchars($boat['captain_name']) ?></p>
                    <p><strong>Status:</strong> <?= ucfirst($boat['status']) ?></p>
                    <?php if ($boat['crew_members']): ?>
                    <div class="participant-list">
                        <strong>Crew:</strong><br>
                        <?php 
                        $crew = json_decode($boat['crew_members'], true);
                        foreach ($crew as $member): 
                        ?>
                        • <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?> (<?= $member['birth_year'] ?>)<br>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="section">
            <div class="placeholder">
                Bitte wählen Sie ein Event aus, um mit dem Single Creator zu beginnen
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Single Details Modal -->
    <div id="singleDetailsModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 20px; border-radius: 10px; width: 80%; max-width: 600px; max-height: 80%; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #4facfe;">
                <h3 style="margin: 0; color: #4facfe;">👤 Einzelanmeldung Details</h3>
                <span class="close" onclick="closeSingleDetails()" style="font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa;">&times;</span>
            </div>
            <div class="modal-body" id="singleDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer" style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #eee; text-align: right;">
                <button onclick="closeSingleDetails()" class="btn btn-primary">Schließen</button>
            </div>
        </div>
    </div>

    <script>
        let selectedParticipants = [];
        let requiredCount = 0;

        function changeEvent() {
            const eventId = document.getElementById('eventSelect').value;
            if (eventId) {
                window.location.href = 'single_creator.php?event_id=' + eventId;
            }
        }

        function updateRequiredCount() {
            const boatType = document.getElementById('boatType').value;
            switch (boatType) {
                case '1x': requiredCount = 1; break;
                case '2x': requiredCount = 2; break;
                case '3x+': requiredCount = 3; break;
                case '4x': requiredCount = 4; break;
                default: requiredCount = 0;
            }
            updateCreateButton();
        }

        function updateCreateButton() {
            const btn = document.getElementById('createBoatBtn');
            const isValid = selectedParticipants.length === requiredCount && requiredCount > 0;
            btn.disabled = !isValid;
            
            if (requiredCount > 0) {
                btn.textContent = `Boot erstellen (${selectedParticipants.length}/${requiredCount})`;
            } else {
                btn.textContent = 'Boot erstellen';
            }
        }

        function showSingleDetails(singleId) {
            const singleItem = document.querySelector(`[data-id="${singleId}"]`);
            if (!singleItem) return;
            
            const name = singleItem.dataset.name;
            const birth = singleItem.dataset.birth;
            const club = singleItem.dataset.club;
            const additional = singleItem.dataset.additional;
            
            const content = document.getElementById('singleDetailsContent');
            content.innerHTML = `
                <div style="margin-bottom: 15px;">
                    <strong>Name:</strong> ${name}<br>
                    <strong>Geburtsjahr:</strong> ${birth}<br>
                    <strong>Verein:</strong> ${club}
                </div>
                <div style="margin-bottom: 15px;">
                    <strong>Zusätzliche Informationen:</strong><br>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 5px; white-space: pre-wrap;">
                        ${additional || 'Keine zusätzlichen Informationen vorhanden.'}
                    </div>
                </div>
            `;
            
            document.getElementById('singleDetailsModal').style.display = 'block';
        }

        function closeSingleDetails() {
            document.getElementById('singleDetailsModal').style.display = 'none';
        }

        function removeParticipant(id) {
            selectedParticipants = selectedParticipants.filter(p => p.id !== id);
            updateParticipantList();
            updateCreateButton();
        }

        function updateParticipantList() {
            const list = document.getElementById('participantList');
            const participantIds = document.getElementById('participantIds');
            
            if (selectedParticipants.length === 0) {
                list.innerHTML = '<div class="placeholder">Ziehen Sie Teilnehmer hierher</div>';
                participantIds.value = '';
                return;
            }
            
            list.innerHTML = '';
            const ids = [];
            
            selectedParticipants.forEach(participant => {
                const div = document.createElement('div');
                div.className = 'selected-participant';
                div.innerHTML = `
                    <span>${participant.name} (${participant.birth})</span>
                    <button type="button" class="remove-participant" onclick="removeParticipant(${participant.id})">×</button>
                `;
                list.appendChild(div);
                ids.push(participant.id);
            });
            
            participantIds.value = ids.join(',');
        }

        // Drag and Drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const singleItems = document.querySelectorAll('.single-item');
            const selectedParticipantsDiv = document.getElementById('selectedParticipants');

            singleItems.forEach(item => {
                item.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', item.dataset.id);
                    item.classList.add('dragging');
                });

                item.addEventListener('dragend', function() {
                    item.classList.remove('dragging');
                });
            });

            selectedParticipantsDiv.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            selectedParticipantsDiv.addEventListener('drop', function(e) {
                e.preventDefault();
                const participantId = parseInt(e.dataTransfer.getData('text/plain'));
                const participantItem = document.querySelector(`[data-id="${participantId}"]`);
                
                if (participantItem && !selectedParticipants.find(p => p.id === participantId)) {
                    const participant = {
                        id: participantId,
                        name: participantItem.dataset.name,
                        birth: participantItem.dataset.birth
                    };
                    
                    selectedParticipants.push(participant);
                    updateParticipantList();
                    updateCreateButton();
                }
            });
        });
    </script>
</body>
</html> 