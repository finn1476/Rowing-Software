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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_event':
                $event_id = $_POST['event_id'];
                $is_active = $_POST['is_active'];
                
                $stmt = $conn->prepare("UPDATE registration_events SET is_active = ? WHERE id = ?");
                $stmt->execute([$is_active, $event_id]);
                $message = "Event-Status erfolgreich aktualisiert!";
                $messageType = "success";
                break;
                
            case 'activate_main_event':
                $main_event_id = $_POST['main_event_id'];
                
                // Check if this event is already in registration_events
                $stmt = $conn->prepare("SELECT id FROM registration_events WHERE main_event_id = ?");
                $stmt->execute([$main_event_id]);
                $existing = $stmt->fetch();
                
                if (!$existing) {
                    // Get main event details
                    $stmt = $conn->prepare("SELECT e.*, y.name as year_name FROM events e 
                                          LEFT JOIN years y ON e.year_id = y.id 
                                          WHERE e.id = ?");
                    $stmt->execute([$main_event_id]);
                    $main_event = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($main_event) {
                        // Add to registration_events with a clear indication it's for registration
                        $registration_name = "Anmeldung: " . $main_event['name'];
                        $registration_description = "Anmeldung für das Event: " . $main_event['name'] . 
                                                   ($main_event['description'] ? "\n\n" . $main_event['description'] : "");
                        
                        $stmt = $conn->prepare("INSERT INTO registration_events (name, event_date, description, main_event_id) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$registration_name, $main_event['event_date'], $registration_description, $main_event_id]);
                        $message = "Event erfolgreich für Anmeldungen aktiviert!";
                        $messageType = "success";
                    } else {
                        $message = "Event nicht gefunden!";
                        $messageType = "error";
                    }
                } else {
                    $message = "Event ist bereits für Anmeldungen aktiviert!";
                    $messageType = "warning";
                }
                break;
                
            case 'update_boat_status':
                $boat_id = $_POST['boat_id'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE registration_boats SET status = ? WHERE id = ?");
                $stmt->execute([$status, $boat_id]);
                $message = "Boot-Status erfolgreich aktualisiert!";
                $messageType = "success";
                break;
                
            case 'update_single_status':
                $single_id = $_POST['single_id'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE registration_singles SET status = ? WHERE id = ?");
                $stmt->execute([$status, $single_id]);
                $message = "Einzelanmeldung-Status erfolgreich aktualisiert!";
                $messageType = "success";
                break;
        }
    }
}

// Get all registration events
$stmt = $conn->query("SELECT * FROM registration_events ORDER BY event_date DESC");
$registration_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all main events that are not yet in registration_events
$stmt = $conn->query("SELECT e.*, y.name as year_name FROM events e 
                     LEFT JOIN years y ON e.year_id = y.id 
                     WHERE e.id NOT IN (SELECT main_event_id FROM registration_events WHERE main_event_id IS NOT NULL)
                     ORDER BY e.event_date DESC");
$available_main_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$filter_event_id = $_GET['filter_event_id'] ?? '';

// Get all boat registrations with filter
if ($filter_event_id) {
    $stmt = $conn->prepare("SELECT rb.*, re.name as event_name FROM registration_boats rb 
                           LEFT JOIN registration_events re ON rb.event_id = re.id 
                           WHERE rb.event_id = ?
                           ORDER BY rb.created_at DESC");
    $stmt->execute([$filter_event_id]);
} else {
    $stmt = $conn->query("SELECT rb.*, re.name as event_name FROM registration_boats rb 
                         LEFT JOIN registration_events re ON rb.event_id = re.id 
                         ORDER BY rb.created_at DESC");
}
$boat_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all single registrations with filter
if ($filter_event_id) {
    $stmt = $conn->prepare("SELECT rs.*, re.name as event_name FROM registration_singles rs 
                           LEFT JOIN registration_events re ON rs.event_id = re.id 
                           WHERE rs.event_id = ?
                           ORDER BY rs.created_at DESC");
    $stmt->execute([$filter_event_id]);
} else {
    $stmt = $conn->query("SELECT rs.*, re.name as event_name FROM registration_singles rs 
                         LEFT JOIN registration_events re ON rs.event_id = re.id 
                         ORDER BY rs.created_at DESC");
}
$single_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meldeportal Admin - Regatta System</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            border-bottom: 3px solid #667eea;
            font-size: 1.5em;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .data-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-used {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-info {
            background: #d1ecf1;
            color: #0c5460;
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
        
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0;
            font-size: 2em;
            font-weight: 300;
        }
        
        .stat-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1>🏆 Meldeportal Admin</h1>
            <p>Verwalten Sie Events, Anmeldungen und Genehmigungen</p>
            <div class="nav-links">
                <a href="admin.php">← Zurück zum Admin</a>
                <a href="single_creator.php">👥 Single Creator</a>
                <a href="race_creator.php">🚣‍♀️ Race Creator</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </div>

        <?php if (isset($message)): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= count($registration_events) ?></h3>
                <p>Aktive Events</p>
            </div>
            <div class="stat-card">
                <h3><?= count($boat_registrations) ?></h3>
                <p><?= $filter_event_id ? 'Gefilterte Boot-Anmeldungen' : 'Boot-Anmeldungen' ?></p>
            </div>
            <div class="stat-card">
                <h3><?= count($single_registrations) ?></h3>
                <p><?= $filter_event_id ? 'Gefilterte Einzel-Anmeldungen' : 'Einzel-Anmeldungen' ?></p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_filter($boat_registrations, function($b) { return $b['status'] === 'approved'; })) ?></h3>
                <p><?= $filter_event_id ? 'Genehmigte Boote (gefiltert)' : 'Genehmigte Boote' ?></p>
            </div>
        </div>

        <!-- Event Management -->
        <div class="section">
            <h2>📅 Event-Verwaltung</h2>
            
            <!-- Activate Main Event -->
            <div style="margin-bottom: 30px;">
                <h3 style="color: #667eea; margin-bottom: 15px;">🔗 Event aus Hauptsystem aktivieren</h3>
                <form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                    <input type="hidden" name="action" value="activate_main_event">
                    <div class="form-group">
                        <label for="main_event_id">Event auswählen:</label>
                        <select name="main_event_id" id="main_event_id" required>
                            <option value="">Bitte wählen Sie ein Event aus dem Hauptsystem</option>
                            <?php foreach ($available_main_events as $event): ?>
                            <option value="<?= $event['id'] ?>">
                                <?= htmlspecialchars($event['name']) ?> (<?= date('d.m.Y', strtotime($event['event_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">Event für Anmeldungen aktivieren</button>
                </form>
            </div>

            <!-- Event List -->
            <h3 style="color: #667eea; margin-bottom: 15px;">📋 Aktuelle Events</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Datum</th>
                            <th>Typ</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registration_events as $event): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($event['name']) ?></strong></td>
                            <td><?= date('d.m.Y', strtotime($event['event_date'])) ?></td>
                            <td>
                                <?php if ($event['main_event_id']): ?>
                                    <span class="status-badge status-approved">Hauptsystem</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">Manuell</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_event">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $event['is_active'] ? '0' : '1' ?>">
                                    <button type="submit" class="btn <?= $event['is_active'] ? 'btn-warning' : 'btn-success' ?>" style="padding: 6px 12px; font-size: 12px;">
                                        <?= $event['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <?php if ($event['main_event_id']): ?>
                                    <a href="race_creator.php?event_id=<?= $event['id'] ?>" class="btn btn-info" style="padding: 6px 12px; font-size: 12px;">Rennen erstellen</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Boat Registrations -->
        <div class="section">
            <h2>🚣‍♀️ Boot-Anmeldungen</h2>
            
            <!-- Filter -->
            <div style="margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 10px;">
                <form method="GET" style="display: flex; align-items: center; gap: 15px;">
                    <div class="form-group" style="margin: 0; flex: 1;">
                        <label for="filter_event_id" style="margin-bottom: 5px; font-size: 14px;">Nach Event filtern:</label>
                        <select name="filter_event_id" id="filter_event_id" onchange="this.form.submit()" style="width: 100%;">
                            <option value="">Alle Events anzeigen</option>
                            <?php foreach ($registration_events as $event): ?>
                            <option value="<?= $event['id'] ?>" <?= $filter_event_id == $event['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Filtern</button>
                    <?php if ($filter_event_id): ?>
                    <a href="?" class="btn btn-warning" style="margin-top: 20px;">Filter zurücksetzen</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Verein</th>
                            <th>Boot</th>
                            <th>Typ</th>
                            <th>Melder</th>
                            <th>Event</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($boat_registrations as $boat): ?>
                        <tr>
                            <td>
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
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($boat['boat_name']) ?></td>
                            <td><span class="status-badge status-info"><?= $boat['boat_type'] ?></span></td>
                            <td><?= htmlspecialchars($boat['captain_name']) ?></td>
                            <td><?= htmlspecialchars($boat['event_name']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $boat['status'] ?>">
                                    <?= ucfirst($boat['status']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_boat_status">
                                    <input type="hidden" name="boat_id" value="<?= $boat['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #ddd;">
                                        <option value="pending" <?= $boat['status'] === 'pending' ? 'selected' : '' ?>>Ausstehend</option>
                                        <option value="approved" <?= $boat['status'] === 'approved' ? 'selected' : '' ?>>Genehmigt</option>
                                        <option value="rejected" <?= $boat['status'] === 'rejected' ? 'selected' : '' ?>>Abgelehnt</option>
                                        <option value="used" <?= $boat['status'] === 'used' ? 'selected' : '' ?>>Verwendet</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Single Registrations -->
        <div class="section">
            <h2>👤 Einzel-Anmeldungen</h2>
            <div style="margin-bottom: 20px;">
                <a href="single_creator.php" class="btn btn-info" style="text-decoration: none; margin-right: 10px;">
                    👥 Single Creator - Boote aus Einzel-Anmeldungen erstellen
                </a>
                <a href="../create_test_singles.php" class="btn btn-success" style="text-decoration: none;">
                    🧪 Test Einzel-Anmeldungen erstellen
                </a>
            </div>
            
            <!-- Filter -->
            <div style="margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 10px;">
                <form method="GET" style="display: flex; align-items: center; gap: 15px;">
                    <div class="form-group" style="margin: 0; flex: 1;">
                        <label for="filter_event_id_single" style="margin-bottom: 5px; font-size: 14px;">Nach Event filtern:</label>
                        <select name="filter_event_id" id="filter_event_id_single" onchange="this.form.submit()" style="width: 100%;">
                            <option value="">Alle Events anzeigen</option>
                            <?php foreach ($registration_events as $event): ?>
                            <option value="<?= $event['id'] ?>" <?= $filter_event_id == $event['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Filtern</button>
                    <?php if ($filter_event_id): ?>
                    <a href="?" class="btn btn-warning" style="margin-top: 20px;">Filter zurücksetzen</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Geburtsjahr</th>
                            <th>Bevorzugte Boote</th>
                            <th>Rennen</th>
                            <th>Event</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($single_registrations as $single): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($single['name']) ?></strong></td>
                            <td><?= $single['birth_year'] ?></td>
                            <td><?= 
                                is_string($single['preferred_boat_types']) ? 
                                htmlspecialchars($single['preferred_boat_types']) : 
                                htmlspecialchars(implode(', ', json_decode($single['preferred_boat_types'], true) ?: []))
                            ?></td>
                            <td><?= $single['races_completed'] ?>/<?= $single['desired_races'] ?></td>
                            <td><?= htmlspecialchars($single['event_name']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $single['status'] ?>">
                                    <?= ucfirst($single['status']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_single_status">
                                    <input type="hidden" name="single_id" value="<?= $single['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #ddd;">
                                        <option value="pending" <?= $single['status'] === 'pending' ? 'selected' : '' ?>>Ausstehend</option>
                                        <option value="approved" <?= $single['status'] === 'approved' ? 'selected' : '' ?>>Genehmigt</option>
                                        <option value="rejected" <?= $single['status'] === 'rejected' ? 'selected' : '' ?>>Abgelehnt</option>
                                        <option value="used" <?= $single['status'] === 'used' ? 'selected' : '' ?>>Verwendet</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 