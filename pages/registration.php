<?php
// Disable error display in production
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config/database.php';

$conn = getDbConnection();

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Input validation and sanitization functions
function validateAndSanitizeInput($input, $type = 'string', $maxLength = 255) {
    if (empty($input)) {
        return null;
    }
    
    $input = trim($input);
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : null;
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) ? (int)$input : null;
        case 'phone':
            // Basic phone validation - allow digits, spaces, +, -, (, )
            return preg_match('/^[\d\s\+\-\(\)]+$/', $input) ? substr($input, 0, $maxLength) : null;
        case 'name':
            // Allow letters, spaces, hyphens, apostrophes, dots, and numbers for club names
            return preg_match('/^[a-zA-ZäöüßÄÖÜ\s\-\'\.\d]+$/', $input) ? substr($input, 0, $maxLength) : null;
        case 'year':
            $year = filter_var($input, FILTER_VALIDATE_INT);
            return ($year >= 1900 && $year <= date('Y')) ? $year : null;
        case 'boat_type':
            $allowed_types = ['1x', '2x', '2x+', '3x', '3x+', '4x', '4x+', '8-'];
            return in_array($input, $allowed_types) ? $input : null;
        case 'experience_level':
            $allowed_levels = ['beginner', 'intermediate', 'advanced'];
            return in_array($input, $allowed_levels) ? $input : null;
        case 'desired_races':
            $races = filter_var($input, FILTER_VALIDATE_INT);
            return ($races >= 1 && $races <= 10) ? $races : null;
        default:
            return substr($input, 0, $maxLength);
    }
}

