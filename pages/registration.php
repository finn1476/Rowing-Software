<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

$conn = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register_boat':
                $event_id = $_POST['event_id'];
                $boat_type = $_POST['boat_type'];
                $team_id = $_POST['team_id'];
                $melder_name = $_POST['melder_name'];
                $contact_email = $_POST['contact_email'] ?? '';
                $contact_phone = $_POST['contact_phone'] ?? '';
                
                // Handle crew members - REQUIRED based on boat type
                $crew_members = [];
                $required_crew_count = getRequiredCrewCount($boat_type);
                
                if (isset($_POST['crew_first_names']) && is_array($_POST['crew_first_names'])) {
                    $valid_crew_count = 0;
                    foreach ($_POST['crew_first_names'] as $index => $first_name) {
                        if (!empty($first_name) && !empty($_POST['crew_last_names'][$index]) && !empty($_POST['crew_birth_years'][$index])) {
                            $crew_members[] = [
                                'first_name' => $first_name,
                                'last_name' => $_POST['crew_last_names'][$index],
                                'birth_year' => $_POST['crew_birth_years'][$index],
                                'club' => $_POST['crew_clubs'][$index] ?? 'Unbekannt'
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
                    } catch (Exception $e) {
                        $error_message = "Datenbankfehler: " . $e->getMessage();
                    }
                }
                break;
                
            case 'register_single':
                $event_id = $_POST['event_id'];
                $first_name = $_POST['first_name'];
                $last_name = $_POST['last_name'];
                $birth_year = $_POST['birth_year'];
                $experience_level = $_POST['experience_level'];
                $desired_races = $_POST['desired_races'] ?? 1;
                $contact_email = $_POST['contact_email'] ?? '';
                $contact_phone = $_POST['contact_phone'] ?? '';
                $additional_info = $_POST['additional_info'] ?? '';
                
                // Handle preferred boat types
                $preferred_types = [];
                if (isset($_POST['preferred_types']) && is_array($_POST['preferred_types'])) {
                    $preferred_types = $_POST['preferred_types'];
                }
                
                $full_name = $first_name . ' ' . $last_name;
                $stmt = $conn->prepare("INSERT INTO registration_singles (event_id, name, birth_year, preferred_boat_types, desired_races, experience_level, contact_email, contact_phone, additional_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$event_id, $full_name, $birth_year, json_encode($preferred_types), $desired_races, $experience_level, $contact_email, $contact_phone, $additional_info]);
                
                $success_message = "Einzelmeldung erfolgreich eingereicht!";
                break;
        }
    }
}

