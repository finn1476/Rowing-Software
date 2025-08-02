<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Get selected event ID from URL parameter
$selected_event_id = $_GET['event_id'] ?? null;

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $conn = getDbConnection();
        
        switch ($action) {
            case 'create_race':
                $event_id = $_POST['event_id'] ?? $selected_event_id ?? 0;
                $boat_type = $_POST['boat_type'] ?? '';
                $race_name = $_POST['race_name'] ?? '';
                $distance = $_POST['distance'] ?? 1000;
                $start_time = $_POST['start_time'] ?? '';
                $distance_markers = $_POST['distance_markers'] ?? '';
                $participant_ids_json = $_POST['participant_ids'] ?? '[]';
                
                // Get registration event details
                $stmt = $conn->prepare("SELECT * FROM registration_events WHERE id = ?");
                $stmt->execute([$event_id]);
                $registration_event = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Debug information
                error_log("Event ID: " . $event_id);
                error_log("Registration Event: " . ($registration_event ? 'found' : 'not found'));
                if ($registration_event) {
                    error_log("Registration Event Details: " . json_encode($registration_event));
                }
                
                if ($registration_event) {
                    // Combine event date with start time
                    $full_start_time = $registration_event['event_date'] . ' ' . $start_time . ':00';
                    
                    // Create race
                    $participant_count = getBoatTypeParticipantCount($boat_type);
                    $stmt = $conn->prepare("INSERT INTO races (event_id, name, start_time, distance, distance_markers, participants_per_boat) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$registration_event['main_event_id'], $race_name, $full_start_time, $distance, $distance_markers, $participant_count]);
                    
                    $race_id = $conn->lastInsertId();
                    
                    // Parse participant IDs
                    $participant_ids = json_decode($participant_ids_json, true);
                    
                    // Add participants to race with boat numbers and lanes
                    $boat_number = 1;
                    $lane = 1;
                    
                    foreach ($participant_ids as $participant_data) {
                        $registration_boat_id = $participant_data['registration_boat_id'];
                        
                        // Get registration boat details
                        $stmt = $conn->prepare("SELECT * FROM registration_boats WHERE id = ?");
                        $stmt->execute([$registration_boat_id]);
                        $boat = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($boat) {
                            // Check if team already exists
                            $stmt = $conn->prepare("SELECT id FROM teams WHERE name = ?");
                            $stmt->execute([$boat['club_name']]);
                            $existing_team = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($existing_team) {
                                $team_id = $existing_team['id'];
                            } else {
                                // Create new team only if it doesn't exist
                                $stmt = $conn->prepare("INSERT INTO teams (name, description) VALUES (?, ?)");
                                $stmt->execute([$boat['club_name'], 'Boot: ' . $boat['boat_name']]);
                                $team_id = $conn->lastInsertId();
                            }
                            
                            // Add crew members as participants
                            if ($boat['crew_members']) {
                                $crew = json_decode($boat['crew_members'], true);
                                foreach ($crew as $member) {
                                    $full_name = $member['first_name'] . ' ' . $member['last_name'];
                                    
                                    // Check if participant already exists
                                    $stmt = $conn->prepare("SELECT id FROM participants WHERE name = ? AND birth_year = ?");
                                    $stmt->execute([$full_name, $member['birth_year']]);
                                    $existing_participant = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($existing_participant) {
                                        $participant_id = $existing_participant['id'];
                                    } else {
                                        // Create new participant only if it doesn't exist
                                        $stmt = $conn->prepare("INSERT INTO participants (team_id, name, birth_year) VALUES (?, ?, ?)");
                                        $stmt->execute([$team_id, $full_name, $member['birth_year']]);
                                        $participant_id = $conn->lastInsertId();
                                    }
                                    
                                    // Add to race participants with boat number and lane
                                    $stmt = $conn->prepare("INSERT INTO race_participants (race_id, team_id, participant_id, boat_number, lane, registration_boat_id) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([$race_id, $team_id, $participant_id, $boat_number, $lane, $registration_boat_id]);
                                }
                            }
                            
                            // Mark registration boat as used
                            $stmt = $conn->prepare("UPDATE registration_boats SET status = 'used' WHERE id = ?");
                            $stmt->execute([$registration_boat_id]);
                            
                            $boat_number++;
                            $lane++;
                        }
                    }
                    
                    $success_message = "Rennen '$race_name' erfolgreich erstellt!";
                } else {
                    $error_message = "Event nicht gefunden! Event ID: " . $event_id . " - Verfügbare Events: ";
                    $stmt = $conn->query("SELECT id, name FROM registration_events WHERE is_active = 1");
                    $available_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($available_events as $event) {
                        $error_message .= "ID " . $event['id'] . " (" . $event['name'] . "), ";
                    }
                    $error_message = rtrim($error_message, ", ");
                }
                break;
        }
    } catch (Exception $e) {
        $error_message = "Fehler beim Erstellen des Rennens: " . $e->getMessage();
    }
}