// Handle form submissions with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = "Sicherheitsfehler: Ungültiger Token.";
    } else {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'register_boat':
                    // Validate and sanitize all inputs
                    $event_id = validateAndSanitizeInput($_POST['event_id'] ?? '', 'int');
                    $boat_type = validateAndSanitizeInput($_POST['boat_type'] ?? '', 'boat_type');
                    $melder_name = validateAndSanitizeInput($_POST['melder_name'] ?? '', 'name', 100);
                    $contact_email = validateAndSanitizeInput($_POST['contact_email'] ?? '', 'email');
                    $contact_phone = validateAndSanitizeInput($_POST['contact_phone'] ?? '', 'phone', 20);
                    
                    // Validate required fields
                    if (!$event_id || !$boat_type || !$melder_name) {
                        $error_message = "Bitte füllen Sie alle Pflichtfelder korrekt aus.";
                        break;
                    }
                    
                    // Validate event exists and is active
                    try {
                        $stmt = $conn->prepare("SELECT id FROM registration_events WHERE id = ? AND is_active = 1");
                        $stmt->execute([$event_id]);
                        if (!$stmt->fetch()) {
                            $error_message = "Ungültige Veranstaltung ausgewählt.";
                            break;
                        }
                    } catch (PDOException $e) {
                        error_log("Database error in event validation: " . $e->getMessage());
                        $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                        break;
                    }
                    
                    // Handle crew members - REQUIRED based on boat type
                    $crew_members = [];
                    $required_crew_count = getRequiredCrewCount($boat_type);
                    
                    if (isset($_POST['crew_first_names']) && is_array($_POST['crew_first_names'])) {
                        $valid_crew_count = 0;
                        foreach ($_POST['crew_first_names'] as $index => $first_name) {
                            $first_name = validateAndSanitizeInput($first_name, 'name', 50);
                            $last_name = validateAndSanitizeInput($_POST['crew_last_names'][$index] ?? '', 'name', 50);
                            $birth_year = validateAndSanitizeInput($_POST['crew_birth_years'][$index] ?? '', 'year');
                            $club = validateAndSanitizeInput($_POST['crew_clubs'][$index] ?? '', 'name', 100);
                            
                            if ($first_name && $last_name && $birth_year && $club) {
                                $crew_members[] = [
                                    'first_name' => $first_name,
                                    'last_name' => $last_name,
                                    'birth_year' => $birth_year,
                                    'club' => $club
                                ];
                                $valid_crew_count++;
                            }
                        }
                        
                        // Check if we have enough crew members
                        if ($valid_crew_count < $required_crew_count) {
                            $error_message = "Für ein $boat_type Boot benötigen Sie mindestens $required_crew_count Crew-Mitglieder (Sie haben $valid_crew_count angegeben).";
                            break;
                        }
                    } else {
                        $error_message = "Für ein $boat_type Boot benötigen Sie mindestens $required_crew_count Crew-Mitglieder.";
                        break;
                    }
                    
                    if (!isset($error_message)) {
                        try {
                            // Generate boat name from first crew member's club and boat type
                            $first_crew_club = $crew_members[0]['club'] ?? 'Unbekannt';
                            $boat_name = $first_crew_club . ' ' . $boat_type;
                            
                            // Use first crew member's club as primary club
                            $club_name = $first_crew_club;
                            
                            $stmt = $conn->prepare("INSERT INTO registration_boats (event_id, boat_name, boat_type, club_name, captain_name, captain_birth_year, crew_members, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$event_id, $boat_name, $boat_type, $club_name, $melder_name, null, json_encode($crew_members), $contact_email, $contact_phone]);
                            
                            $success_message = "Bootmeldung erfolgreich eingereicht!";
                        } catch (PDOException $e) {
                            error_log("Database error in boat registration: " . $e->getMessage());
                            $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                        }
                    }
                    break;
                    
                case 'register_single':
                    // Validate and sanitize all inputs
                    $event_id = validateAndSanitizeInput($_POST['event_id'] ?? '', 'int');
                    $first_name = validateAndSanitizeInput($_POST['first_name'] ?? '', 'name', 50);
                    $last_name = validateAndSanitizeInput($_POST['last_name'] ?? '', 'name', 50);
                    $birth_year = validateAndSanitizeInput($_POST['birth_year'] ?? '', 'year');
                    $club_name = validateAndSanitizeInput($_POST['club_name'] ?? '', 'name', 100);
                    $experience_level = validateAndSanitizeInput($_POST['experience_level'] ?? '', 'experience_level');
                    $desired_races = validateAndSanitizeInput($_POST['desired_races'] ?? 1, 'desired_races');
                    $contact_email = validateAndSanitizeInput($_POST['contact_email'] ?? '', 'email');
                    $contact_phone = validateAndSanitizeInput($_POST['contact_phone'] ?? '', 'phone', 20);
                    $additional_info = validateAndSanitizeInput($_POST['additional_info'] ?? '', 'string', 1000);
                    
                    // Validate required fields
                    if (!$event_id || !$first_name || !$last_name || !$birth_year || !$club_name || !$experience_level || !$desired_races) {
                        $error_message = "Bitte füllen Sie alle Pflichtfelder korrekt aus.";
                        break;
                    }
                    
                    // Validate event exists and is active
                    try {
                        $stmt = $conn->prepare("SELECT id FROM registration_events WHERE id = ? AND is_active = 1");
                        $stmt->execute([$event_id]);
                        if (!$stmt->fetch()) {
                            $error_message = "Ungültige Veranstaltung ausgewählt.";
                            break;
                        }
                        
                        // Check if single registrations are enabled for this event
                        if (!isSingleRegistrationEnabled($event_id, $events)) {
                            $error_message = "Einzelanmeldungen sind für dieses Event nicht aktiviert.";
                            break;
                        }
                    } catch (PDOException $e) {
                        error_log("Database error in event validation: " . $e->getMessage());
                        $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                        break;
                    }
                    
                    // Handle preferred boat types
                    $preferred_types = [];
                    if (isset($_POST['preferred_types']) && is_array($_POST['preferred_types'])) {
                        foreach ($_POST['preferred_types'] as $type) {
                            $valid_type = validateAndSanitizeInput($type, 'boat_type');
                            if ($valid_type) {
                                $preferred_types[] = $valid_type;
                            }
                        }
                    }
                    
                    try {
                        $full_name = $first_name . ' ' . $last_name;
                        $stmt = $conn->prepare("INSERT INTO registration_singles (event_id, name, birth_year, club_name, preferred_boat_types, desired_races, experience_level, contact_email, contact_phone, additional_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$event_id, $full_name, $birth_year, $club_name, json_encode($preferred_types), $desired_races, $experience_level, $contact_email, $contact_phone, $additional_info]);
                        
                        $success_message = "Einzelmeldung erfolgreich eingereicht!";
                    } catch (PDOException $e) {
                        error_log("Database error in single registration: " . $e->getMessage());
                        $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                    }
                    break;
            }
        }
    }
}

