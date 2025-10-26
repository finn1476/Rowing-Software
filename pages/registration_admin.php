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
                
            case 'update_event_settings':
                $event_id = $_POST['event_id'];
                $allowed_boat_types = $_POST['allowed_boat_types'] ?? [];
                $singles_enabled = isset($_POST['singles_enabled']) ? 1 : 0;
                
                // Validate boat types
                $valid_types = ['1x', '2x', '2x+', '3x', '3x+', '4x', '4x+', '8-'];
                $filtered_types = array_intersect($allowed_boat_types, $valid_types);
                
                // If no types selected, store NULL (meaning all types are allowed)
                $json_value = empty($filtered_types) ? null : json_encode($filtered_types);
                
                $stmt = $conn->prepare("UPDATE registration_events SET allowed_boat_types = ?, singles_enabled = ? WHERE id = ?");
                $stmt->execute([$json_value, $singles_enabled, $event_id]);
                $message = "Event-Einstellungen erfolgreich aktualisiert!";
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rowing Regatta Management - Registration Admin</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        
        .crew-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e1e5e9;
        }
        
        .crew-member {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0056b3;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="py-3 mb-4 border-bottom">
            <h1 class="text-center">Rowing Regatta Management</h1>
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav">
                            <li class="nav-item">
                                <a class="nav-link" href="../index.php">Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../index.php?page=upcoming_races">Upcoming Races</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../index.php?page=ruderer_search">Ruderer-Suche</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../index.php?page=historical_data">Historical Data</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="registration.php">Meldeportal</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="admin.php">Admin</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="registration_admin.php">Registration Admin</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

        <main>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="mb-0">🏆 Meldeportal Admin</h2>
                            <p class="mb-0 text-muted">Verwalten Sie Events, Anmeldungen und Genehmigungen</p>
                        </div>
                        <div class="card-body">
                            <div class="btn-group" role="group">
                                <a href="admin.php" class="btn btn-outline-primary">← Zurück zum Admin</a>
                                <a href="single_creator.php" class="btn btn-outline-info">👥 Single Creator</a>
                                <a href="race_creator.php" class="btn btn-outline-success">🚣‍♀️ Race Creator</a>
                                <a href="../logout.php" class="btn btn-outline-danger">🚪 Logout</a>
                            </div>
                        </div>
                    </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'warning') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="display-4 text-primary"><?= count($registration_events) ?></h3>
                            <p class="card-text">Aktive Events</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="display-4 text-info"><?= count($boat_registrations) ?></h3>
                            <p class="card-text"><?= $filter_event_id ? 'Gefilterte Boot-Anmeldungen' : 'Boot-Anmeldungen' ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="display-4 text-success"><?= count($single_registrations) ?></h3>
                            <p class="card-text"><?= $filter_event_id ? 'Gefilterte Einzel-Anmeldungen' : 'Einzel-Anmeldungen' ?></p>
                        </div>
                    </div>
            </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="display-4 text-warning"><?= count(array_filter($boat_registrations, function($b) { return $b['status'] === 'approved'; })) ?></h3>
                            <p class="card-text"><?= $filter_event_id ? 'Genehmigte Boote (gefiltert)' : 'Genehmigte Boote' ?></p>
            </div>
            </div>
            </div>
        </div>

        <!-- Event Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="mb-0">📅 Event-Verwaltung</h2>
                </div>
                <div class="card-body">
            <!-- Activate Main Event -->
                    <div class="mb-4">
                        <h4 class="text-primary mb-3">🔗 Event aus Hauptsystem aktivieren</h4>
                        <form method="POST" class="bg-light p-3 rounded">
                    <input type="hidden" name="action" value="activate_main_event">
                            <div class="mb-3">
                                <label for="main_event_id" class="form-label">Event auswählen:</label>
                                <select name="main_event_id" id="main_event_id" class="form-select" required>
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
                    <h4 class="text-primary mb-3">📋 Aktuelle Events</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
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
                                        <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_event">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $event['is_active'] ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-sm <?= $event['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $event['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="openEventSettings(<?= $event['id'] ?>, '<?= htmlspecialchars($event['name']) ?>', <?= htmlspecialchars($event['allowed_boat_types'] ?? 'null') ?>, <?= $event['singles_enabled'] ? 'true' : 'false' ?>)">
                                            ⚙️ Settings
                                        </button>
                                <?php if ($event['main_event_id']): ?>
                                            <a href="race_creator.php?event_id=<?= $event['id'] ?>" class="btn btn-sm btn-info">Rennen erstellen</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                    </div>
            </div>
        </div>

        <!-- Boat Registrations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="mb-0">🚣‍♀️ Boot-Anmeldungen</h2>
                </div>
                <div class="card-body">
            <!-- Filter -->
                    <div class="mb-3 bg-light p-3 rounded">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label for="filter_event_id" class="form-label">Nach Event filtern:</label>
                                <select name="filter_event_id" id="filter_event_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Alle Events anzeigen</option>
                            <?php foreach ($registration_events as $event): ?>
                            <option value="<?= $event['id'] ?>" <?= $filter_event_id == $event['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Filtern</button>
                            </div>
                    <?php if ($filter_event_id): ?>
                            <div class="col-md-2">
                                <a href="?" class="btn btn-warning">Filter zurücksetzen</a>
                            </div>
                    <?php endif; ?>
                </form>
            </div>
            
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
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
                                        <br><small class="text-muted">
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
                                        <br><button type="button" class="btn btn-sm btn-info mt-1" onclick="toggleCrewDetails(<?= $boat['id'] ?>)">
                                    👥 Details anzeigen
                                </button>
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
                                        <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="update_boat_status">
                                    <input type="hidden" name="boat_id" value="<?= $boat['id'] ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="pending" <?= $boat['status'] === 'pending' ? 'selected' : '' ?>>Ausstehend</option>
                                        <option value="approved" <?= $boat['status'] === 'approved' ? 'selected' : '' ?>>Genehmigt</option>
                                        <option value="rejected" <?= $boat['status'] === 'rejected' ? 'selected' : '' ?>>Abgelehnt</option>
                                        <option value="used" <?= $boat['status'] === 'used' ? 'selected' : '' ?>>Verwendet</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <!-- Crew Details Row -->
                                <tr id="crew-details-<?= $boat['id'] ?>" style="display: none;">
                                    <td colspan="7">
                                        <div class="crew-details">
                                            <h5 class="mb-3">
                                                <span class="me-2">👥</span>
                                        Crew-Details: <?= htmlspecialchars($boat['boat_name']) ?> (<?= $boat['boat_type'] ?>)
                                            </h5>
                                    
                                    <?php if ($boat['crew_members']): ?>
                                        <?php 
                                        $crew = json_decode($boat['crew_members'], true);
                                        $crew_count = count($crew);
                                        ?>
                                                <div class="mb-3">
                                            <strong>Anzahl Crew-Mitglieder:</strong> <?= $crew_count ?> 
                                                    <span class="text-muted ms-2">
                                                (Erforderlich: <?= 
                                                    $boat['boat_type'] === '1x' ? 1 :
                                                    ($boat['boat_type'] === '2x' ? 2 :
                                                    ($boat['boat_type'] === '2x+' ? 3 :
                                                    ($boat['boat_type'] === '3x' ? 3 :
                                                    ($boat['boat_type'] === '3x+' ? 4 :
                                                    ($boat['boat_type'] === '4x' ? 4 :
                                                    ($boat['boat_type'] === '4x+' ? 5 :
                                                    ($boat['boat_type'] === '8-' ? 8 : 1)))))))
                                                ?>)
                                            </span>
                                        </div>
                                        
                                                <div class="row">
                                            <?php foreach ($crew as $index => $member): ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="crew-member">
                                                            <h6 class="mb-2">Crew-Mitglied <?= $index + 1 ?></h6>
                                                            <div class="row">
                                                                <div class="col-6">
                                                        <strong>Name:</strong><br>
                                                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                                    </div>
                                                                <div class="col-6">
                                                        <strong>Geburtsjahr:</strong><br>
                                                        <?= htmlspecialchars($member['birth_year']) ?>
                                                    </div>
                                                                <div class="col-6">
                                                        <strong>Verein:</strong><br>
                                                                    <span class="text-primary fw-bold">
                                                            <?= htmlspecialchars($member['club_name'] ?? 'Unbekannt') ?>
                                                        </span>
                                                    </div>
                                                                <div class="col-6">
                                                        <strong>Alter:</strong><br>
                                                        <?= date('Y') - $member['birth_year'] ?> Jahre
                                                                </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                                <div class="text-muted fst-italic text-center py-3 bg-light rounded">
                                            Keine Crew-Details verfügbar
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Contact Information -->
                                            <div class="mt-3 pt-3 border-top">
                                                <h6 class="mb-2">📞 Kontakt-Informationen</h6>
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <strong>Melder:</strong><br>
                                                        <?= htmlspecialchars($boat['captain_name']) ?>
                                            </div>
                                                    <div class="col-md-3">
                                                        <strong>Email:</strong><br>
                                                <?= $boat['contact_email'] ? htmlspecialchars($boat['contact_email']) : 'Nicht angegeben' ?>
                                            </div>
                                                    <div class="col-md-3">
                                                        <strong>Telefon:</strong><br>
                                                <?= $boat['contact_phone'] ? htmlspecialchars($boat['contact_phone']) : 'Nicht angegeben' ?>
                                            </div>
                                                    <div class="col-md-3">
                                                        <strong>Anmeldung:</strong><br>
                                                <?= date('d.m.Y H:i', strtotime($boat['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                    </div>
            </div>
        </div>

        <!-- Single Registrations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="mb-0">👤 Einzel-Anmeldungen</h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <a href="single_creator.php" class="btn btn-info me-2">
                    👥 Single Creator - Boote aus Einzel-Anmeldungen erstellen
                </a>
                        <a href="../create_test_singles.php" class="btn btn-success">
                    🧪 Test Einzel-Anmeldungen erstellen
                </a>
            </div>
            
            <!-- Filter -->
                    <div class="mb-3 bg-light p-3 rounded">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label for="filter_event_id_single" class="form-label">Nach Event filtern:</label>
                                <select name="filter_event_id" id="filter_event_id_single" class="form-select" onchange="this.form.submit()">
                            <option value="">Alle Events anzeigen</option>
                            <?php foreach ($registration_events as $event): ?>
                            <option value="<?= $event['id'] ?>" <?= $filter_event_id == $event['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Filtern</button>
                            </div>
                    <?php if ($filter_event_id): ?>
                            <div class="col-md-2">
                                <a href="?" class="btn btn-warning">Filter zurücksetzen</a>
                            </div>
                    <?php endif; ?>
                </form>
            </div>
            
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Geburtsjahr</th>
                                    <th>Verein</th>
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
                            <td><?= htmlspecialchars($single['club_name'] ?? 'Nicht angegeben') ?></td>
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
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="update_single_status">
                                    <input type="hidden" name="single_id" value="<?= $single['id'] ?>">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
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
        </main>

        <footer class="py-3 my-4 border-top">
            <p class="text-center text-muted">© 2023 Rowing Regatta Management</p>
        </footer>
    </div>


    <!-- Event Settings Modal -->
    <div class="modal fade" id="eventSettingsModal" tabindex="-1" aria-labelledby="eventSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventSettingsModalLabel">⚙️ Event-Einstellungen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="eventSettingsForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_event_settings">
                        <input type="hidden" name="event_id" id="settingsEventId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Event:</label>
                            <p id="settingsEventName" class="text-muted"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Erlaubte Boot-Typen:</label>
                            <p class="text-muted small">Wählen Sie die Boot-Typen aus, die für dieses Event angemeldet werden dürfen:</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_boat_types[]" value="1x" id="boat_1x">
                                        <label class="form-check-label" for="boat_1x">1x (Einer)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_boat_types[]" value="2x" id="boat_2x">
                                        <label class="form-check-label" for="boat_2x">2x (Zweier)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_boat_types[]" value="2x+" id="boat_2x_plus">
                                        <label class="form-check-label" for="boat_2x_plus">2x+ (Zweier mit Steuermann)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_boat_types[]" value="3x" id="boat_3x">
                                        <label class="form-check-label" for="boat_3x">3x (Dreier)</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_boat_types[]" value="3x+" id="boat_3x_plus">
                                        <label class="form-check-label" for="boat_3x_plus">3x+ (Dreier mit Steuermann)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_boat_types[]" value="4x" id="boat_4x">
                                        <label class="form-check-label" for="boat_4x">4x (Vierer)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_boat_types[]" value="4x+" id="boat_4x_plus">
                                        <label class="form-check-label" for="boat_4x_plus">4x+ (Vierer mit Steuermann)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_boat_types[]" value="8-" id="boat_8">
                                        <label class="form-check-label" for="boat_8">8- (Achter)</label>
                                    </div>
            </div>
        </div>
    </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Einzelanmeldungen:</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="singles_enabled" id="singles_enabled" checked>
                                <label class="form-check-label" for="singles_enabled">
                                    Einzelanmeldungen für dieses Event aktiviert
                                </label>
                            </div>
                            <div class="form-text">Wenn deaktiviert, können sich keine Einzelpersonen für dieses Event anmelden.</div>
    </div>

                        <div class="alert alert-info">
                            <small>
                                <strong>Hinweis:</strong> 
                                <ul class="mb-0 mt-1">
                                    <li>✅ <strong>Angehakt:</strong> Diese Boot-Typen sind für das Event erlaubt</li>
                                    <li>❌ <strong>Nicht angehakt:</strong> Diese Boot-Typen sind für das Event nicht erlaubt</li>
                                    <li><strong>Alle nicht angehakt:</strong> Alle Boot-Typen sind erlaubt (keine Einschränkungen)</li>
                                </ul>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCrewDetails(boatId) {
            const detailsRow = document.getElementById('crew-details-' + boatId);
            const button = event.target;
            
            if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                detailsRow.style.display = 'table-row';
                button.textContent = '👥 Details ausblenden';
                button.className = 'btn btn-sm btn-danger mt-1';
            } else {
                detailsRow.style.display = 'none';
                button.textContent = '👥 Details anzeigen';
                button.className = 'btn btn-sm btn-info mt-1';
            }
        }
        
        function openEventSettings(eventId, eventName, allowedBoatTypes, singlesEnabled) {
            // Set event ID and name
            document.getElementById('settingsEventId').value = eventId;
            document.getElementById('settingsEventName').textContent = eventName;
            
            // Clear all checkboxes first
            const checkboxes = document.querySelectorAll('input[name="allowed_boat_types[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
            
            // Set checkboxes based on allowed boat types
            if (allowedBoatTypes && allowedBoatTypes !== 'null' && allowedBoatTypes !== '') {
                try {
                    // Handle both string and already parsed JSON
                    let types;
                    if (typeof allowedBoatTypes === 'string') {
                        types = JSON.parse(allowedBoatTypes);
                    } else {
                        types = allowedBoatTypes;
                    }
                    
                    if (Array.isArray(types) && types.length > 0) {
                        // If specific types are set, check only those
                        types.forEach(type => {
                            const checkbox = document.getElementById('boat_' + type.replace('+', '_plus').replace('-', '_'));
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    }
                    // If empty array, leave all unchecked (meaning no restrictions = all allowed)
                } catch (e) {
                    console.error('Error parsing allowed boat types:', e);
                    console.log('Raw allowedBoatTypes:', allowedBoatTypes);
                    // If parsing fails, leave all unchecked (fallback to all allowed)
                }
            }
            // If no restrictions are set (null, empty, etc.), leave all unchecked (meaning all are allowed)
            
            // Set singles enabled status
            document.getElementById('singles_enabled').checked = singlesEnabled === true || singlesEnabled === 'true';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('eventSettingsModal'));
            modal.show();
        }
        
    </script>
</body>
</html> 