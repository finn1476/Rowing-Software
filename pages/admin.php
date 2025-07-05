<?php
// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection mit korrektem Pfad
include_once __DIR__ . '/../config/database.php';
// Include authentication mit korrektem Pfad
include_once __DIR__ . '/../config/auth.php';

// Überprüfe ob der Benutzer als Admin eingeloggt ist
redirectToLoginIfNotAdmin();

$conn = getDbConnection();

// Process form submissions
$message = '';
$messageType = '';

// Display message from session if available
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'];
    // Clear message after displaying
    unset($_SESSION['message'], $_SESSION['message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'add_year':
            $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            
            if ($year && $name) {
                try {
                    $stmt = $conn->prepare("INSERT INTO years (year, name, description) VALUES (:year, :name, :description)");
                    $stmt->bindParam(':year', $year);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':description', $description);
                    $stmt->execute();
                    
                    $message = "Year added successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding year: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Year and name are required.";
                $messageType = "danger";
            }
            break;
            
        case 'add_event':
            $yearId = isset($_POST['year_id']) ? (int)$_POST['year_id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $date = isset($_POST['event_date']) ? trim($_POST['event_date']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            
            if ($yearId && $name && $date) {
                try {
                    $stmt = $conn->prepare("INSERT INTO events (year_id, name, event_date, description) VALUES (:year_id, :name, :event_date, :description)");
                    $stmt->bindParam(':year_id', $yearId);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':event_date', $date);
                    $stmt->bindParam(':description', $description);
                    $stmt->execute();
                    
                    $message = "Event added successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding event: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Year, name, and date are required.";
                $messageType = "danger";
            }
            break;
            
        case 'add_race':
            $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $startTime = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
            $distance = isset($_POST['distance']) ? (int)$_POST['distance'] : 0;
            $distanceMarkers = isset($_POST['distance_markers']) ? trim($_POST['distance_markers']) : '';
            $participantsPerBoat = isset($_POST['participants_per_boat']) ? (int)$_POST['participants_per_boat'] : null;
            $status = isset($_POST['status']) ? trim($_POST['status']) : 'upcoming';
            
            if ($eventId && $name && $startTime && $distance) {
                try {
                    $stmt = $conn->prepare("INSERT INTO races (event_id, name, start_time, distance, distance_markers, participants_per_boat, status) VALUES (:event_id, :name, :start_time, :distance, :distance_markers, :participants_per_boat, :status)");
                    $stmt->bindParam(':event_id', $eventId);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':start_time', $startTime);
                    $stmt->bindParam(':distance', $distance);
                    $stmt->bindParam(':distance_markers', $distanceMarkers);
                    $stmt->bindParam(':participants_per_boat', $participantsPerBoat);
                    $stmt->bindParam(':status', $status);
                    $stmt->execute();
                    
                    $message = "Race added successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding race: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Event, name, start time, and distance are required.";
                $messageType = "danger";
            }
            break;
            
        case 'add_team':
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            
            if ($name) {
                try {
                    $stmt = $conn->prepare("INSERT INTO teams (name, description) VALUES (:name, :description)");
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':description', $description);
                    $stmt->execute();
                    
                    $message = "Team added successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding team: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Team name is required.";
                $messageType = "danger";
            }
            break;
            
        case 'add_participant':
            $teamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $birthYear = isset($_POST['birth_year']) ? (int)$_POST['birth_year'] : null;
            
            if ($teamId && $name) {
                try {
                    $stmt = $conn->prepare("INSERT INTO participants (team_id, name, birth_year) VALUES (:team_id, :name, :birth_year)");
                    $stmt->bindParam(':team_id', $teamId);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':birth_year', $birthYear);
                    $stmt->execute();
                    
                    $message = "Participant added successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding participant: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Team and name are required.";
                $messageType = "danger";
            }
            break;
            
        case 'add_race_participant':
            $raceId = isset($_POST['race_id']) ? (int)$_POST['race_id'] : 0;
            $participantIds = isset($_POST['participant_id']) ? $_POST['participant_id'] : [];
            $lane = isset($_POST['lane']) ? (int)$_POST['lane'] : 0;
            $finishTime = isset($_POST['finish_time']) ? trim($_POST['finish_time']) : null;
            $position = isset($_POST['position']) ? (int)$_POST['position'] : null;
            $boatNumber = isset($_POST['boat_number']) ? trim($_POST['boat_number']) : null;
            // $boatSeat = isset($_POST['boat_seat']) ? (int)$_POST['boat_seat'] : null; // Sitzplatz entfernt
            if ($raceId && !empty($participantIds) && $lane) {
                try {
                    foreach ($participantIds as $participantId) {
                    // Get team_id from participant
                    $teamStmt = $conn->prepare("SELECT team_id FROM participants WHERE id = :id");
                    $teamStmt->bindParam(':id', $participantId);
                    $teamStmt->execute();
                    $teamId = $teamStmt->fetchColumn();
                        $stmt = $conn->prepare("INSERT INTO race_participants (race_id, team_id, participant_id, lane, finish_time, position, boat_number, boat_seat) VALUES (:race_id, :team_id, :participant_id, :lane, :finish_time, :position, :boat_number, :boat_seat)");
                    $stmt->bindParam(':race_id', $raceId);
                    $stmt->bindParam(':team_id', $teamId);
                    $stmt->bindParam(':participant_id', $participantId);
                    $stmt->bindParam(':lane', $lane);
                    $stmt->bindParam(':finish_time', $finishTime);
                    $stmt->bindParam(':position', $position);
                        $stmt->bindParam(':boat_number', $boatNumber);
                        $null = null;
                        $stmt->bindParam(':boat_seat', $null, PDO::PARAM_NULL);
                    $stmt->execute();
                    }
                    $message = "Race participants added successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding race participants: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Race, participants, and lane are required.";
                $messageType = "danger";
            }
            break;
            
        case 'update_race_status':
            $raceId = isset($_POST['race_id']) ? (int)$_POST['race_id'] : 0;
            $status = isset($_POST['status']) ? trim($_POST['status']) : '';
            
            if ($raceId && $status) {
                try {
                    $stmt = $conn->prepare("UPDATE races SET status = :status WHERE id = :race_id");
                    $stmt->bindParam(':race_id', $raceId);
                    $stmt->bindParam(':status', $status);
                    $stmt->execute();
                    
                    $message = "Race status updated successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating race status: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Race and status are required.";
                $messageType = "danger";
            }
            break;
            
        case 'update_race_result':
            $raceParticipantId = isset($_POST['race_participant_id']) ? (int)$_POST['race_participant_id'] : 0;
            $finishTime = isset($_POST['finish_time']) ? trim($_POST['finish_time']) : null;
            $position = isset($_POST['position']) && !empty($_POST['position']) ? (int)$_POST['position'] : null;
            
            if ($raceParticipantId) {
                try {
                    // Calculate finish_seconds for sorting and calculations
                    $finishSeconds = null;
                    if ($finishTime) {
                        // Korrekte Umrechnung in Sekunden für MM:SS.ms Format
                        $parts = explode(':', $finishTime);
                        if (count($parts) === 2) {
                            // MM:SS Format
                            $minutes = (int)$parts[0];
                            $seconds = (float)$parts[1];
                            $finishSeconds = ($minutes * 60) + $seconds;
                        }
                    }
                    
                    // Debug-Ausgabe in Log-Datei schreiben
                    error_log("Finish Time: $finishTime -> Finish Seconds: $finishSeconds");
                    
                    $stmt = $conn->prepare("
                        UPDATE race_participants 
                        SET finish_time = :finish_time, finish_seconds = :finish_seconds, position = :position 
                        WHERE id = :id
                    ");
                    $stmt->bindParam(':id', $raceParticipantId);
                    $stmt->bindParam(':finish_time', $finishTime);
                    $stmt->bindParam(':finish_seconds', $finishSeconds);
                    $stmt->bindParam(':position', $position);
                    $stmt->execute();
                    
                    // If position is set, also update the race status to completed
                    if ($position) {
                        $getRaceStmt = $conn->prepare("
                            SELECT race_id FROM race_participants WHERE id = :id
                        ");
                        $getRaceStmt->bindParam(':id', $raceParticipantId);
                        $getRaceStmt->execute();
                        $raceId = $getRaceStmt->fetchColumn();
                        
                        if ($raceId) {
                            $updateRaceStmt = $conn->prepare("
                                UPDATE races SET status = 'completed' WHERE id = :race_id
                            ");
                            $updateRaceStmt->bindParam(':race_id', $raceId);
                            $updateRaceStmt->execute();
                        }
                    }
                    
                    $message = "Race result updated successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating race result: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Race participant ID is required.";
                $messageType = "danger";
            }
            break;
            
        case 'add_distance_time':
            $raceParticipantId = isset($_POST['race_participant_id']) ? (int)$_POST['race_participant_id'] : 0;
            $distance = isset($_POST['distance']) ? (int)$_POST['distance'] : 0;
            $time = isset($_POST['time']) ? trim($_POST['time']) : '';
            
            if ($raceParticipantId && $distance && $time) {
                try {
                    // Calculate seconds elapsed
                    $secondsElapsed = timeToSeconds($time);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO distance_times (race_participant_id, distance, time, seconds_elapsed)
                        VALUES (:race_participant_id, :distance, :time, :seconds_elapsed)
                    ");
                    $stmt->bindParam(':race_participant_id', $raceParticipantId);
                    $stmt->bindParam(':distance', $distance);
                    $stmt->bindParam(':time', $time);
                    $stmt->bindParam(':seconds_elapsed', $secondsElapsed);
                    $stmt->execute();
                    
                    $message = "Distance time added successfully.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding distance time: " . $e->getMessage();
                    $messageType = "danger";
                }
            } else {
                $message = "Race participant ID, distance, and time are required.";
                $messageType = "danger";
            }
            break;
    }
}

// Get data for dropdowns
$years = $conn->query("SELECT * FROM years ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC);
$events = $conn->query("SELECT e.*, y.year FROM events e JOIN years y ON e.year_id = y.id ORDER BY e.event_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$races = $conn->query("SELECT r.*, e.name as event_name FROM races r JOIN events e ON r.event_id = e.id ORDER BY r.start_time DESC")->fetchAll(PDO::FETCH_ASSOC);
$teams = $conn->query("SELECT * FROM teams ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h2>Admin Panel</h2>
        <div>
            <a href="logout.php" class="btn btn-secondary btn-sm me-2">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
            <a href="clear_database.php" class="btn btn-danger btn-sm">
                <i class="bi bi-trash"></i> Datenbank leeren
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="years-tab" data-bs-toggle="tab" data-bs-target="#years" type="button" role="tab" aria-controls="years" aria-selected="true">Years</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab" aria-controls="events" aria-selected="false">Events</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="races-tab" data-bs-toggle="tab" data-bs-target="#races" type="button" role="tab" aria-controls="races" aria-selected="false">Races</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="teams-tab" data-bs-toggle="tab" data-bs-target="#teams" type="button" role="tab" aria-controls="teams" aria-selected="false">Teams</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button" role="tab" aria-controls="participants" aria-selected="false">Participants</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="race-participants-tab" data-bs-toggle="tab" data-bs-target="#race-participants" type="button" role="tab" aria-controls="race-participants" aria-selected="false">Race Participants</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="race-results-tab" data-bs-toggle="tab" data-bs-target="#race-results" type="button" role="tab" aria-controls="race-results" aria-selected="false">Race Results</button>
            </li>
        </ul>
        
        <div class="tab-content p-3 border border-top-0 rounded-bottom" id="adminTabsContent">
            <!-- Years Tab -->
            <div class="tab-pane fade show active" id="years" role="tabpanel" aria-labelledby="years-tab">
                <h4>Add New Year</h4>
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="add_year">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="year" class="form-label">Year</label>
                            <input type="number" class="form-control" id="year" name="year" required>
                        </div>
                        <div class="col-md-5">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="1"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Year</button>
                        </div>
                    </div>
                </form>
                
                <h4>Existing Years</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Year</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($years as $year): ?>
                                <tr>
                                    <td><?php echo $year['id']; ?></td>
                                    <td><?php echo $year['year']; ?></td>
                                    <td><?php echo htmlspecialchars($year['name']); ?></td>
                                    <td><?php echo htmlspecialchars($year['description']); ?></td>
                                    <td><?php echo $year['created_at']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../edit_item.php?table=years&id=<?php echo $year['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete('years', <?php echo $year['id']; ?>)" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Events Tab -->
            <div class="tab-pane fade" id="events" role="tabpanel" aria-labelledby="events-tab">
                <h4>Add New Event</h4>
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="add_event">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="year_id" class="form-label">Year</label>
                            <select class="form-select" id="year_id" name="year_id" required>
                                <option value="">Select Year</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year['id']; ?>"><?php echo $year['year']; ?> - <?php echo htmlspecialchars($year['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="event_name" class="form-label">Event Name</label>
                            <input type="text" class="form-control" id="event_name" name="name" required>
                        </div>
                        <div class="col-md-3">
                            <label for="event_date" class="form-label">Event Date</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" required>
                        </div>
                        <div class="col-md-3">
                            <label for="event_description" class="form-label">Description</label>
                            <textarea class="form-control" id="event_description" name="description" rows="1"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Event</button>
                        </div>
                    </div>
                </form>
                
                <h4>Existing Events</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Year</th>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?php echo $event['id']; ?></td>
                                    <td><?php echo $event['year']; ?></td>
                                    <td><?php echo htmlspecialchars($event['name']); ?></td>
                                    <td><?php echo $event['event_date']; ?></td>
                                    <td><?php echo htmlspecialchars($event['description']); ?></td>
                                    <td><?php echo $event['created_at']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../edit_item.php?table=events&id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete('events', <?php echo $event['id']; ?>)" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Races Tab -->
            <div class="tab-pane fade" id="races" role="tabpanel" aria-labelledby="races-tab">
                <h4>Add New Race</h4>
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="add_race">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="event_id" class="form-label">Event</label>
                            <select class="form-select" id="event_id" name="event_id" required>
                                <option value="">Select Event</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>"><?php echo $event['year']; ?> - <?php echo htmlspecialchars($event['name']); ?> (<?php echo $event['event_date']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="race_name" class="form-label">Race Name</label>
                            <input type="text" class="form-control" id="race_name" name="name" required>
                        </div>
                        <div class="col-md-2">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="col-md-2">
                            <label for="distance" class="form-label">Distance (m)</label>
                            <input type="number" class="form-control" id="distance" name="distance" required>
                        </div>
                        <div class="col-md-2">
                            <label for="participants_per_boat" class="form-label">Teilnehmer pro Boot</label>
                            <input type="number" class="form-control" id="participants_per_boat" name="participants_per_boat" min="1" placeholder="z.B. 4">
                        </div>
                        <div class="col-md-12">
                            <label for="distance_markers" class="form-label">Distance Markers (Comma-separated, e.g. "500,1000,1500")</label>
                            <input type="text" class="form-control" id="distance_markers" name="distance_markers" placeholder="Enter distance markers separated by commas">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="upcoming">Upcoming</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Race</button>
                        </div>
                    </div>
                </form>
                
                <h4>Existing Races</h4>
                
                <!-- Event-Filter für Races -->
                <form method="get" class="mb-3" id="race-filter-form">
                    <input type="hidden" name="page" value="admin">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="event_filter" class="col-form-label">Filter by Event:</label>
                        </div>
                        <div class="col-md-4">
                            <select name="event_filter" id="event_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php
                                $events = $conn->query("SELECT id, name FROM events ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($events as $event) {
                                    $selected = isset($_GET['event_filter']) && $_GET['event_filter'] == $event['id'] ? 'selected' : '';
                                    echo "<option value=\"{$event['id']}\" {$selected}>{$event['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Event</th>
                                <th>Name</th>
                                <th>Start Time</th>
                                <th>Distance</th>
                                <th>Teilnehmer pro Boot</th>
                                <th>Markers</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Angepasste Abfrage mit Event-Filter
                            $eventFilter = isset($_GET['event_filter']) && !empty($_GET['event_filter']) ? 
                                "WHERE r.event_id = " . (int)$_GET['event_filter'] : "";
                            
                            $racesQuery = "
                                SELECT r.*, e.name as event_name
                                FROM races r
                                JOIN events e ON r.event_id = e.id
                                {$eventFilter}
                                ORDER BY r.start_time DESC
                            ";
                            $races = $conn->query($racesQuery)->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($races as $race):
                            ?>
                                <tr>
                                    <td><?php echo $race['id']; ?></td>
                                    <td><?php echo htmlspecialchars($race['event_name']); ?></td>
                                    <td><?php echo htmlspecialchars($race['name']); ?></td>
                                    <td><?php echo $race['start_time']; ?></td>
                                    <td><?php echo $race['distance']; ?> m</td>
                                    <td><?php echo isset($race['participants_per_boat']) ? htmlspecialchars($race['participants_per_boat']) : '-'; ?></td>
                                    <td><?php echo $race['distance_markers'] ?? '-'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $race['status'] == 'upcoming' ? 'primary' : ($race['status'] == 'completed' ? 'success' : 'danger'); ?>">
                                            <?php echo ucfirst($race['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $race['created_at']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../edit_item.php?table=races&id=<?php echo $race['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete('races', <?php echo $race['id']; ?>)" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Teams Tab -->
            <div class="tab-pane fade" id="teams" role="tabpanel" aria-labelledby="teams-tab">
                <h4>Add New Team</h4>
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="add_team">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="team_name" class="form-label">Team Name</label>
                            <input type="text" class="form-control" id="team_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="team_description" class="form-label">Description</label>
                            <textarea class="form-control" id="team_description" name="description" rows="1"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Team</button>
                        </div>
                    </div>
                </form>
                
                <h4>Existing Teams</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $teams = $conn->query("SELECT * FROM teams ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($teams as $team):
                            ?>
                                <tr>
                                    <td><?php echo $team['id']; ?></td>
                                    <td><?php echo htmlspecialchars($team['name']); ?></td>
                                    <td><?php echo htmlspecialchars($team['description']); ?></td>
                                    <td><?php echo $team['created_at']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../edit_item.php?table=teams&id=<?php echo $team['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete('teams', <?php echo $team['id']; ?>)" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Participants Tab -->
            <div class="tab-pane fade" id="participants" role="tabpanel" aria-labelledby="participants-tab">
                <h4>Add New Participant</h4>
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="add_participant">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="participant_team_id" class="form-label">Team</label>
                            <select class="form-select" id="participant_team_id" name="team_id" required>
                                <option value="">Select Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="participant_name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="participant_name" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="birth_year" class="form-label">Birth Year</label>
                            <input type="number" class="form-control" id="birth_year" name="birth_year" min="1900" max="<?php echo date('Y'); ?>" placeholder="e.g. 2004">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Participant</button>
                        </div>
                    </div>
                </form>
                
                <h4>Existing Participants</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Team</th>
                                <th>Name</th>
                                <th>Birth Year</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $participants = $conn->query("SELECT p.*, t.name as team_name FROM participants p JOIN teams t ON p.team_id = t.id ORDER BY t.name, p.name")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($participants as $participant):
                            ?>
                                <tr>
                                    <td><?php echo $participant['id']; ?></td>
                                    <td><?php echo htmlspecialchars($participant['team_name']); ?></td>
                                    <td><?php echo htmlspecialchars($participant['name']); ?></td>
                                    <td><?php echo $participant['birth_year'] ? $participant['birth_year'] : '-'; ?></td>
                                    <td><?php echo $participant['created_at']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../edit_item.php?table=participants&id=<?php echo $participant['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete('participants', <?php echo $participant['id']; ?>)" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Race Participants Tab -->
            <div class="tab-pane fade" id="race-participants" role="tabpanel" aria-labelledby="race-participants-tab">
                <h4>Add Participant to Race</h4>
                <form method="post" class="mb-4" id="add_race_participant_form">
                    <input type="hidden" name="action" value="add_race_participant">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="filter_event_id" class="form-label">Event (optional)</label>
                            <select class="form-select" id="filter_event_id">
                                <option value="">-- All Events --</option>
                                <?php
                                $events = $conn->query("SELECT id, name FROM events ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($events as $event) {
                                    echo "<option value=\"{$event['id']}\">{$event['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="race_id" class="form-label">Race</label>
                            <select class="form-select" id="race_id" name="race_id" required onchange="checkAvailableLanes()">
                                <option value="">-- Select Race --</option>
                                <?php
                                $races = $conn->query("
                                    SELECT r.id, r.name, e.name as event_name, e.id as event_id
                                    FROM races r 
                                    JOIN events e ON r.event_id = e.id 
                                    ORDER BY e.name, r.name
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($races as $race) {
                                    echo "<option value=\"{$race['id']}\" data-event=\"{$race['event_name']}\" data-event-id=\"{$race['event_id']}\">{$race['event_name']} - {$race['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="participant_id" class="form-label">Participants</label>
                            <select class="form-select" id="participant_id" name="participant_id[]" multiple required>
                                <option value="">Select Participants</option>
                                <?php 
                                $participants = $conn->query("SELECT id, name FROM participants ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($participants as $participant) {
                                    echo "<option value=\"{$participant['id']}\">{$participant['name']}</option>";
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">Halte Strg/Cmd gedrückt, um mehrere auszuwählen</small>
                        </div>
                        <div class="col-md-2">
                            <label for="lane" class="form-label">Lane</label>
                            <select class="form-select" id="lane" name="lane" required>
                                <option value="">Select Lane</option>
                                <?php for($i=1; $i<=9; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <div id="lane-feedback" class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-2">
                            <label for="boat_number" class="form-label">Bugnummer</label>
                            <input type="text" class="form-control" id="boat_number" name="boat_number" placeholder="z.B. 12">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" id="add_participant_btn">Add Participant to Race</button>
                        </div>
                    </div>
                </form>
                
                <!-- JavaScript für Lane-Validierung -->
                <script>
                    // Funktion zum Prüfen verfügbarer Lanes
                    function checkAvailableLanes() {
                        const raceId = document.getElementById('race_id').value;
                        const laneSelect = document.getElementById('lane');
                        const laneFeedback = document.getElementById('lane-feedback');
                        
                        if (!raceId) return;
                        
                        // AJAX-Anfrage, um belegte Lanes abzurufen
                        fetch(`get_occupied_lanes.php?race_id=${raceId}`)
                            .then(response => response.json())
                            .then(data => {
                                // Zurücksetzen aller Optionen
                                for (let i = 1; i <= 9; i++) {
                                    const option = laneSelect.querySelector(`option[value="${i}"]`);
                                    if (option) {
                                        option.disabled = false;
                                        option.textContent = i.toString();
                                    }
                                }
                                
                                // Belegte Lanes deaktivieren
                                data.occupied_lanes.forEach(lane => {
                                    const option = laneSelect.querySelector(`option[value="${lane.lane}"]`);
                                    if (option) {
                                        option.disabled = true;
                                        option.textContent = `${lane.lane} (${lane.participant_name})`;
                                    }
                                });
                                
                                // Falls die aktuell ausgewählte Lane belegt ist, Auswahl zurücksetzen
                                if (laneSelect.value && data.occupied_lanes.some(l => l.lane == laneSelect.value)) {
                                    laneSelect.value = '';
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching occupied lanes:', error);
                            });
                    }
                    
                    // Event-Listener für das Formular
                    document.getElementById('add_race_participant_form').addEventListener('submit', function(event) {
                        const raceId = document.getElementById('race_id').value;
                        const lane = document.getElementById('lane').value;
                        const laneFeedback = document.getElementById('lane-feedback');
                        
                        if (!raceId || !lane) return;
                        
                        // AJAX-Anfrage um zu prüfen, ob die Lane belegt ist
                        fetch(`check_lane.php?race_id=${raceId}&lane=${lane}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.is_occupied) {
                                    event.preventDefault();
                                    document.getElementById('lane').classList.add('is-invalid');
                                    laneFeedback.textContent = `Lane ${lane} is already occupied by ${data.participant_name}`;
                                }
                            })
                            .catch(error => {
                                console.error('Error checking lane:', error);
                            });
                    });
                    
                    // Beim Seitenladen
                    document.addEventListener('DOMContentLoaded', function() {
                        // Wenn eine Race bereits ausgewählt ist, verfügbare Lanes prüfen
                        if (document.getElementById('race_id').value) {
                            checkAvailableLanes();
                        }
                    });
                    
                    // Filter participants based on selected team
                    document.getElementById('filter_team_id').addEventListener('change', function() {
                        const teamId = this.value;
                        const participantSelect = document.getElementById('participant_id');
                        const options = participantSelect.querySelectorAll('option');
                        
                        for (let i = 0; i < options.length; i++) {
                            const option = options[i];
                            if (option.value === '') {
                                // Always show the placeholder option
                                option.style.display = '';
                            } else if (!teamId || option.getAttribute('data-team') === teamId) {
                                option.style.display = '';
                            } else {
                                option.style.display = 'none';
                            }
                        }
                        
                        // Reset selection
                        participantSelect.value = '';
                    });
                </script>
                
                <h4>Existing Race Participants</h4>
                
                <!-- Event-Filter für Race Participants -->
                <form method="get" class="mb-3" id="participant-filter-form">
                    <input type="hidden" name="page" value="admin">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="rp_event_filter" class="col-form-label">Filter by Event:</label>
                        </div>
                        <div class="col-md-4">
                            <select name="rp_event_filter" id="rp_event_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php
                                foreach ($events as $event) {
                                    $selected = isset($_GET['rp_event_filter']) && $_GET['rp_event_filter'] == $event['id'] ? 'selected' : '';
                                    echo "<option value=\"{$event['id']}\" {$selected}>{$event['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Race</th>
                                <th>Team</th>
                                <th>Participants</th>
                                <th>Lane</th>
                                <th>Finish Time</th>
                                <th>Position</th>
                                <th>Bugnummer</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Angepasste Abfrage mit Event-Filter
                            $rpEventFilter = isset($_GET['rp_event_filter']) && !empty($_GET['rp_event_filter']) ? 
                                "AND r.event_id = " . (int)$_GET['rp_event_filter'] : "";
                            
                            $raceParticipants = $conn->query("
                                SELECT rp.*, r.name as race_name, t.name as team_name, 
                                       p.name as participant_name, p.birth_year, rp.lane, rp.boat_number, rp.boat_seat
                                FROM race_participants rp
                                JOIN races r ON rp.race_id = r.id
                                JOIN teams t ON rp.team_id = t.id
                                JOIN participants p ON rp.participant_id = p.id
                                WHERE 1=1 {$rpEventFilter}
                                ORDER BY r.start_time DESC, rp.lane ASC
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Gruppiere Race Participants nach Race, Lane und Bugnummer für die Anzeige
                            $groupedBoats = [];
                            foreach ($raceParticipants as $rp) {
                                $key = $rp['race_id'] . '_' . $rp['lane'] . '_' . ($rp['boat_number'] ?? '');
                                if (!isset($groupedBoats[$key])) {
                                    $groupedBoats[$key] = [
                                        'ids' => [],
                                        'race_name' => $rp['race_name'],
                                        'team_names' => [],
                                        'participants' => [],
                                        'lane' => $rp['lane'],
                                        'finish_time' => $rp['finish_time'],
                                        'position' => $rp['position'],
                                        'boat_number' => $rp['boat_number'],
                                        'boat_seat' => $rp['boat_seat'],
                                    ];
                                }
                                $groupedBoats[$key]['ids'][] = $rp['id'];
                                $groupedBoats[$key]['team_names'][] = $rp['team_name'];
                                $nameWithYear = $rp['participant_name'];
                                if ($rp['birth_year']) {
                                    $nameWithYear .= ' (' . substr($rp['birth_year'], -2) . ')';
                                }
                                $groupedBoats[$key]['participants'][] = $nameWithYear;
                            }
                            foreach ($groupedBoats as $boat) :
                            ?>
                                <tr>
                                <td><?php echo implode(',', $boat['ids']); ?></td>
                                <td><?php echo htmlspecialchars($boat['race_name']); ?></td>
                                <td><?php echo implode(', ', array_unique($boat['team_names'])); ?></td>
                                <td><?php echo implode(', ', $boat['participants']); ?></td>
                                <td><?php echo htmlspecialchars($boat['lane']); ?></td>
                                <td><?php echo $boat['finish_time'] ? htmlspecialchars($boat['finish_time']) : '-'; ?></td>
                                <td><?php echo $boat['position'] ? htmlspecialchars($boat['position']) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($boat['boat_number'] ?? '-'); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                        <a href="../edit_item.php?table=race_participants&id=<?php echo $boat['ids'][0]; ?>" class="btn btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        <a href="javascript:void(0);" onclick="confirmDelete('race_participants', <?php echo $boat['ids'][0]; ?>)" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Race Results Tab -->
            <div class="tab-pane fade" id="race-results" role="tabpanel" aria-labelledby="race-results-tab">
                <h4>Update Race Status</h4>
                
                <!-- Event-Filter für Update Race Status -->
                <form method="get" class="mb-3" id="race-status-filter-form">
                    <input type="hidden" name="page" value="admin">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="status_event_filter" class="col-form-label">Filter by Event:</label>
                        </div>
                        <div class="col-md-4">
                            <select name="status_event_filter" id="status_event_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php
                                foreach ($events as $event) {
                                    $selected = isset($_GET['status_event_filter']) && $_GET['status_event_filter'] == $event['id'] ? 'selected' : '';
                                    echo "<option value=\"{$event['id']}\" {$selected}>{$event['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>
                
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="update_race_status">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="result_race_id" class="form-label">Race</label>
                            <select class="form-select" id="result_race_id" name="race_id" required>
                                <option value="">Select Race</option>
                                <?php 
                                // Filter für Update Race Status
                                $statusEventFilter = isset($_GET['status_event_filter']) && !empty($_GET['status_event_filter']) ? 
                                    "WHERE r.event_id = " . (int)$_GET['status_event_filter'] : "";
                                
                                $statusRaces = $conn->query("
                                    SELECT r.id, r.name, e.name as event_name, r.start_time 
                                    FROM races r 
                                    JOIN events e ON r.event_id = e.id 
                                    {$statusEventFilter}
                                    ORDER BY r.start_time DESC
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($statusRaces as $race): 
                                ?>
                                    <option value="<?php echo $race['id']; ?>"><?php echo htmlspecialchars($race['name']); ?> (<?php echo htmlspecialchars($race['event_name']); ?>, <?php echo date('M d, Y', strtotime($race['start_time'])); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="result_status" class="form-label">Status</label>
                            <select class="form-select" id="result_status" name="status" required>
                                <option value="upcoming">Upcoming</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Update Race Status</button>
                        </div>
                    </div>
                </form>
                
                <h4>Record Race Results</h4>
                
                <!-- Event- und Race-Filter für Record Race Results -->
                <form method="get" class="mb-3" id="results-filter-form">
                    <input type="hidden" name="page" value="admin">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="results_event_filter" class="col-form-label">Filter by Event:</label>
                        </div>
                        <div class="col-md-4">
                            <select name="results_event_filter" id="results_event_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php
                                foreach ($events as $event) {
                                    $selected = isset($_GET['results_event_filter']) && $_GET['results_event_filter'] == $event['id'] ? 'selected' : '';
                                    echo "<option value=\"{$event['id']}\" {$selected}>{$event['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="race_select" class="form-label">Race</label>
                            <select class="form-select" id="race_select" name="race_select" onchange="this.form.submit()" required>
                                <option value="">Select Race</option>
                                <?php
                                $racesEventFilter = isset($_GET['results_event_filter']) && !empty($_GET['results_event_filter']) ? 
                                    "WHERE r.event_id = " . (int)$_GET['results_event_filter'] . " AND r.status = 'completed'" : 
                                    "WHERE r.status = 'completed'";
                                $raceResults = $conn->query("
                                    SELECT r.id, r.name, e.name as event_name, r.start_time 
                                    FROM races r 
                                    JOIN events e ON r.event_id = e.id 
                                    {$racesEventFilter}
                                    ORDER BY r.start_time DESC
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                $selectedRaceId = isset($_GET['race_select']) ? (int)$_GET['race_select'] : 0;
                                foreach ($raceResults as $race): 
                                    $selected = $selectedRaceId === (int)$race['id'] ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $race['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($race['name']); ?> (<?php echo htmlspecialchars($race['event_name']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
                <!-- Das eigentliche Ergebnis-Formular bleibt POST -->
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="update_race_result">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="result_rp_id" class="form-label">Boot (Lane/Bugnummer)</label>
                            <select class="form-select" id="result_rp_id" name="race_participant_id" required onchange="updatePositionForTime()">
                                <option value="">Select Boot</option>
                                <?php
                                $rpEventFilter = isset($_GET['results_event_filter']) && !empty($_GET['results_event_filter']) ? 
                                    "AND r.event_id = " . (int)$_GET['results_event_filter'] : "";
                                $raceIdFilter = $selectedRaceId ? "AND rp.race_id = $selectedRaceId" : "";
                                $raceParticipantsForResults = $conn->query("
                                    SELECT rp.id, rp.race_id, r.name as race_name, t.name as team_name, p.name as participant_name, p.birth_year, rp.lane, rp.boat_number, r.start_time
                                    FROM race_participants rp
                                    JOIN races r ON rp.race_id = r.id
                                    JOIN teams t ON rp.team_id = t.id
                                    JOIN participants p ON rp.participant_id = p.id
                                    WHERE r.status = 'completed' {$rpEventFilter} {$raceIdFilter}
                                    ORDER BY r.start_time DESC, rp.lane ASC
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                // Gruppieren nach Boot
                                $bootGroups = [];
                                foreach ($raceParticipantsForResults as $rp) {
                                    $key = $rp['race_id'] . '_' . $rp['lane'] . '_' . ($rp['boat_number'] ?? '');
                                    if (!isset($bootGroups[$key])) {
                                        $bootGroups[$key] = [
                                            'ids' => [],
                                            'race_name' => $rp['race_name'],
                                            'lane' => $rp['lane'],
                                            'boat_number' => $rp['boat_number'],
                                            'participants' => [],
                                        ];
                                    }
                                    $bootGroups[$key]['ids'][] = $rp['id'];
                                    $nameWithYear = $rp['participant_name'];
                                    if ($rp['birth_year']) {
                                        $nameWithYear .= ' (' . substr($rp['birth_year'], -2) . ')';
                                    }
                                    $bootGroups[$key]['participants'][] = $nameWithYear;
                                }
                                foreach ($bootGroups as $boot) {
                                    // Für das Dropdown nehmen wir die erste id des Boots
                                    $label = htmlspecialchars($boot['race_name']) . ' - Lane ' . htmlspecialchars($boot['lane']);
                                    if ($boot['boat_number']) $label .= ' - Bug ' . htmlspecialchars($boot['boat_number']);
                                    $label .= ' - ' . implode(', ', $boot['participants']);
                                    echo '<option value="' . $boot['ids'][0] . '">' . $label . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="finish_time" class="form-label">Finish Time</label>
                            <input type="text" class="form-control" id="finish_time" name="finish_time" placeholder="MM:SS.ms" onchange="updatePositionForTime()">
                            <small class="form-text text-muted">Format: Minuten:Sekunden.Millisekunden z.B. 01:29.45</small>
                        </div>
                        <div class="col-md-2">
                            <label for="position" class="form-label">Position</label>
                            <input type="number" class="form-control" id="position" name="position">
                            <small class="form-text text-muted">Auto-calculated based on time</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Update Result</button>
                        </div>
                    </div>
                </form>
                
                <!-- JavaScript für die automatische Position basierend auf der Zeit -->
                <script>
                    // Globale Variable für die aktuellen Rennergebnisse
                    let currentRaceResults = [];
                    
                    // Funktion zum Laden der aktuellen Positionen und Zeiten
                    function loadCurrentPositions() {
                        const raceId = document.getElementById('race_select').value;
                        if (!raceId) return;
                        
                        console.log("Lade Ergebnisse für Rennen:", raceId);
                        
                        // Status anzeigen
                        const statusText = document.createElement('div');
                        statusText.className = 'text-muted small mb-2';
                        statusText.id = 'race-results-loading';
                        statusText.textContent = 'Lade aktuelle Rennergebnisse...';
                        
                        const existingStatus = document.getElementById('race-results-loading');
                        if (existingStatus) {
                            existingStatus.replaceWith(statusText);
                        } else {
                            const finishTimeInput = document.getElementById('finish_time');
                            if (finishTimeInput) {
                                finishTimeInput.parentNode.appendChild(statusText);
                            }
                        }
                        
                        // API URL - Relativ zum aktuellen Pfad (pages/admin.php -> ../get_race_results.php)
                        const apiUrl = "../get_race_results.php";
                        
                        console.log("Verwende API URL:", apiUrl);
                        console.log("Mit Parameter race_id =", raceId);
                        
                        // Anfrage mit dem direkten Pfad
                        fetch(`${apiUrl}?race_id=${raceId}`)
                            .then(response => {
                                console.log("API Antwort Status:", response.status);
                                if (!response.ok) {
                                    return response.text().then(text => {
                                        console.error("API Fehlerantwort:", text);
                                        throw new Error(`Server antwortete mit ${response.status}: ${text}`);
                                    });
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log("Race results:", data);
                                
                                if (!data.success) {
                                    throw new Error(data.message || "Unbekannter Fehler beim Laden der Rennergebnisse");
                                }
                                
                                // Konvertiere alle Zeiten in Sekunden
                                currentRaceResults = data.results.map(result => {
                                    // Wenn finish_seconds nicht gesetzt ist, berechne es aus finish_time
                                    if ((result.finish_seconds === null || result.finish_seconds === 0) && result.finish_time) {
                                        const timeParts = result.finish_time.split(':');
                                        
                                        // Format MM:SS.ms - Das häufigste Format
                                        if (timeParts.length === 2) {
                                            const minutes = parseInt(timeParts[0]);
                                            const seconds = parseFloat(timeParts[1]);
                                            result.finish_seconds = (minutes * 60) + seconds;
                                            console.log(`Korrigierte Zeit für ID ${result.id}: ${result.finish_time} = ${result.finish_seconds} Sekunden`);
                                        }
                                        // Format HH:MM:SS.ms - Falls vorhanden
                                        else if (timeParts.length === 3) {
                                            const hours = parseInt(timeParts[0]);
                                            const minutes = parseInt(timeParts[1]);
                                            const seconds = parseFloat(timeParts[2]);
                                            result.finish_seconds = (hours * 3600) + (minutes * 60) + seconds;
                                            console.log(`Korrigierte Zeit (mit Stunden) für ID ${result.id}: ${result.finish_time} = ${result.finish_seconds} Sekunden`);
                                        }
                                    }
                                    
                                    // Korrigiere extrem große Werte (über 1 Stunde), die vermutlich falsch sind
                                    if (result.finish_seconds > 3600) {
                                        console.warn(`Verdächtig hoher Wert für finish_seconds: ${result.finish_seconds} (${result.finish_time}). Versuche zu korrigieren...`);
                                        
                                        // Versuche, die Zeit aus dem finish_time neu zu berechnen
                                        if (result.finish_time) {
                                            const timeParts = result.finish_time.split(':');
                                            if (timeParts.length >= 2) {
                                                // Interpretiere das Format neu: letzte zwei Teile als Minuten und Sekunden
                                                const lastIndex = timeParts.length - 1;
                                                const seconds = parseFloat(timeParts[lastIndex]);
                                                const minutes = parseInt(timeParts[lastIndex - 1]);
                                                result.finish_seconds = (minutes * 60) + seconds;
                                                console.log(`Korrigierte verdächtige Zeit: ${result.finish_time} = ${result.finish_seconds} Sekunden`);
                                            }
                                        }
                                    }
                                    
                                    return result;
                                }).sort((a, b) => {
                                    // Null-Werte ans Ende
                                    if (a.finish_seconds === null) return 1;
                                    if (b.finish_seconds === null) return -1;
                                    // Aufsteigend nach Zeit
                                    return a.finish_seconds - b.finish_seconds;
                                });
                                
                                console.log("Konvertierte und sortierte Ergebnisse:", currentRaceResults);
                                
                                if (statusText) {
                                    statusText.textContent = `${currentRaceResults.length} Teilnehmer im Rennen geladen.`;
                                    // Entferne Status nach kurzer Zeit
                                    setTimeout(() => {
                                        statusText.remove();
                                    }, 3000);
                                }
                                
                                // Falls bereits eine Zeit eingegeben wurde, Position aktualisieren
                                updatePositionForTime();
                            })
                            .catch(error => {
                                console.error('Fehler beim Laden der Rennergebnisse:', error);
                                if (statusText) {
                                    statusText.className = 'text-danger small mb-2';
                                    statusText.textContent = `Fehler: ${error.message}`;
                                }
                            });
                    }
                    
                    // Funktion zur Berechnung der Position basierend auf der Zeit
                    function updatePositionForTime() {
                        const timeInput = document.getElementById('finish_time');
                        const positionInput = document.getElementById('position');
                        const participantSelect = document.getElementById('result_rp_id');
                        
                        if (!timeInput.value || !currentRaceResults.length) return;
                        
                        // Zeit in Sekunden umrechnen
                        const timeValue = timeInput.value;
                        let seconds = 0;
                        
                        // Verschiedene Zeitformate erkennen
                        if (timeValue.includes(':')) {
                            const parts = timeValue.split(':');
                            
                            // Format: MM:SS.ms
                            if (parts.length === 2) {
                                seconds = parseInt(parts[0]) * 60 + parseFloat(parts[1]);
                                console.log(`Zeit umgerechnet: ${parts[0]} Minuten und ${parts[1]} Sekunden = ${seconds} Sekunden`);
                            } 
                            // Format HH:MM:SS.ms (weniger wahrscheinlich, aber zur Sicherheit)
                            else if (parts.length === 3) {
                                seconds = parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + parseFloat(parts[2]);
                                console.log(`Zeit umgerechnet: ${parts[0]} Stunden, ${parts[1]} Minuten und ${parts[2]} Sekunden = ${seconds} Sekunden`);
                            }
                        } else {
                            // Direkt Sekunden
                            seconds = parseFloat(timeValue);
                        }
                        
                        if (isNaN(seconds)) {
                            console.error("Ungültiges Zeitformat:", timeValue);
                            return;
                        }
                        
                        console.log("Berechnete Zeit in Sekunden:", seconds);
                        console.log("Aktuelle Ergebnisse:", currentRaceResults);
                        
                        // Berechnen der Position basierend auf der Zeit
                        let position = 1;
                        const participantId = participantSelect.value;
                        
                        // Sortiere Ergebnisse nach Zeit (aufsteigend) und filtere null-Werte
                        const sortedResults = [...currentRaceResults]
                            .filter(r => r.finish_seconds !== null && r.finish_seconds > 0) // Nur gültige Zeiten betrachten
                            .sort((a, b) => a.finish_seconds - b.finish_seconds);
                        
                        console.log("Sortierte Ergebnisse:", sortedResults);
                        
                        // Füge das aktuelle Ergebnis temporär hinzu
                        const mergedResults = [...sortedResults];
                        
                        // Füge die aktuelle Zeit hinzu (wenn es nicht schon der aktuelle Teilnehmer ist)
                        let inserted = false;
                        for (let i = 0; i < mergedResults.length; i++) {
                            // Wenn es der gleiche Teilnehmer ist, aktualisiere die Zeit
                            if (mergedResults[i].id == participantId) {
                                mergedResults[i].finish_seconds = seconds;
                                inserted = true;
                                break;
                            }
                            
                            // An der richtigen Position einfügen
                            if (mergedResults[i].finish_seconds > seconds && !inserted) {
                                mergedResults.splice(i, 0, { id: 'current', finish_seconds: seconds });
                                inserted = true;
                            }
                        }
                        
                        // Falls noch nicht eingefügt (langsamste Zeit oder keine anderen Zeiten)
                        if (!inserted) {
                            mergedResults.push({ id: 'current', finish_seconds: seconds });
                        }
                        
                        // Neu sortieren nach dem Einfügen
                        mergedResults.sort((a, b) => a.finish_seconds - b.finish_seconds);
                        
                        console.log("Zusammengeführte und sortierte Ergebnisse:", mergedResults);
                        
                        // Finde die Position der aktuellen Zeit
                        for (let i = 0; i < mergedResults.length; i++) {
                            if (mergedResults[i].id == participantId || mergedResults[i].id == 'current') {
                                position = i + 1;
                                break;
                            }
                        }
                        
                        console.log("Berechnete Position:", position);
                        
                        // Position setzen
                        positionInput.value = position;
                    }
                    
                    // Funktion zur Teilnehmerfilterung nach Rennen
                    function updateParticipants() {
                        const raceSelect = document.getElementById('race_select');
                        const participantSelect = document.getElementById('result_rp_id');
                        const selectedRaceId = raceSelect.value;
                        
                        // Erste Option (Placeholder) immer anzeigen
                        participantSelect.selectedIndex = 0;
                        
                        // Alle Optionen durchlaufen (ab Index 1, um den Placeholder zu überspringen)
                        for (let i = 1; i < participantSelect.options.length; i++) {
                            const option = participantSelect.options[i];
                            const raceId = option.getAttribute('data-race');
                            
                            // Wenn kein Rennen ausgewählt oder das Rennen passt, Option anzeigen, sonst verstecken
                            option.style.display = (!selectedRaceId || raceId === selectedRaceId) ? '' : 'none';
                        }
                    }
                    
                    // Event-Listener für das Rennen-Dropdown
                    document.addEventListener('DOMContentLoaded', function() {
                        // Wenn ein Rennen bereits ausgewählt ist, Positionen laden
                        if (document.getElementById('race_select').value) {
                            loadCurrentPositions();
                        }
                    });
                </script>
                
                <!-- Incremental Distance Times Section -->
                <h4>Record Incremental Distance Times</h4>
                
                <!-- Event und Race Filter für Distance Times -->
                <form method="get" class="mb-3" id="distance-filter-form">
                    <input type="hidden" name="page" value="admin">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="distance_event_filter" class="col-form-label">Filter by Event:</label>
                        </div>
                        <div class="col-md-3">
                            <select name="distance_event_filter" id="distance_event_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php
                                foreach ($events as $event) {
                                    $selected = isset($_GET['distance_event_filter']) && $_GET['distance_event_filter'] == $event['id'] ? 'selected' : '';
                                    echo "<option value=\"{$event['id']}\" {$selected}>{$event['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label for="distance_race_filter" class="col-form-label">Filter by Race:</label>
                        </div>
                        <div class="col-md-3">
                            <select name="distance_race_filter" id="distance_race_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">All Races</option>
                                <?php
                                // Nur Rennen des ausgewählten Events anzeigen
                                $distanceEventFilter = isset($_GET['distance_event_filter']) && !empty($_GET['distance_event_filter']) ? 
                                    "WHERE event_id = " . (int)$_GET['distance_event_filter'] : "";
                                
                                $distanceRaces = $conn->query("
                                    SELECT id, name FROM races 
                                    {$distanceEventFilter}
                                    ORDER BY name
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($distanceRaces as $race) {
                                    $selected = isset($_GET['distance_race_filter']) && $_GET['distance_race_filter'] == $race['id'] ? 'selected' : '';
                                    echo "<option value=\"{$race['id']}\" {$selected}>{$race['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>
                
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="add_distance_time">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="distance_rp_id" class="form-label">Boot (Lane/Bugnummer)</label>
                            <select class="form-select" id="distance_rp_id" name="race_participant_id" required>
                                <option value="">Select Boot</option>
                                <?php 
                                // Hole alle Race Participants für das Filterrennen
                                $distanceRpEventFilter = isset($_GET['distance_event_filter']) && !empty($_GET['distance_event_filter']) ? 
                                    "AND r.event_id = " . (int)$_GET['distance_event_filter'] : "";
                                $distanceRpRaceFilter = isset($_GET['distance_race_filter']) && !empty($_GET['distance_race_filter']) ? 
                                    "AND r.id = " . (int)$_GET['distance_race_filter'] : "";
                                $distanceParticipants = $conn->query("
                                    SELECT rp.id, rp.race_id, r.name as race_name, t.name as team_name, p.name as participant_name, p.birth_year, rp.lane, rp.boat_number
                                    FROM race_participants rp
                                    JOIN races r ON rp.race_id = r.id
                                    JOIN teams t ON rp.team_id = t.id
                                    JOIN participants p ON rp.participant_id = p.id
                                    WHERE 1=1 {$distanceRpEventFilter} {$distanceRpRaceFilter}
                                    ORDER BY r.name, rp.lane
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                // Gruppieren nach Boot
                                $bootGroups = [];
                                foreach ($distanceParticipants as $rp) {
                                    $key = $rp['race_id'] . '_' . $rp['lane'] . '_' . ($rp['boat_number'] ?? '');
                                    if (!isset($bootGroups[$key])) {
                                        $bootGroups[$key] = [
                                            'ids' => [],
                                            'race_name' => $rp['race_name'],
                                            'lane' => $rp['lane'],
                                            'boat_number' => $rp['boat_number'],
                                            'participants' => [],
                                        ];
                                    }
                                    $bootGroups[$key]['ids'][] = $rp['id'];
                                    $nameWithYear = $rp['participant_name'];
                                    if ($rp['birth_year']) {
                                        $nameWithYear .= ' (' . substr($rp['birth_year'], -2) . ')';
                                    }
                                    $bootGroups[$key]['participants'][] = $nameWithYear;
                                }
                                foreach ($bootGroups as $boot) {
                                    // Für das Dropdown nehmen wir die erste id des Boots
                                    $label = htmlspecialchars($boot['race_name']) . ' - Lane ' . htmlspecialchars($boot['lane']);
                                    if ($boot['boat_number']) $label .= ' - Bug ' . htmlspecialchars($boot['boat_number']);
                                    $label .= ' - ' . implode(', ', $boot['participants']);
                                    echo '<option value="' . $boot['ids'][0] . '">' . $label . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="distance_point" class="form-label">Distance (m)</label>
                            <input type="number" class="form-control" id="distance_point" name="distance" required>
                            <small class="form-text text-muted">Enter the distance marker point (e.g., 500, 1000)</small>
                        </div>
                        <div class="col-md-4">
                            <label for="distance_time" class="form-label">Time (MM:SS.ms)</label>
                            <input type="text" class="form-control" id="distance_time" name="time" placeholder="e.g. 01:23.456" required>
                            <small class="form-text text-muted">Enter time in minutes:seconds.milliseconds format</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Distance Time</button>
                        </div>
                    </div>
                </form>

                <!-- Existing Distance Times -->
                <h4>Existing Distance Times</h4>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Race</th>
                                <th>Team</th>
                                <th>Lane</th>
                                <th>Distance (m)</th>
                                <th>Time</th>
                                <th>Teilnehmer</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Hole alle Distance Times und Race Participants für die Gruppierung
                            $dtEventFilter = isset($_GET['results_event_filter']) && !empty($_GET['results_event_filter']) ? 
                                "AND r.event_id = " . (int)$_GET['results_event_filter'] : "";
                            $distanceTimes = $conn->query("
                                SELECT dt.*, rp.lane, rp.boat_number, r.name as race_name, t.name as team_name, p.name as participant_name, p.birth_year, rp.race_id
                                FROM distance_times dt
                                JOIN race_participants rp ON dt.race_participant_id = rp.id
                                JOIN races r ON rp.race_id = r.id
                                JOIN teams t ON rp.team_id = t.id
                                JOIN participants p ON rp.participant_id = p.id
                                WHERE 1=1 {$dtEventFilter}
                                ORDER BY r.start_time DESC, rp.lane ASC, dt.distance ASC
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            // Gruppieren nach Boot und Distance
                            $distanceBoots = [];
                            foreach ($distanceTimes as $dt) {
                                $key = $dt['race_id'] . '_' . $dt['lane'] . '_' . ($dt['boat_number'] ?? '') . '_' . $dt['distance'];
                                if (!isset($distanceBoots[$key])) {
                                    $distanceBoots[$key] = [
                                        'ids' => [],
                                        'race_name' => $dt['race_name'],
                                        'team_names' => [],
                                        'participants' => [],
                                        'lane' => $dt['lane'],
                                        'boat_number' => $dt['boat_number'],
                                        'distance' => $dt['distance'],
                                        'time' => $dt['time'],
                                    ];
                                }
                                $distanceBoots[$key]['ids'][] = $dt['id'];
                                $distanceBoots[$key]['team_names'][] = $dt['team_name'];
                                $nameWithYear = $dt['participant_name'];
                                if ($dt['birth_year']) {
                                    $nameWithYear .= ' (' . substr($dt['birth_year'], -2) . ')';
                                }
                                $distanceBoots[$key]['participants'][] = $nameWithYear;
                            }
                            foreach ($distanceBoots as $key => $boot) :
                                // race_id für die Teilnehmerabfrage setzen
                                if (!isset($boot['race_id']) && strpos($key, '_') !== false) {
                                    $parts = explode('_', $key);
                                    $boot['race_id'] = $parts[0];
                                }
                                // Alle Teilnehmer für dieses Boot (gleiche race_id, lane, boat_number) holen
                                $bootTeilnehmer = $conn->query(
                                    "SELECT p.name, p.birth_year FROM race_participants rp JOIN participants p ON rp.participant_id = p.id WHERE rp.race_id = " . (int)$boot['race_id'] .
                                    " AND rp.lane = " . (int)$boot['lane'] .
                                    (isset($boot['boat_number']) && $boot['boat_number'] !== null ? " AND rp.boat_number = '" . addslashes($boot['boat_number']) . "'" : " AND (rp.boat_number IS NULL OR rp.boat_number = '')")
                                )->fetchAll(PDO::FETCH_ASSOC);
                                $teilnehmerNamen = [];
                                foreach ($bootTeilnehmer as $teiln) {
                                    $name = $teiln['name'];
                                    if ($teiln['birth_year']) $name .= ' (' . substr($teiln['birth_year'], -2) . ')';
                                    $teilnehmerNamen[] = $name;
                                }
                            ?>
                                <tr>
                                <td><?php echo implode(',', $boot['ids']); ?></td>
                                <td><?php echo htmlspecialchars($boot['race_name']); ?></td>
                                <td><?php echo implode(', ', array_unique($boot['team_names'])); ?></td>
                                <td><?php echo htmlspecialchars($boot['lane']); ?></td>
                                <td><?php echo htmlspecialchars($boot['distance']); ?></td>
                                <td><?php echo htmlspecialchars($boot['time']); ?></td>
                                <td><?php echo implode(', ', $teilnehmerNamen); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                        <a href="../edit_item.php?table=distance_times&id=<?php echo $boot['ids'][0]; ?>" class="btn btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        <a href="javascript:void(0);" onclick="confirmDelete('distance_times', <?php echo $boot['ids'][0]; ?>)" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div> 

<!-- Add JavaScript for delete confirmation -->
<script>
    function confirmDelete(table, id) {
        if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            window.location.href = '../delete_item.php?table=' + table + '&id=' + id;
        }
    }

    // Store active tab in sessionStorage
    document.addEventListener('DOMContentLoaded', function() {
        // Set active tab based on stored value
        const activeTab = sessionStorage.getItem('adminActiveTab');
        if (activeTab) {
            const tabElement = document.querySelector(activeTab);
            if (tabElement) {
                const tab = new bootstrap.Tab(tabElement);
                tab.show();
            }
        }
        
        // Store tab when clicked
        const tabs = document.querySelectorAll('#adminTabs button[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(event) {
                sessionStorage.setItem('adminActiveTab', '#' + event.target.id);
            });
        });
    });
</script> 

<!-- Add JavaScript to handle the Event Filter for Race Dropdown -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filter für Race Dropdown basierend auf Event
        const eventFilter = document.getElementById('filter_event_id');
        const raceDropdown = document.getElementById('race_id');
        
        if (eventFilter && raceDropdown) {
            eventFilter.addEventListener('change', function() {
                const selectedEventId = this.value;
                const raceOptions = raceDropdown.querySelectorAll('option');
                
                // Erste Option (Placeholder) immer anzeigen
                raceDropdown.selectedIndex = 0;
                
                // Alle Optionen durchlaufen (ab Index 1, um den Placeholder zu überspringen)
                for (let i = 1; i < raceOptions.length; i++) {
                    const option = raceOptions[i];
                    const eventId = option.getAttribute('data-event-id');
                    
                    // Wenn kein Filter oder Event passt, Option anzeigen, sonst verstecken
                    if (!selectedEventId || eventId === selectedEventId) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                }
            });
        }
        
        // ... existing JavaScript ...
    });
</script> 

<script>
// Dynamische Sitzplatz-Auswahl basierend auf participants_per_boat
const raceSelect = document.getElementById('race_id');
const seatSelect = document.getElementById('boat_seat');
if (raceSelect && seatSelect) {
    raceSelect.addEventListener('change', function() {
        const raceId = this.value;
        seatSelect.innerHTML = '<option value="">Sitz wählen</option>';
        if (!raceId) return;
        fetch('get_race_participants.php?race_id=' + raceId)
            .then(response => response.json())
            .then(data => {
                const seats = data.participants_per_boat || 0;
                for (let i = 1; i <= seats; i++) {
                    const opt = document.createElement('option');
                    opt.value = i;
                    opt.textContent = i + '. Sitz';
                    seatSelect.appendChild(opt);
                }
            });
    });
}
</script> 

<script>
// Dynamisches Nachladen der Boot-Optionen nach Auswahl des Rennens
const raceSelect = document.getElementById('race_select');
const bootSelect = document.getElementById('result_rp_id');
function loadBootOptions() {
    const raceId = raceSelect.value;
    bootSelect.innerHTML = '<option value="">Select Boot</option>';
    if (!raceId) return;
    fetch('get_race_participants.php?race_id=' + raceId)
        .then(response => response.json())
        .then(data => {
            if (!data || !data.participants) return;
            // Gruppieren nach Lane und Bugnummer
            const boots = {};
            data.participants.forEach(rp => {
                const key = rp.lane + '_' + (rp.boat_number || '');
                if (!boots[key]) {
                    boots[key] = {
                        ids: [],
                        lane: rp.lane,
                        boat_number: rp.boat_number,
                        participants: [],
                        race_name: rp.race_name
                    };
                }
                boots[key].ids.push(rp.id);
                let nameWithYear = rp.participant_name;
                if (rp.birth_year) nameWithYear += ' (' + String(rp.birth_year).slice(-2) + ')';
                boots[key].participants.push(nameWithYear);
            });
            Object.values(boots).forEach(boot => {
                let label = boot.race_name + ' - Lane ' + boot.lane;
                if (boot.boat_number) label += ' - Bug ' + boot.boat_number;
                label += ' - ' + boot.participants.join(', ');
                const opt = document.createElement('option');
                opt.value = boot.ids[0];
                opt.textContent = label;
                bootSelect.appendChild(opt);
            });
        });
}
raceSelect.addEventListener('change', loadBootOptions);
document.addEventListener('DOMContentLoaded', function() {
    if (raceSelect.value) loadBootOptions();
    });
</script> 