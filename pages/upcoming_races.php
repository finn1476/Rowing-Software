<?php
$conn = getDbConnection();

// Check if a specific race is requested
if (isset($_GET['race_id'])) {
    $raceId = (int)$_GET['race_id'];
    
    // Get race details
    $stmt = $conn->prepare("
        SELECT r.*, e.name as event_name, e.event_date, y.year 
        FROM races r
        JOIN events e ON r.event_id = e.id
        JOIN years y ON e.year_id = y.id
        WHERE r.id = :race_id
    ");
    $stmt->bindParam(':race_id', $raceId);
    $stmt->execute();
    $race = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$race) {
        echo '<div class="alert alert-danger">Race not found.</div>';
    } else {
        // Get participants for this race
        $stmt = $conn->prepare("
            SELECT rp.*, t.name as team_name, p.name as participant_name
            FROM race_participants rp
            JOIN teams t ON rp.team_id = t.id
            LEFT JOIN participants p ON rp.participant_id = p.id
            WHERE rp.race_id = :race_id
            ORDER BY rp.lane ASC
        ");
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Teilnehmer nach Boot gruppieren
        $boats = [];
        foreach ($participants as $p) {
            $key = $p['lane'] . '_' . $p['boat_number'];
            if (!isset($boats[$key])) {
                $boats[$key] = [
                    'lane' => $p['lane'],
                    'boat_number' => $p['boat_number'],
                    'team_names' => [],
                    'participants' => []
                ];
            }
            if (!in_array($p['team_name'], $boats[$key]['team_names'])) {
                $boats[$key]['team_names'][] = $p['team_name'];
            }
            if (!empty($p['participant_name'])) {
                $boats[$key]['participants'][] = $p['participant_name'];
            }
        }
        
        // Display race details
        ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h2><?php echo htmlspecialchars($race['name']); ?></h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4>Race Details</h4>
                        <p><strong>Event:</strong> <?php echo htmlspecialchars($race['event_name']); ?></p>
                        <p><strong>Year:</strong> <?php echo $race['year']; ?></p>
                        <p><strong>Start Time:</strong> <?php echo date('F j, Y, g:i a', strtotime($race['start_time'])); ?></p>
                        <p><strong>Distance:</strong> <?php echo $race['distance']; ?> meters</p>
                        <p><strong>Status:</strong> <?php echo ucfirst($race['status']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h4>Participants</h4>
                        <?php if (count($boats) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Lane</th>
                                            <th>Bugnummer</th>
                                            <th>Teams</th>
                                            <th>Participants</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($boats as $boot): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($boot['lane']); ?></td>
                                                <td><?php echo htmlspecialchars($boot['boat_number']); ?></td>
                                                <td><?php echo htmlspecialchars(implode(', ', $boot['team_names'])); ?></td>
                                                <td><?php echo htmlspecialchars(implode(', ', $boot['participants'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No participants registered for this race yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="index.php?page=upcoming_races" class="btn btn-secondary">Back to Upcoming Races</a>
            </div>
        </div>
        <?php
    }
} else {
    // Get all upcoming races grouped by event
    $stmt = $conn->prepare("
        SELECT r.*, e.name as event_name, e.event_date, y.year, COUNT(rp.id) as participant_count
        FROM races r
        JOIN events e ON r.event_id = e.id
        JOIN years y ON e.year_id = y.id
        LEFT JOIN race_participants rp ON r.id = rp.race_id
        WHERE r.status = 'upcoming'
        GROUP BY r.id
        ORDER BY r.start_time ASC
    ");
    $stmt->execute();
    $races = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize races by date
    $racesByDate = [];
    foreach ($races as $race) {
        $date = date('Y-m-d', strtotime($race['start_time']));
        if (!isset($racesByDate[$date])) {
            $racesByDate[$date] = [];
        }
        $racesByDate[$date][] = $race;
    }
    
    // Display upcoming races
    ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h2>Upcoming Races</h2>
        </div>
        <div class="card-body">
            <?php if (count($racesByDate) > 0): ?>
                <div class="accordion" id="accordionRaces">
                    <?php foreach ($racesByDate as $date => $dayRaces): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo md5($date); ?>">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo md5($date); ?>" aria-expanded="true" aria-controls="collapse<?php echo md5($date); ?>">
                                    <?php echo date('l, F j, Y', strtotime($date)); ?> (<?php echo count($dayRaces); ?> races)
                                </button>
                            </h2>
                            <div id="collapse<?php echo md5($date); ?>" class="accordion-collapse collapse show" aria-labelledby="heading<?php echo md5($date); ?>" data-bs-parent="#accordionRaces">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Race</th>
                                                    <th>Event</th>
                                                    <th>Distance</th>
                                                    <th>Participants</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dayRaces as $race): ?>
                                                    <tr>
                                                        <td><?php echo date('g:i a', strtotime($race['start_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($race['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($race['event_name']); ?> (<?php echo $race['year']; ?>)</td>
                                                        <td><?php echo $race['distance']; ?> m</td>
                                                        <td><?php echo $race['participant_count']; ?></td>
                                                        <td>
                                                            <a href="index.php?page=upcoming_races&race_id=<?php echo $race['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No upcoming races scheduled.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?> 