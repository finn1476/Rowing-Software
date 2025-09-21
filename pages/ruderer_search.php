<?php
include_once 'config/database.php';
$conn = getDbConnection();

// Get all teams for dropdown
$teams = $conn->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get all years for dropdown
$years = $conn->query("SELECT DISTINCT y.year FROM years y ORDER BY y.year DESC")->fetchAll(PDO::FETCH_COLUMN);

// Get all events for dropdown
$events = $conn->query("SELECT DISTINCT e.id, e.name FROM events e ORDER BY e.name ASC")->fetchAll(PDO::FETCH_ASSOC);

$searchResults = [];
$searchPerformed = false;

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['search_name'])) {
    $searchPerformed = true;
    $searchName = trim($_POST['search_name']);
    $birthYear = !empty($_POST['birth_year']) ? (int)$_POST['birth_year'] : null;
    $teamId = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;
    $yearFilter = !empty($_POST['year_filter']) ? (int)$_POST['year_filter'] : null;
    $eventId = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    
    // Build search query
    $whereConditions = ["p.name LIKE :search_name"];
    $params = [':search_name' => '%' . $searchName . '%'];
    
    if ($birthYear) {
        $whereConditions[] = "p.birth_year = :birth_year";
        $params[':birth_year'] = $birthYear;
    }
    
    if ($teamId) {
        $whereConditions[] = "p.team_id = :team_id";
        $params[':team_id'] = $teamId;
    }
    
    if ($yearFilter) {
        $whereConditions[] = "y.year = :year_filter";
        $params[':year_filter'] = $yearFilter;
    }
    
    if ($eventId) {
        $whereConditions[] = "e.id = :event_id";
        $params[':event_id'] = $eventId;
    }
    
    $sql = "
        SELECT DISTINCT p.id, p.name, p.birth_year, t.name as team_name, t.id as team_id
        FROM participants p
        LEFT JOIN teams t ON p.team_id = t.id
        LEFT JOIN race_participants rp ON p.id = rp.participant_id
        LEFT JOIN races r ON rp.race_id = r.id
        LEFT JOIN events e ON r.event_id = e.id
        LEFT JOIN years y ON e.year_id = y.id
        WHERE " . implode(' AND ', $whereConditions) . "
        ORDER BY p.name ASC, p.id ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    
    // Group participants by name and birth year to avoid duplicates
    $groupedParticipants = [];
    if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
        echo "<script>console.log('DEBUG: Starting participant grouping...');</script>\n";
    }
    foreach ($participants as $participant) {
        $key = $participant['name'] . '_' . $participant['birth_year'];
        if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
            echo "<script>console.log('DEBUG: Processing participant: " . addslashes($participant['name']) . " (Key: " . addslashes($key) . ")');</script>\n";
        }
        if (!isset($groupedParticipants[$key])) {
            $groupedParticipants[$key] = $participant;
            $groupedParticipants[$key]['races'] = [];
            if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                echo "<script>console.log('DEBUG: Added new participant with key: " . addslashes($key) . "');</script>\n";
            }
        } else {
            if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                echo "<script>console.log('DEBUG: Skipped duplicate participant with key: " . addslashes($key) . "');</script>\n";
            }
        }
    }
    if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
        echo "<script>console.log('DEBUG: Grouping complete. Total groups: " . count($groupedParticipants) . "');</script>\n";
    }
    
    // Get races for each grouped participant (search by name and birth year)
    if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
        echo "<script>console.log('DEBUG: Starting race retrieval for " . count($groupedParticipants) . " participants...');</script>\n";
    }
    foreach ($groupedParticipants as $key => $participant) {
        if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
            echo "<script>console.log('DEBUG: Getting races for participant: " . addslashes($participant['name']) . " (Birth: " . $participant['birth_year'] . ")');</script>\n";
        }
        $raceSql = "
            SELECT r.*, e.name as event_name, e.event_date, y.year, rp.lane, rp.boat_number, 
                   rp.finish_time, rp.finish_seconds, rp.position, rp.dnf,
                   t.name as team_name, p.name as participant_name
            FROM race_participants rp
            JOIN participants p ON rp.participant_id = p.id
            JOIN races r ON rp.race_id = r.id
            JOIN events e ON r.event_id = e.id
            JOIN years y ON e.year_id = y.id
            LEFT JOIN teams t ON rp.team_id = t.id
            WHERE p.name = :participant_name AND p.birth_year = :birth_year
            ORDER BY r.start_time DESC
        ";
        
        $raceStmt = $conn->prepare($raceSql);
        $raceStmt->bindParam(':participant_name', $participant['name']);
        $raceStmt->bindParam(':birth_year', $participant['birth_year']);
        $raceStmt->execute();
        $races = $raceStmt->fetchAll(PDO::FETCH_ASSOC);
        if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
            echo "<script>console.log('DEBUG: Found " . count($races) . " races for " . addslashes($participant['name']) . "');</script>\n";
        }
        
        // Calculate positions for each race if not set (per boat, not per participant)
        if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
            echo "<script>console.log('DEBUG: Starting position calculation for " . count($races) . " races...');</script>\n";
        }
        foreach ($races as $raceIndex => $race) {
            if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                echo "<script>console.log('DEBUG: Processing race: " . addslashes($race['name']) . " (ID: " . $race['id'] . ", Current position: " . ($race['position'] ?: 'empty') . ")');</script>\n";
            }
            if (empty($race['position']) && !empty($race['finish_time'])) {
                if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                    echo "<script>console.log('DEBUG: Calculating position for race " . addslashes($race['name']) . " (Time: " . $race['finish_seconds'] . "s)');</script>\n";
                }
                // Get all boats for this specific race and calculate position per boat
                $posStmt = $conn->prepare("
                    SELECT rp.lane, rp.boat_number, rp.finish_seconds, rp.dnf
                    FROM race_participants rp
                    WHERE rp.race_id = :race_id AND rp.finish_seconds IS NOT NULL AND rp.dnf = 0
                    GROUP BY rp.lane, rp.boat_number, rp.finish_seconds, rp.dnf
                    ORDER BY rp.finish_seconds ASC
                ");
                $posStmt->bindParam(':race_id', $race['id']);
                $posStmt->execute();
                $allBoats = $posStmt->fetchAll(PDO::FETCH_ASSOC);
                if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                    echo "<script>console.log('DEBUG: Found " . count($allBoats) . " boats in race " . addslashes($race['name']) . "');</script>\n";
                }
                
                // Find position by counting how many boats have better times
                $position = 1;
                foreach ($allBoats as $boat) {
                    if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                        echo "<script>console.log('DEBUG: Comparing boat (Lane: " . $boat['lane'] . ", Time: " . $boat['finish_seconds'] . "s) with participant time: " . $race['finish_seconds'] . "s');</script>\n";
                    }
                    if ($boat['finish_seconds'] < $race['finish_seconds']) {
                        $position++;
                        if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                            echo "<script>console.log('DEBUG: Boat has better time, position now: " . $position . "');</script>\n";
                        }
                    }
                }
                $races[$raceIndex]['position'] = $position;
                if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                    echo "<script>console.log('DEBUG: FINAL - Race " . addslashes($race['name']) . " - Time: " . $race['finish_seconds'] . "s, Position: " . $position . ", Total boats: " . count($allBoats) . "');</script>\n";
                }
            } else {
                if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
                    echo "<script>console.log('DEBUG: Skipping position calculation for race " . addslashes($race['name']) . " (Position: " . ($race['position'] ?: 'empty') . ", Finish time: " . ($race['finish_time'] ?: 'empty') . ")');</script>\n";
                }
            }
        }
        
        $groupedParticipants[$key]['races'] = $races;
        if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
            echo "<script>console.log('DEBUG: Completed race processing for " . addslashes($participant['name']) . "');</script>\n";
        }
    }
    
    if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
        echo "<script>console.log('DEBUG: All race processing complete. Creating final results...');</script>\n";
    }
    $searchResults = array_values($groupedParticipants);
    if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
        echo "<script>console.log('DEBUG: Final search results created with " . count($searchResults) . " participants');</script>\n";
    }
    
}
?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h2><i class="fas fa-search"></i> Ruderer-Suche</h2>
    </div>
    <div class="card-body">
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="search_name" class="form-label">Name des Ruderers *</label>
                    <input type="text" class="form-control" id="search_name" name="search_name" 
                           value="<?= htmlspecialchars($_POST['search_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="birth_year" class="form-label">Geburtsjahr</label>
                    <input type="number" class="form-control" id="birth_year" name="birth_year" 
                           value="<?= htmlspecialchars($_POST['birth_year'] ?? '') ?>" 
                           min="1900" max="2030" placeholder="z.B. 1995">
                </div>
                <div class="col-md-3">
                    <label for="team_id" class="form-label">Verein</label>
                    <select class="form-select" id="team_id" name="team_id">
                        <option value="">Alle Vereine</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?= $team['id'] ?>" <?= (isset($_POST['team_id']) && $_POST['team_id'] == $team['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($team['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year_filter" class="form-label">Jahr</label>
                    <select class="form-select" id="year_filter" name="year_filter">
                        <option value="">Alle Jahre</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year ?>" <?= (isset($_POST['year_filter']) && $_POST['year_filter'] == $year) ? 'selected' : '' ?>>
                                <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label for="event_id" class="form-label">Event</label>
                    <select class="form-select" id="event_id" name="event_id">
                        <option value="">Alle Events</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?= $event['id'] ?>" <?= (isset($_POST['event_id']) && $_POST['event_id'] == $event['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Suchen
                    </button>
                    <a href="index.php?page=ruderer_search" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Zurücksetzen
                    </a>
                </div>
            </div>
        </form>

        <?php if ($searchPerformed): ?>
            <?php if (empty($searchResults)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Keine Ruderer gefunden, die den Suchkriterien entsprechen.
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= count($searchResults) ?> Ruderer gefunden.
                </div>
                
                <?php foreach ($searchResults as $i => $participant): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($participant['name']) ?>
                                <?php if ($participant['birth_year']): ?>
                                    <span class="badge bg-secondary ms-2"><?= $participant['birth_year'] ?></span>
                                <?php endif; ?>
                                <?php if ($participant['team_name']): ?>
                                    <span class="badge bg-info ms-2"><?= htmlspecialchars($participant['team_name']) ?></span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($participant['races'])): ?>
                                <p class="text-muted">Keine Rennen gefunden.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Datum</th>
                                                <th>Rennen</th>
                                                <th>Event</th>
                                                <th>Jahr</th>
                                                <th>Lane</th>
                                                <th>Bugnummer</th>
                                                <th>Status</th>
                                                <th>Zeit</th>
                                                <th>Platz</th>
                                                <th>Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($participant['races'] as $i => $race): ?>
                                                <tr>
                                                    <td><?= date('d.m.Y', strtotime($race['start_time'])) ?></td>
                                                    <td>
                                                        <a href="index.php?page=<?= $race['status'] == 'completed' ? 'results' : 'upcoming_races' ?>&race_id=<?= $race['id'] ?>" 
                                                           class="text-decoration-none fw-bold">
                                                            <?= htmlspecialchars($race['name']) ?>
                                                        </a>
                                                        <?php if (isset($_GET['debug']) && $_GET['debug'] == 'true'): ?>
                                                            <small class="text-muted">(DEBUG: Race #<?= $i+1 ?>, ID=<?= $race['id'] ?>)</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($race['event_name']) ?></td>
                                                    <td><?= $race['year'] ?></td>
                                                    <td><?= $race['lane'] ?></td>
                                                    <td><?= htmlspecialchars($race['boat_number'] ?: '-') ?></td>
                                                    <td>
                                                        <?php if ($race['dnf']): ?>
                                                            <span class="badge bg-danger">DNF</span>
                                                        <?php elseif ($race['finish_time']): ?>
                                                            <span class="badge bg-success">Fertig</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Ausstehend</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($race['finish_time']): ?>
                                                            <?= $race['finish_time'] ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($race['position']) && $race['position'] != '0'): ?>
                                                            <?= $race['position'] ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="index.php?page=<?= $race['status'] == 'completed' ? 'results' : 'upcoming_races' ?>&race_id=<?= $race['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Rennen-Details anzeigen">
                                                            <i class="bi bi-eye"></i> Details
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Geben Sie den Namen eines Ruderers ein, um nach seinen Rennen zu suchen.
            </div>
        <?php endif; ?>
    </div>
</div>