// Get database connection
$conn = getDbConnection();

// Get all registration events
$stmt = $conn->query("SELECT * FROM registration_events WHERE is_active = 1 ORDER BY event_date ASC");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all approved boat registrations grouped by type (exclude used ones)
if ($selected_event_id) {
    $stmt = $conn->prepare("SELECT * FROM registration_boats WHERE status = 'approved' AND event_id = ? ORDER BY club_name, boat_name");
    $stmt->execute([$selected_event_id]);
    $all_boats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $all_boats = [];
}

// Get existing races for this event (not finished)
if ($selected_event_id) {
    // First, get the main_event_id for this registration event
    $stmt = $conn->prepare("SELECT main_event_id FROM registration_events WHERE id = ?");
    $stmt->execute([$selected_event_id]);
    $registration_event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($registration_event && $registration_event['main_event_id']) {
        // Get races for the main event
        $stmt = $conn->prepare("SELECT r.*, COUNT(rp.id) as participant_count FROM races r 
                               LEFT JOIN race_participants rp ON r.id = rp.race_id 
                               WHERE r.event_id = ? AND r.status != 'finished' 
                               GROUP BY r.id 
                               ORDER BY r.start_time");
        $stmt->execute([$registration_event['main_event_id']]);
        $existing_races = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If no main_event_id, try to get races for the registration event directly
        $stmt = $conn->prepare("SELECT r.*, COUNT(rp.id) as participant_count FROM races r 
                               LEFT JOIN race_participants rp ON r.id = rp.race_id 
                               WHERE r.event_id = ? AND r.status != 'finished' 
                               GROUP BY r.id 
                               ORDER BY r.start_time");
        $stmt->execute([$selected_event_id]);
        $existing_races = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // If no event selected, show no races
    $existing_races = [];
}

$boats_by_type = [];
foreach ($all_boats as $boat) {
    $boat_type = $boat['boat_type'];
    if (!isset($boats_by_type[$boat_type])) {
        $boats_by_type[$boat_type] = [];
    }
    $boats_by_type[$boat_type][] = $boat;
}

// Helper function
function getBoatTypeParticipantCount($boat_type) {
    switch ($boat_type) {
        case '1x': return 1;
        case '2x': return 2;
        case '3x+': return 3;
        case '4x': return 4;
        default: return 1;
    }
}

function getBoatTypeName($boat_type) {
    switch ($boat_type) {
        case '1x': return 'Einer';
        case '2x': return 'Zweier';
        case '3x+': return 'Dreier und mehr';
        case '4x': return 'Vierer';
        default: return $boat_type;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rennenerstellung - Regatta System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .race-creator {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .boat-type-section {
            background: white;
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .boat-type-header {
            background: #3498db;
            color: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .drag-container {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            min-height: 400px;
        }
        
        .available-boats, .race-container {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 15px;
            min-height: 300px;
        }
        
        .available-boats.drag-over, .race-container.drag-over {
            border-color: #3498db;
            background: #e3f2fd;
        }
        
        .boat-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            margin: 8px 0;
            cursor: move;
            transition: all 0.2s;
        }
        
        .boat-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .boat-item.dragging {
            opacity: 0.5;
        }
        
        .race-item {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 6px;
            padding: 10px;
            margin: 8px 0;
            position: relative;
        }
        
        .remove-boat {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .race-form {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .create-race-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .create-race-btn:hover {
            background: #219a52;
        }
        
        .create-race-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .boat-count {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .existing-races {
            background: #f8f9fa;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 15px;
            min-height: 300px;
        }
        
        .existing-race {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .existing-race h4 {
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .participant-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .participant-item strong {
            color: #495057;
        }
        
        .participant-item small {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="race-creator">
        <header>
            <h1>Rennenerstellung</h1>
            <nav>
                <a href="registration_admin.php">← Zurück zum Meldeportal Admin</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </header>

        <?php if (isset($success_message)): ?>
        <div class="success-message">
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="error-message">
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- Event Selection -->
        <div class="boat-type-section">
            <div class="boat-type-header">
                <h2>Regatta auswählen</h2>
            </div>
            <select id="event-select" style="padding: 10px; font-size: 16px; width: 300px;">
                <option value="">Bitte wählen Sie eine Regatta</option>
                <?php foreach ($events as $event): ?>
                <option value="<?= $event['id'] ?>" <?= $selected_event_id == $event['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($event['name']) ?> (<?= date('d.m.Y', strtotime($event['event_date'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Boat Type Sections -->
        <?php foreach (['1x', '2x', '3x+', '4x'] as $boat_type): ?>
        <div class="boat-type-section" id="section-<?= $boat_type ?>">
            <div class="boat-type-header">
                <h2><?= $boat_type ?> - <?= getBoatTypeName($boat_type) ?></h2>
                <span class="boat-count" id="count-<?= $boat_type ?>">
                    <?= isset($boats_by_type[$boat_type]) ? count($boats_by_type[$boat_type]) : 0 ?> verfügbar
                </span>
            </div>
            
            <!-- Debug info -->
            <div style="background: #f0f0f0; padding: 10px; margin-bottom: 10px; border-radius: 4px; font-size: 12px;">
                <strong>Debug:</strong> 
                <?php if (isset($boats_by_type[$boat_type])): ?>
                    <?= count($boats_by_type[$boat_type]) ?> Boote gefunden für <?= $boat_type ?>
                <?php else: ?>
                    Keine Boote für <?= $boat_type ?>
                <?php endif; ?>
                <br>
                <strong>Bestehende Rennen:</strong> 
                <?php 
                if ($selected_event_id) {
                    echo "Gesamt: " . count($existing_races) . " Rennen verfügbar<br>";
                    foreach ($existing_races as $race) {
                        echo "- '{$race['name']}' (ID: {$race['id']})<br>";
                    }
                    
                                    $event_races = array_filter($existing_races, function($race) use ($boat_type) {
                    // More precise matching for boat types
                    $race_name_trimmed = trim($race['name']);
                    $boat_type_trimmed = trim($boat_type);
                    
                    // Check for exact match first
                    if ($race_name_trimmed === $boat_type_trimmed) {
                        return true;
                    }
                    
                    // Check if race name starts with boat type (for cases like "2x " vs "2x")
                    if (strpos($race_name_trimmed, $boat_type_trimmed) === 0) {
                        return true;
                    }
                    
                    // Check for boat type within race name (for cases like "2x Rennen")
                    if (strpos($race_name_trimmed, $boat_type_trimmed) !== false) {
                        return true;
                    }
                    
                    // Special case for 3x+ (remove + for comparison)
                    if ($boat_type_trimmed === '3x+' && strpos($race_name_trimmed, '3x') !== false) {
                        return true;
                    }
                    
                    return false;
                });
                    echo "Gefiltert: " . count($event_races) . " Rennen für " . $boat_type;
                } else {
                    echo "Keine Event ausgewählt - bitte wählen Sie eine Regatta aus";
                }
                ?>
                <br>
                <strong>Event ID:</strong> <?= $selected_event_id ?: 'Keine ausgewählt' ?>
            </div>
            
            <div class="drag-container">
                <!-- Available Boats -->
                <div class="available-boats" id="available-<?= $boat_type ?>" data-boat-type="<?= $boat_type ?>">
                    <h3>Verfügbare Meldungen</h3>
                    <?php if (!$selected_event_id): ?>
                        <p style="color: #666; font-style: italic;">Bitte wählen Sie eine Regatta aus</p>
                    <?php elseif (isset($boats_by_type[$boat_type]) && count($boats_by_type[$boat_type]) > 0): ?>
                        <?php foreach ($boats_by_type[$boat_type] as $boat): ?>
                        <div class="boat-item" draggable="true" data-boat-id="<?= $boat['id'] ?>" data-boat-type="<?= $boat_type ?>">
                            <strong><?= htmlspecialchars($boat['club_name']) ?></strong>
                            <?php if ($boat['crew_members']): ?>
                            <br><small style="color: #666;">
                                <?php 
                                $crew = json_decode($boat['crew_members'], true);
                                $clubs = [];
                                foreach ($crew as $member) {
                                    if (isset($member['club']) && !in_array($member['club'], $clubs)) {
                                        $clubs[] = $member['club'];
                                    }
                                }
                                if (count($clubs) > 1) {
                                    echo "Crew: " . implode(', ', $clubs);
                                }
                                ?>
                            </small>
                            <?php endif; ?><br>
                            <small><?= htmlspecialchars($boat['boat_name']) ?></small><br>
                            <small>Teilnehmer im Boot:</small>
                            <?php if ($boat['crew_members']): ?>
                                <?php 
                                $crew = json_decode($boat['crew_members'], true);
                                foreach ($crew as $member): 
                                $club = $member['club'] ?? 'Unbekannt';
                                ?>
                                <br><small>• <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?> (<?= $member['birth_year'] ?>) - <?= htmlspecialchars($club) ?></small>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">Keine Meldungen verfügbar</p>
                    <?php endif; ?>
                </div>
                
                <!-- New Race Container -->
                <div class="race-container" id="race-<?= $boat_type ?>" data-boat-type="<?= $boat_type ?>">
                    <h3>Neues Rennen erstellen</h3>
                    <p style="color: #666; font-style: italic;">Ziehen Sie Meldungen hierher, um ein Rennen zu erstellen</p>
                    
                    <!-- Race Form -->
                    <div class="race-form" id="race-form-<?= $boat_type ?>" style="display: none;">
                        <h4>Rennen konfigurieren</h4>
                        <form method="POST" class="race-form-submit">
                            <input type="hidden" name="action" value="create_race">
                            <input type="hidden" name="event_id" id="event-id-<?= $boat_type ?>" value="<?= $selected_event_id ?>">
                            <input type="hidden" name="boat_type" value="<?= $boat_type ?>">
                            <input type="hidden" name="participant_ids" id="participant-ids-<?= $boat_type ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Rennenname:</label>
                                    <input type="text" name="race_name" id="race-name-<?= $boat_type ?>">
                                </div>
                                <div class="form-group">
                                    <label>Distanz (m):</label>
                                    <input type="number" name="distance" value="1000" required>
                                </div>
                                <div class="form-group">
                                    <label>Startzeit (nur Zeit):</label>
                                    <input type="time" name="start_time" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Abstandsmarken (m, kommagetrennt):</label>
                                <input type="text" name="distance_markers" placeholder="250, 500, 750" value="250, 500, 750">
                            </div>
                            
                            <button type="submit" class="create-race-btn">Rennen erstellen</button>
                            <button type="button" class="create-race-btn" style="background: #f39c12; margin-left: 10px;" onclick="clearRace('<?= $boat_type ?>')">Rennen löschen</button>
                        </form>
                    </div>
                    
                    <!-- Participants List -->
                    <div class="participants-list" id="participants-<?= $boat_type ?>">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
                
                <!-- Existing Races -->
                <div class="existing-races" id="existing-<?= $boat_type ?>">
                    <h3>Bestehende Rennen</h3>
                                    <?php 
                $event_races = array_filter($existing_races, function($race) use ($boat_type) {
                    // More flexible matching for boat types
                    $race_name_lower = strtolower($race['name']);
                    $boat_type_lower = strtolower($boat_type);
                    
                    // Check for exact match or partial match
                    $matches = $race_name_lower === $boat_type_lower || 
                              strpos($race_name_lower, $boat_type_lower) !== false ||
                              strpos($race_name_lower, str_replace('+', '', $boat_type_lower)) !== false;
                    
                    // Debug output
                    error_log("Race: '{$race['name']}' vs Boat Type: '{$boat_type}' = " . ($matches ? 'MATCH' : 'NO MATCH'));
                    
                    return $matches;
                });
                ?>
                    <?php if (!$selected_event_id): ?>
                        <p style="color: #666; font-style: italic;">Bitte wählen Sie eine Regatta aus</p>
                    <?php elseif (!empty($event_races)): ?>
                        <?php foreach ($event_races as $race): ?>
                            <div class="existing-race" data-race-id="<?= $race['id'] ?>">
                                <h4><?= htmlspecialchars($race['name']) ?></h4>
                                <p><strong>Distanz:</strong> <?= $race['distance'] ?>m</p>
                                <p><strong>Startzeit:</strong> <?= date('H:i', strtotime($race['start_time'])) ?></p>
                                <p><strong>Teilnehmer:</strong> <?= $race['participant_count'] ?></p>
                                <div class="race-participants">
                                    <h5>Teilnehmer im Rennen:</h5>
                                    <div class="participants-list" id="race-<?= $race['id'] ?>-participants">
                                        <!-- Will be populated by JavaScript -->
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-warning" onclick="removeBoatFromExistingRace(<?= $race['id'] ?>)">Boot entfernen</button>
                                    <button type="button" class="btn btn-info" onclick="addBoatToExistingRace(<?= $race['id'] ?>)">Boot hinzufügen</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">Keine bestehenden Rennen für <?= getBoatTypeName($boat_type) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Helper function
        function getBoatTypeName(boatType) {
            const names = {
                '1x': 'Einer',
                '2x': 'Zweier',
                '3x+': 'Dreier und mehr',
                '4x': 'Vierer'
            };
            return names[boatType] || boatType;
        }
        
        // Load participants for all existing races on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Load participants for existing races
            document.querySelectorAll('.existing-race').forEach(raceElement => {
                const raceId = raceElement.dataset.raceId;
                if (raceId) {
                    loadExistingRaceParticipants(raceId);
                }
            });
        });

        // Drag and Drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const eventSelect = document.getElementById('event-select');
            
            // Update event ID for all forms when event is selected
            eventSelect.addEventListener('change', function() {
                const eventId = this.value;
                document.querySelectorAll('[id^="event-id-"]').forEach(input => {
                    input.value = eventId;
                });
                
                // Only reload if the event actually changed and is different from current URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentEventId = urlParams.get('event_id');
                
                if (eventId && eventId !== currentEventId) {
                    // Store the selected event in sessionStorage to preserve it
                    sessionStorage.setItem('selectedEventId', eventId);
                    window.location.href = `race_creator.php?event_id=${eventId}`;
                }
            });
            
            // No automatic restoration to prevent infinite loops
            // The event selection is handled by PHP based on URL parameters
            
            // Drag and drop for each boat type
            ['1x', '2x', '3x+', '4x'].forEach(boatType => {
                const availableContainer = document.getElementById(`available-${boatType}`);
                const raceContainer = document.getElementById(`race-${boatType}`);
                const raceForm = document.getElementById(`race-form-${boatType}`);
                const participantIdsInput = document.getElementById(`participant-ids-${boatType}`);
                
                let selectedBoats = [];
                
                // Drag start
                availableContainer.addEventListener('dragstart', function(e) {
                    if (e.target.classList.contains('boat-item')) {
                        e.target.classList.add('dragging');
                        e.dataTransfer.setData('text/plain', e.target.dataset.boatId);
                    }
                });
                
                // Drag end
                availableContainer.addEventListener('dragend', function(e) {
                    if (e.target.classList.contains('boat-item')) {
                        e.target.classList.remove('dragging');
                    }
                });
                
                // Drag over
                raceContainer.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('drag-over');
                });
                
                // Drag leave
                raceContainer.addEventListener('dragleave', function(e) {
                    this.classList.remove('drag-over');
                });
                
                // Drop
                raceContainer.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');
                    
                    const boatId = e.dataTransfer.getData('text/plain');
                    const boatElement = document.querySelector(`[data-boat-id="${boatId}"]`);
                    
                    if (boatElement && boatElement.dataset.boatType === boatType) {
                        // Check if we already have 4 boats in this race
                        const currentBoats = this.querySelectorAll('.race-item');
                        if (currentBoats.length >= 4) {
                            alert('Maximal 4 Boote pro Rennen erlaubt!');
                            return;
                        }
                        
                        // Check if boat is already in this race
                        if (this.querySelector(`[data-boat-id="${boatId}"]`)) {
                            alert('Dieses Boot ist bereits im Rennen!');
                            return;
                        }
                        
                        // Add boat to race
                        const raceItem = document.createElement('div');
                        raceItem.className = 'race-item';
                        raceItem.dataset.boatId = boatId;
                        raceItem.innerHTML = `
                            ${boatElement.innerHTML}
                            <button type="button" class="remove-boat" onclick="removeBoatFromRace('${boatType}', '${boatId}')">×</button>
                        `;
                        
                        // Insert before the form
                        const form = this.querySelector('.race-form');
                        this.insertBefore(raceItem, form);
                        
                        // Add to selected boats
                        selectedBoats.push({
                            registration_boat_id: boatId,
                            boat_element: boatElement
                        });
                        
                        // Update participant IDs
                        participantIdsInput.value = JSON.stringify(selectedBoats.map(b => ({ registration_boat_id: b.registration_boat_id })));
                        
                        // Show form if we have boats
                        if (selectedBoats.length > 0) {
                            raceForm.style.display = 'block';
                            this.querySelector('p').style.display = 'none';
                        }
                        
                        // Hide boat from available list
                        boatElement.style.display = 'none';
                    }
                });
            });
        });
        
        // Remove boat from race
        function removeBoatFromRace(boatType, boatId) {
            const raceContainer = document.getElementById(`race-${boatType}`);
            const raceItem = raceContainer.querySelector(`[data-boat-id="${boatId}"]`);
            const availableContainer = document.getElementById(`available-${boatType}`);
            const boatElement = availableContainer.querySelector(`[data-boat-id="${boatId}"]`);
            const participantIdsInput = document.getElementById(`participant-ids-${boatType}`);
            const raceForm = document.getElementById(`race-form-${boatType}`);
            
            // Remove from race
            raceItem.remove();
            
            // Show in available list
            boatElement.style.display = 'block';
            
            // Update selected boats
            const selectedBoats = Array.from(raceContainer.querySelectorAll('.race-item')).map(item => ({
                registration_boat_id: item.dataset.boatId
            }));
            
            participantIdsInput.value = JSON.stringify(selectedBoats);
            
            // Hide form if no boats
            if (selectedBoats.length === 0) {
                raceForm.style.display = 'none';
                raceContainer.querySelector('p').style.display = 'block';
            }
        }
        
        // Remove boat from existing race
        function removeBoatFromExistingRace(raceId) {
            // Get available boats for selection
            fetch(`../manage_race_participants.php?action=get_race_participants&race_id=${raceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.participants.length > 0) {
                        // Create modal for boat selection
                        const modal = document.createElement('div');
                        modal.style.cssText = `
                            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                            background: rgba(0,0,0,0.5); z-index: 1000; display: flex; 
                            align-items: center; justify-content: center;
                        `;
                        
                        const modalContent = document.createElement('div');
                        modalContent.style.cssText = `
                            background: white; padding: 20px; border-radius: 8px; 
                            max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;
                        `;
                        
                        const boatOptions = data.participants.map(p => 
                            `<option value="${p.boat_number}-${p.lane}">${p.team_name} - ${p.participants} (Boot ${p.boat_number}, Bahn ${p.lane})</option>`
                        ).join('');
                        
                        modalContent.innerHTML = `
                            <h3>Boot aus Rennen entfernen</h3>
                            <p>Wählen Sie ein Boot zum Entfernen:</p>
                            <select id="boat-select" style="width: 100%; padding: 10px; margin: 10px 0;">
                                ${boatOptions}
                            </select>
                            <div style="text-align: right; margin-top: 20px;">
                                <button onclick="this.closest('.modal').remove()" style="margin-right: 10px;">Abbrechen</button>
                                <button onclick="confirmRemoveBoat(${raceId})" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px;">Entfernen</button>
                            </div>
                        `;
                        
                        modal.className = 'modal';
                        modal.appendChild(modalContent);
                        document.body.appendChild(modal);
                    } else {
                        alert('Keine Boote in diesem Rennen gefunden');
                    }
                });
        }
        
        // Confirm boat removal
        function confirmRemoveBoat(raceId) {
            const boatInfo = document.getElementById('boat-select').value;
            const [boatNumber, lane] = boatInfo.split('-');
            
            const formData = new FormData();
            formData.append('action', 'remove_boat_from_race');
            formData.append('race_id', raceId);
            formData.append('boat_number', boatNumber);
            formData.append('lane', lane);
            
            fetch('../manage_race_participants.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    location.reload(); // Refresh to update the display
                } else {
                    alert('Fehler: ' + result.message);
                }
                // Remove modal
                document.querySelector('.modal').remove();
            });
        }
        
        // Add boat to existing race
        function addBoatToExistingRace(raceId) {
            const eventId = document.getElementById('event-select').value;
            if (!eventId) {
                alert('Bitte wählen Sie zuerst eine Regatta aus');
                return;
            }
            
            // Get the boat type from the race name or context
            const raceElement = document.querySelector(`[data-race-id="${raceId}"]`);
            const boatType = raceElement ? raceElement.closest('.boat-type-section').id.replace('section-', '') : '';
            
            if (!boatType) {
                alert('Boottyp konnte nicht ermittelt werden');
                return;
            }
            
            // Get available boats
            fetch(`../manage_race_participants.php?action=get_available_boats&event_id=${eventId}&boat_type=${boatType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.boats.length > 0) {
                        // Create modal for boat selection
                        const modal = document.createElement('div');
                        modal.style.cssText = `
                            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                            background: rgba(0,0,0,0.5); z-index: 1000; display: flex; 
                            align-items: center; justify-content: center;
                        `;
                        
                        const modalContent = document.createElement('div');
                        modalContent.style.cssText = `
                            background: white; padding: 20px; border-radius: 8px; 
                            max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;
                        `;
                        
                        const boatOptions = data.boats.map(boat => 
                            `<option value="${boat.id}">${boat.club_name} - ${boat.boat_name}</option>`
                        ).join('');
                        
                        modalContent.innerHTML = `
                            <h3>Boot zum Rennen hinzufügen</h3>
                            <p>Wählen Sie ein verfügbares Boot:</p>
                            <select id="boat-add-select" style="width: 100%; padding: 10px; margin: 10px 0;">
                                ${boatOptions}
                            </select>
                            <div style="text-align: right; margin-top: 20px;">
                                <button onclick="this.closest('.modal').remove()" style="margin-right: 10px;">Abbrechen</button>
                                <button onclick="confirmAddBoat(${raceId})" style="background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px;">Hinzufügen</button>
                            </div>
                        `;
                        
                        modal.className = 'modal';
                        modal.appendChild(modalContent);
                        document.body.appendChild(modal);
                    } else {
                        alert('Keine verfügbaren Boote für diesen Boottyp');
                    }
                });
        }
        
        // Confirm boat addition
        function confirmAddBoat(raceId) {
            const boatId = document.getElementById('boat-add-select').value;
            
            const formData = new FormData();
            formData.append('action', 'add_boat_to_race');
            formData.append('race_id', raceId);
            formData.append('registration_boat_id', boatId);
            
            fetch('../manage_race_participants.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    location.reload(); // Refresh to update the display
                } else {
                    alert('Fehler: ' + result.message);
                }
                // Remove modal
                document.querySelector('.modal').remove();
            });
        }
        
        // Load existing race participants
        function loadExistingRaceParticipants(raceId) {
            fetch(`../manage_race_participants.php?action=get_race_participants&race_id=${raceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById(`race-${raceId}-participants`);
                        if (container) {
                            const participantsHtml = data.participants.map(p => 
                                `<div class="participant-item">
                                    <strong>${p.team_name}</strong>
                                    <br><small>${p.crew_with_clubs || p.participants}</small>
                                    <br><small>Boot ${p.boat_number}, Bahn ${p.lane}</small>
                                </div>`
                            ).join('');
                            container.innerHTML = participantsHtml;
                        }
                    }
                });
        }
        
        // Clear race (remove all boats and reset form)
        function clearRace(boatType) {
            const raceContainer = document.getElementById(`race-${boatType}`);
            const availableContainer = document.getElementById(`available-${boatType}`);
            const raceForm = document.getElementById(`race-form-${boatType}`);
            const participantIdsInput = document.getElementById(`participant-ids-${boatType}`);
            
            // Remove all race items
            const raceItems = raceContainer.querySelectorAll('.race-item');
            raceItems.forEach(item => {
                const boatId = item.dataset.boatId;
                const boatElement = availableContainer.querySelector(`[data-boat-id="${boatId}"]`);
                if (boatElement) {
                    boatElement.style.display = 'block';
                }
                item.remove();
            });
            
            // Reset form
            raceForm.style.display = 'none';
            raceContainer.querySelector('p').style.display = 'block';
            participantIdsInput.value = '[]';
            
            // Clear form inputs
            const form = raceForm.querySelector('form');
            form.reset();
        }
    </script>
</body>
</html> 