// Get active events
$stmt = $conn->query("SELECT * FROM registration_events WHERE is_active = 1 ORDER BY event_date ASC");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all teams/clubs for dropdown
$stmt = $conn->query("SELECT * FROM teams ORDER BY name ASC");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to get required crew count for boat type
function getRequiredCrewCount($boat_type) {
    switch ($boat_type) {
        case '1x': return 1; // 1 crew member (no melder in boat)
        case '2x': return 2; // 2 crew members
        case '3x+': return 3; // 3 crew members
        case '4x': return 4; // 4 crew members
        default: return 1;
    }
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
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <?= htmlspecialchars($error_message) ?>
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
                    <h3><?= htmlspecialchars($event['name']) ?></h3>
                    <p class="event-date">Datum: <?= date('d.m.Y', strtotime($event['event_date'])) ?></p>
                    <?php if ($event['description']): ?>
                        <p><?= htmlspecialchars($event['description']) ?></p>
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
                    <input type="hidden" name="action" value="register_boat">
                    
                    <div class="form-group">
                        <label for="event_id">Regatta auswählen:</label>
                        <select name="event_id" id="event_id" required>
                            <option value="">Bitte wählen Sie eine Regatta</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?= $event['id'] ?>">
                                <?= htmlspecialchars($event['name']) ?> (<?= date('d.m.Y', strtotime($event['event_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="boat_type">Boottyp:</label>
                            <select name="boat_type" id="boat_type" required>
                                <option value="">Bitte wählen</option>
                                <option value="1x">1x (Einer)</option>
                                <option value="2x">2x (Zweier)</option>
                                <option value="3x+">3x+ (Dreier und mehr)</option>
                                <option value="4x">4x (Vierer)</option>
                            </select>
                        </div>
                        
                        <!-- Club selection moved to individual crew members -->
                        
                        <div class="form-group">
                            <label for="melder_name">Melder Name:</label>
                            <input type="text" name="melder_name" id="melder_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_email">Email (optional):</label>
                            <input type="email" name="contact_email" id="contact_email">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_phone">Telefon (optional):</label>
                            <input type="tel" name="contact_phone" id="contact_phone">
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
                    <input type="hidden" name="action" value="register_single">
                    
                    <div class="form-group">
                        <label for="single_event_id">Rennen auswählen:</label>
                        <select name="event_id" id="single_event_id" required>
                            <option value="">Bitte wählen Sie ein Rennen</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?= $event['id'] ?>">
                                <?= htmlspecialchars($event['name']) ?> (<?= date('d.m.Y', strtotime($event['event_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">Vorname:</label>
                            <input type="text" name="first_name" id="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Nachname:</label>
                            <input type="text" name="last_name" id="last_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_year">Geburtsjahr:</label>
                            <input type="number" name="birth_year" id="birth_year" min="1900" max="2010" required>
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
                            <input type="email" name="contact_email" id="contact_email">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_phone">Telefon (optional):</label>
                            <input type="tel" name="contact_phone" id="contact_phone">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Bevorzugte Boottypen:</label>
                        <div class="preferred-types">
                            <label class="type-checkbox">
                                <input type="checkbox" name="preferred_types[]" value="1x"> 1x (Einer)
                            </label>
                            <label class="type-checkbox">
                                <input type="checkbox" name="preferred_types[]" value="2x"> 2x (Zweier)
                            </label>
                            <label class="type-checkbox">
                                <input type="checkbox" name="preferred_types[]" value="3x+"> 3x+ (Dreier und mehr)
                            </label>
                            <label class="type-checkbox">
                                <input type="checkbox" name="preferred_types[]" value="4x"> 4x (Vierer)
                            </label>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="additional_info">Zusätzliche Informationen (optional):</label>
                        <textarea name="additional_info" id="additional_info" rows="4" placeholder="Erzählen Sie uns etwas über sich, Ihre Erfahrung oder spezielle Wünsche..."></textarea>
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
                case '3x+':
                    requiredCount = 3;
                    requirementText = '(3 Crew-Mitglieder erforderlich)';
                    break;
                case '4x':
                    requiredCount = 4;
                    requirementText = '(4 Crew-Mitglieder erforderlich)';
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
                        <input type="text" name="crew_first_names[]" required>
                    </div>
                    <div class="form-group">
                        <label>Nachname: <span style="color: red;">*</span></label>
                        <input type="text" name="crew_last_names[]" required>
                    </div>
                    <div class="form-group">
                        <label>Geburtsjahr: <span style="color: red;">*</span></label>
                        <input type="number" name="crew_birth_years[]" min="1900" max="2010" required>
                    </div>
                    <div class="form-group">
                        <label>Verein: <span style="color: red;">*</span></label>
                        <select name="crew_clubs[]" required>
                            <option value="">Bitte wählen Sie einen Verein</option>
                            <?php foreach ($teams as $team): ?>
                            <option value="<?= htmlspecialchars($team['name']) ?>"><?= htmlspecialchars($team['name']) ?></option>
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
        document.addEventListener('DOMContentLoaded', function() {
            const boatTypeSelect = document.getElementById('boat_type');
            if (boatTypeSelect) {
                boatTypeSelect.addEventListener('change', updateCrewRequirements);
            }
        });
    </script>
</body>
</html> 