<?php
$conn = getDbConnection();

// Get all years
$stmt = $conn->prepare("
    SELECT y.*, 
           COUNT(DISTINCT e.id) as event_count,
           COUNT(DISTINCT r.id) as race_count,
           (SELECT COUNT(*) FROM race_participants rp 
            JOIN races r2 ON rp.race_id = r2.id 
            JOIN events e2 ON r2.event_id = e2.id 
            WHERE e2.year_id = y.id) as participant_count
    FROM years y
    LEFT JOIN events e ON y.id = e.year_id
    LEFT JOIN races r ON e.id = r.event_id
    GROUP BY y.id
    ORDER BY y.year DESC
");
$stmt->execute();
$years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if year details are requested
if (isset($_GET['year_id'])) {
    $yearId = (int)$_GET['year_id'];
    
    // Get year details
    $stmt = $conn->prepare("SELECT * FROM years WHERE id = :year_id");
    $stmt->bindParam(':year_id', $yearId);
    $stmt->execute();
    $year = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$year) {
        echo '<div class="alert alert-danger">Year not found.</div>';
    } else {
        // Get events for this year
        $stmt = $conn->prepare("
            SELECT e.*, 
                   COUNT(DISTINCT r.id) as race_count,
                   (SELECT COUNT(*) FROM race_participants rp 
                    JOIN races r2 ON rp.race_id = r2.id 
                    WHERE r2.event_id = e.id) as participant_count
            FROM events e
            LEFT JOIN races r ON e.id = r.event_id
            WHERE e.year_id = :year_id
            GROUP BY e.id
            ORDER BY e.event_date ASC
        ");
        $stmt->bindParam(':year_id', $yearId);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get statistics for this year
        $statsStmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT t.id) as team_count,
                COUNT(DISTINCT r.id) as race_count,
                SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_races,
                COUNT(DISTINCT rp.id) as participant_entries
            FROM years y
            LEFT JOIN events e ON y.id = e.year_id
            LEFT JOIN races r ON e.id = r.event_id
            LEFT JOIN race_participants rp ON r.id = rp.race_id
            LEFT JOIN teams t ON rp.team_id = t.id
            WHERE y.id = :year_id
        ");
        $statsStmt->bindParam(':year_id', $yearId);
        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Display year details
        ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><?php echo htmlspecialchars($year['name']); ?> (<?php echo $year['year']; ?>)</h2>
                    <a href="index.php?page=historical_data" class="btn btn-sm btn-light">Back to All Years</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($year['description']): ?>
                    <div class="mb-4">
                        <h4>Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($year['description'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h4>Year Statistics</h4>
                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Events</h5>
                                        <p class="card-text display-4"><?php echo count($events); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Teams</h5>
                                        <p class="card-text display-4"><?php echo $stats['team_count']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Races</h5>
                                        <p class="card-text display-4"><?php echo $stats['race_count']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Participants</h5>
                                        <p class="card-text display-4"><?php echo $stats['participant_entries']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4>Events</h4>
                <?php if (count($events) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Date</th>
                                    <th>Races</th>
                                    <th>Participants</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['name']); ?></td>
                                        <td><?php echo date('F j, Y', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo $event['race_count']; ?></td>
                                        <td><?php echo $event['participant_count']; ?></td>
                                        <td>
                                            <a href="index.php?page=historical_data&year_id=<?php echo $yearId; ?>&event_id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No events found for this year.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        // Check if event details are requested
        if (isset($_GET['event_id'])) {
            $eventId = (int)$_GET['event_id'];
            
            // Get event details
            $stmt = $conn->prepare("SELECT * FROM events WHERE id = :event_id AND year_id = :year_id");
            $stmt->bindParam(':event_id', $eventId);
            $stmt->bindParam(':year_id', $yearId);
            $stmt->execute();
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                echo '<div class="alert alert-danger">Event not found.</div>';
            } else {
                // Get races for this event
                $stmt = $conn->prepare("
                    SELECT r.*, 
                           (SELECT COUNT(*) FROM race_participants rp WHERE rp.race_id = r.id) as participant_count
                    FROM races r
                    WHERE r.event_id = :event_id
                    ORDER BY r.start_time ASC
                ");
                $stmt->bindParam(':event_id', $eventId);
                $stmt->execute();
                $races = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Display event details
                ?>
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h3><?php echo htmlspecialchars($event['name']); ?></h3>
                        <p class="mb-0">Date: <?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                    </div>
                    <div class="card-body">
                        <?php if ($event['description']): ?>
                            <div class="mb-4">
                                <h4>Description</h4>
                                <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <h4>Races</h4>
                        <?php if (count($races) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Race Name</th>
                                            <th>Start Time</th>
                                            <th>Distance</th>
                                            <th>Status</th>
                                            <th>Participants</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($races as $race): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($race['name']); ?></td>
                                                <td><?php echo date('g:i a', strtotime($race['start_time'])); ?></td>
                                                <td><?php echo $race['distance']; ?> m</td>
                                                <td>
                                                    <?php if ($race['status'] == 'upcoming'): ?>
                                                        <span class="badge bg-primary">Upcoming</span>
                                                    <?php elseif ($race['status'] == 'started'): ?>
                                                        <span class="badge bg-warning text-dark">Started</span>
                                                    <?php elseif ($race['status'] == 'completed'): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Cancelled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $race['participant_count']; ?></td>
                                                <td>
                                                    <?php if ($race['status'] == 'upcoming'): ?>
                                                        <a href="index.php?page=upcoming_races&race_id=<?php echo $race['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                                    <?php elseif ($race['status'] == 'completed'): ?>
                                                        <a href="index.php?page=results&race_id=<?php echo $race['id']; ?>" class="btn btn-sm btn-success">View Results</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No races found for this event.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        }
    }
} else {
    // Display list of all years with statistics
    ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h2>Historical Data</h2>
        </div>
        <div class="card-body">
            <p class="lead">Browse through our archives of rowing regatta data from previous years.</p>
            
            <?php if (count($years) > 0): ?>
                <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                    <?php foreach ($years as $year): ?>
                        <div class="col">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($year['name']); ?> (<?php echo $year['year']; ?>)</h5>
                                    <div class="card-text">
                                        <div class="d-flex justify-content-between mb-2">
                                            <small class="text-muted">Events:</small>
                                            <span class="badge bg-primary rounded-pill"><?php echo $year['event_count']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <small class="text-muted">Races:</small>
                                            <span class="badge bg-warning text-dark rounded-pill"><?php echo $year['race_count']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Participants:</small>
                                            <span class="badge bg-info text-dark rounded-pill"><?php echo $year['participant_count']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <a href="index.php?page=historical_data&year_id=<?php echo $year['id']; ?>" class="btn btn-sm btn-primary w-100">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No historical data available yet.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?> 