// Get active events with error handling
try {
    $stmt = $conn->prepare("SELECT * FROM registration_events WHERE is_active = 1 ORDER BY event_date ASC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode allowed_boat_types for each event
    foreach ($events as &$event) {
        if ($event['allowed_boat_types']) {
            $event['allowed_boat_types'] = json_decode($event['allowed_boat_types'], true);
        }
    }
} catch (PDOException $e) {
    error_log("Database error fetching events: " . $e->getMessage());
    $events = [];
}

// Get all teams/clubs for dropdown with error handling
try {
    $stmt = $conn->prepare("SELECT * FROM teams ORDER BY name ASC");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching teams: " . $e->getMessage());
    $teams = [];
}

// Helper function to get required crew count for boat type
function getRequiredCrewCount($boat_type) {
    switch ($boat_type) {
        case '1x': return 1; // 1 crew member (no melder in boat)
        case '2x': return 2; // 2 crew members
        case '2x+': return 3; // 3 crew members
        case '3x': return 3; // 3 crew members
        case '3x+': return 4; // 4 crew members
        case '4x': return 4; // 4 crew members
        case '4x+': return 5; // 5 crew members (4 Ruderer + 1 Steuermann)
        case '8-': return 8; // 8 crew members
        default: return 1;
    }
}

// Helper function to check if single registrations are enabled for an event
function isSingleRegistrationEnabled($event_id, $events) {
    foreach ($events as $event) {
        if ($event['id'] == $event_id) {
            return $event['singles_enabled'] == 1;
        }
    }
    return true; // Default: enabled
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meldeportal - Regatta System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .registration-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .registration-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
        }
        
        .tab-button {
            padding: 15px 30px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button.active {
            border-bottom-color: #3498db;
            color: #3498db;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .registration-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .crew-member {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .crew-member h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .crew-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
        }
        
        .add-crew-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .remove-crew-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .submit-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .submit-btn:hover {
            background: #2980b9;
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
        
        .events-list {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .event-card {
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        
        .event-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .event-date {
            color: #666;
            font-weight: bold;
        }
        
        .preferred-types {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .type-checkbox {
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <header>
            <h1>Meldeportal</h1>
            <p>Melden Sie sich für die anstehenden Regatten an</p>
        </header>

        <?php if (isset($success_message)): ?>
        <div class="success-message">
            <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="error-message">
            <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <!-- Available Events -->
        <div class="events-list">
            <h2>Verfügbare Regatten</h2>
            <?php if (empty($events)): ?>
                <p>Derzeit sind keine Regatten zur Anmeldung verfügbar.</p>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                <div class="event-card">
                    <h3><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="event-date">Datum: <?= htmlspecialchars(date('d.m.Y', strtotime($event['event_date'])), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($event['description']): ?>
                        <p><?= htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($events)): ?>
        <!-- Registration Tabs -->
        <div class="registration-tabs">
            <button class="tab-button active" onclick="showTab('boat')">Bootmeldung</button>
            <button class="tab-button" onclick="showTab('single')">Einzelmeldung</button>
        </div>

        <!-- Boat Registration -->
        <div id="boat-tab" class="tab-content active">
            <div class="registration-form">
                <h2>Bootmeldung</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="register_boat">
                    
                    <div class="form-group">
                        <label for="event_id">Regatta auswählen:</label>
                        <select name="event_id" id="event_id" required>
                            <option value="">Bitte wählen Sie eine Regatta</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?= htmlspecialchars($event['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(date('d.m.Y', strtotime($event['event_date'])), ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="boat_type">Boottyp:</label>
                            <select name="boat_type" id="boat_type" required>
                                <option value="">Bitte wählen</option>
                                <!-- Options will be populated dynamically based on selected event -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="melder_name">Melder Name:</label>
                            <input type="text" name="melder_name" id="melder_name" required maxlength="100" pattern="[a-zA-ZäöüßÄÖÜ\s\-\'\.\d]+">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_email">Email (optional):</label>
                            <input type="email" name="contact_email" id="contact_email" maxlength="255">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_phone">Telefon (optional):</label>
                            <input type="tel" name="contact_phone" id="contact_phone" maxlength="20" pattern="[\d\s\+\-\(\)]+">
                        </div>
                    </div>

                    <!-- Crew Members -->
                    <div class="form-group full-width">
                        <h3>Crew-Mitglieder <span id="crew-requirement" style="color: #666; font-size: 14px;"></span></h3>
                        <div id="crew-members">
                            <!-- Crew members will be added here dynamically -->
                        </div>
                        <button type="button" class="add-crew-btn" onclick="addCrewMember()" id="add-crew-btn" style="display: none;">Crew-Mitglied hinzufügen</button>
                    </div>

                    <div class="form-group full-width">
                        <button type="submit" class="submit-btn">Bootmeldung einreichen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Single Registration -->
        <div id="single-tab" class="tab-content">
            <div class="registration-form">
                <h2>Einzelmeldung</h2>
                <p>Sie möchten gerne mitmachen, haben aber noch kein vollständiges Boot? Melden Sie sich hier an und wir versuchen, Sie mit anderen Teilnehmern zusammenzubringen.</p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="register_single">
                    
                    <div class="form-group">
                        <label for="single_event_id">Rennen auswählen:</label>
                        <select name="event_id" id="single_event_id" required>
                            <option value="">Bitte wählen Sie ein Rennen</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?= htmlspecialchars($event['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(date('d.m.Y', strtotime($event['event_date'])), ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">Vorname:</label>
                            <input type="text" name="first_name" id="first_name" required maxlength="50" pattern="[a-zA-ZäöüßÄÖÜ\s\-\'\.\d]+">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Nachname:</label>
                            <input type="text" name="last_name" id="last_name" required maxlength="50" pattern="[a-zA-ZäöüßÄÖÜ\s\-\'\.\d]+">
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_year">Geburtsjahr:</label>
                            <input type="number" name="birth_year" id="birth_year" min="1900" max="<?= date('Y') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="club_name">Verein/Club:</label>
                            <select name="club_name" id="club_name" required>
                                <option value="">Bitte wählen Sie Ihren Verein</option>
                                <?php foreach ($teams as $team): ?>
                                <option value="<?= htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="experience_level">Erfahrungslevel:</label>
                            <select name="experience_level" id="experience_level" required>
                                <option value="beginner">Anfänger</option>
                                <option value="intermediate" selected>Fortgeschritten</option>
                                <option value="advanced">Erfahren</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="desired_races">Anzahl gewünschter Rennen:</label>
                            <select name="desired_races" id="desired_races" required>
                                <option value="1">1 Rennen</option>
                                <option value="2">2 Rennen</option>
                                <option value="3">3 Rennen</option>
                                <option value="4">4 Rennen</option>
                                <option value="5">5 Rennen</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_email">Email (optional):</label>
                            <input type="email" name="contact_email" id="contact_email" maxlength="255">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_phone">Telefon (optional):</label>
                            <input type="tel" name="contact_phone" id="contact_phone" maxlength="20" pattern="[\d\s\+\-\(\)]+">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Bevorzugte Boottypen:</label>
                        <div class="preferred-types" id="preferred-types-container">
                            <!-- Checkboxes will be populated dynamically based on selected event -->
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="additional_info">Zusätzliche Informationen (optional):</label>
                        <textarea name="additional_info" id="additional_info" rows="4" placeholder="Erzählen Sie uns etwas über sich, Ihre Erfahrung oder spezielle Wünsche..." maxlength="1000"></textarea>
                    </div>

                    <div class="form-group full-width">
                        <button type="submit" class="submit-btn">Einzelmeldung einreichen</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let crewMemberCount = 0;

        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        function updateCrewRequirements() {
            const boatType = document.getElementById('boat_type').value;
            const crewRequirement = document.getElementById('crew-requirement');
            const addCrewBtn = document.getElementById('add-crew-btn');
            const crewMembers = document.getElementById('crew-members');
            
            // Clear existing crew members
            crewMembers.innerHTML = '';
            crewMemberCount = 0;
            
            let requiredCount = 0;
            let requirementText = '';
            
            switch (boatType) {
                case '1x':
                    requiredCount = 1;
                    requirementText = '(1 Crew-Mitglied erforderlich)';
                    break;
                case '2x':
                    requiredCount = 2;
                    requirementText = '(2 Crew-Mitglieder erforderlich)';
                    break;
                case '2x+':
                    requiredCount = 3;
                    requirementText = '(3 Crew-Mitglieder erforderlich)';
                    break;
                case '3x':
                    requiredCount = 3;
                    requirementText = '(3 Crew-Mitglieder erforderlich)';
                    break;
                case '3x+':
                    requiredCount = 4;
                    requirementText = '(4 Crew-Mitglieder erforderlich)';
                    break;
                case '4x':
                    requiredCount = 4;
                    requirementText = '(4 Crew-Mitglieder erforderlich)';
                    break;
                case '4x+':
                    requiredCount = 5;
                    requirementText = '(5 Crew-Mitglieder erforderlich)';
                    break;
                case '8-':
                    requiredCount = 8;
                    requirementText = '(8 Crew-Mitglieder erforderlich)';
                    break;
                default:
                    requiredCount = 1;
                    requirementText = '';
            }
            
            crewRequirement.textContent = requirementText;
            
            if (requiredCount > 0) {
                addCrewBtn.style.display = 'inline-block';
                // Automatically add required crew members
                for (let i = 0; i < requiredCount; i++) {
                    addCrewMember();
                }
            } else {
                addCrewBtn.style.display = 'none';
            }
        }

        function addCrewMember() {
            crewMemberCount++;
            const crewContainer = document.getElementById('crew-members');
            
            const crewMember = document.createElement('div');
            crewMember.className = 'crew-member';
            crewMember.innerHTML = `
                <h4>Crew-Mitglied ${crewMemberCount}</h4>
                <div class="crew-grid">
                    <div class="form-group">
                        <label>Vorname: <span style="color: red;">*</span></label>
                        <input type="text" name="crew_first_names[]" required maxlength="50" pattern="[a-zA-ZäöüßÄÖÜ\s\-\'\.\d]+">
                    </div>
                    <div class="form-group">
                        <label>Nachname: <span style="color: red;">*</span></label>
                        <input type="text" name="crew_last_names[]" required maxlength="50" pattern="[a-zA-ZäöüßÄÖÜ\s\-\'\.\d]+">
                    </div>
                    <div class="form-group">
                        <label>Geburtsjahr: <span style="color: red;">*</span></label>
                        <input type="number" name="crew_birth_years[]" min="1900" max="<?= date('Y') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Verein: <span style="color: red;">*</span></label>
                        <select name="crew_clubs[]" required>
                            <option value="">Bitte wählen Sie einen Verein</option>
                            <?php foreach ($teams as $team): ?>
                            <option value="<?= htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="button" class="remove-crew-btn" onclick="removeCrewMember(this)">Entfernen</button>
            `;
            
            crewContainer.appendChild(crewMember);
        }

        function removeCrewMember(button) {
            button.parentElement.remove();
        }

        // Add event listener to boat type select
        // Event data for dynamic boat type loading
        const events = <?= json_encode($events) ?>;
        
        function updateBoatTypes() {
            const eventSelect = document.getElementById('event_id');
            const boatTypeSelect = document.getElementById('boat_type');
            const preferredTypesContainer = document.getElementById('preferred-types-container');
            const singleTabButton = document.querySelector('button[onclick="showTab(\'single\')"]');
            const singleTabContent = document.getElementById('single-tab');
            
            if (!eventSelect || !boatTypeSelect) return;
            
            const selectedEventId = eventSelect.value;
            
            // Clear existing options
            boatTypeSelect.innerHTML = '<option value="">Bitte wählen</option>';
            preferredTypesContainer.innerHTML = '';
            
            if (!selectedEventId) {
                // Hide single tab if no event selected
                if (singleTabButton) singleTabButton.style.display = 'none';
                if (singleTabContent) singleTabContent.style.display = 'none';
                return;
            }
            
            // Find the selected event
            const selectedEvent = events.find(event => event.id == selectedEventId);
            if (!selectedEvent) return;
            
            // Show/hide single tab based on singles_enabled status
            const singlesEnabled = selectedEvent.singles_enabled === 1 || selectedEvent.singles_enabled === true;
            if (singleTabButton) {
                singleTabButton.style.display = singlesEnabled ? 'inline-block' : 'none';
            }
            if (singleTabContent) {
                singleTabContent.style.display = singlesEnabled ? 'block' : 'none';
            }
            
            // If single tab is hidden and currently active, switch to boat tab
            if (!singlesEnabled && singleTabContent && singleTabContent.classList.contains('active')) {
                showTab('boat');
            }
            
            // Get allowed boat types for this event
            let allowedTypes = ['1x', '2x', '2x+', '3x', '3x+', '4x', '4x+', '8-'];
            if (selectedEvent.allowed_boat_types !== null && selectedEvent.allowed_boat_types !== undefined && 
                Array.isArray(selectedEvent.allowed_boat_types) && selectedEvent.allowed_boat_types.length > 0) {
                allowedTypes = selectedEvent.allowed_boat_types;
            }
            
            // Add options to boat type select
            const boatTypeLabels = {
                '1x': '1x (Einer)',
                '2x': '2x (Zweier)',
                '2x+': '2x+ (Zweier mit Steuermann)',
                '3x': '3x (Dreier)',
                '3x+': '3x+ (Dreier mit Steuermann)',
                '4x': '4x (Vierer)',
                '4x+': '4x+ (Vierer mit Steuermann)',
                '8-': '8- (Achter)'
            };
            
            allowedTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = boatTypeLabels[type] || type;
                boatTypeSelect.appendChild(option);
            });
            
            // Add checkboxes for preferred types
            allowedTypes.forEach(type => {
                const label = document.createElement('label');
                label.className = 'type-checkbox';
                label.innerHTML = `<input type="checkbox" name="preferred_types[]" value="${type}"> ${boatTypeLabels[type] || type}`;
                preferredTypesContainer.appendChild(label);
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const eventSelect = document.getElementById('event_id');
            const boatTypeSelect = document.getElementById('boat_type');
            
            if (eventSelect) {
                eventSelect.addEventListener('change', updateBoatTypes);
                // Initialize boat types on page load
                updateBoatTypes();
            }
            
            if (boatTypeSelect) {
                boatTypeSelect.addEventListener('change', updateCrewRequirements);
            }
        });
    </script>
</body>
</html> 