<?php
// Get upcoming races
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT r.*, e.name as event_name, COUNT(rp.id) as participant_count
    FROM races r
    JOIN events e ON r.event_id = e.id
    LEFT JOIN race_participants rp ON r.id = rp.race_id
    WHERE r.status = 'upcoming'
    GROUP BY r.id
    ORDER BY r.start_time ASC
    LIMIT 5
");
$stmt->execute();
$upcomingRaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest results
$stmt = $conn->prepare("
    SELECT r.*, e.name as event_name, COUNT(rp.id) as participant_count
    FROM races r
    JOIN events e ON r.event_id = e.id
    LEFT JOIN race_participants rp ON r.id = rp.race_id
    WHERE r.status = 'completed'
    GROUP BY r.id
    ORDER BY r.start_time DESC
    LIMIT 5
");
$stmt->execute();
$latestResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-4 p-md-5 mb-4 text-white rounded bg-dark">
    <div class="col-md-12 px-0">
        <h1 class="display-4 font-italic">Welcome to the Rowing Regatta Management System</h1>
        <p class="lead my-3">Track upcoming races, view results, and explore historical data from past events.</p>
    </div>
</div>

<div class="row mb-2">
    <div class="col-md-6">
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h2>Upcoming Races</h2>
                <?php if (count($upcomingRaces) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($upcomingRaces as $race): ?>
                            <a href="index.php?page=upcoming_races&race_id=<?php echo $race['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($race['name']); ?></h5>
                                    <small><?php echo date('M d, H:i', strtotime($race['start_time'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($race['event_name']); ?></p>
                                <small>Distance: <?php echo $race['distance']; ?>m | Participants: <?php echo $race['participant_count']; ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <a href="index.php?page=upcoming_races" class="btn btn-primary">View All Upcoming Races</a>
                    </div>
                <?php else: ?>
                    <p>No upcoming races scheduled.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h2>Latest Results</h2>
                <?php if (count($latestResults) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($latestResults as $result): ?>
                            <a href="index.php?page=results&race_id=<?php echo $result['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($result['name']); ?></h5>
                                    <small><?php echo date('M d, H:i', strtotime($result['start_time'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($result['event_name']); ?></p>
                                <small>Distance: <?php echo $result['distance']; ?>m | Participants: <?php echo $result['participant_count']; ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <p>No race results available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h2>Historical Data</h2>
                <p>Browse through our archives of rowing regatta data from previous years.</p>
                <a href="index.php?page=historical_data" class="btn btn-primary">Explore Historical Data</a>
            </div>
        </div>
    </div>
</div> 
