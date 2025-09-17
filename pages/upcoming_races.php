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
    
    // Log for debugging
    error_log("Original Query: Found " . count($races) . " upcoming races");
    foreach ($races as $race) {
        error_log("Original Race: " . $race['name'] . " - Status: " . $race['status'] . " - Start: " . $race['start_time']);
    }
    
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
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h2>Upcoming Races</h2>
            <div class="d-flex align-items-center">
                <span id="lastUpdate" class="text-light me-3" style="font-size: 0.9rem;">Letzte Aktualisierung: <span id="updateTime">-</span></span>
                <button id="refreshBtn" class="btn btn-light btn-sm" onclick="refreshRaces()">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Aktualisieren
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="racesContainer">
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
    </div>

    <script>
    let refreshInterval;
    let isRefreshing = false;

    function refreshRaces() {
        if (isRefreshing) return;
        
        isRefreshing = true;
        const refreshIcon = document.getElementById('refreshIcon');
        refreshIcon.classList.add('fa-spin');
        
        fetch('get_upcoming_races.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRacesDisplay(data.races);
                    updateLastUpdateTime();
                } else {
                    console.error('Error fetching races:', data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            })
            .finally(() => {
                isRefreshing = false;
                refreshIcon.classList.remove('fa-spin');
            });
    }

    function updateRacesDisplay(racesByDate) {
        const container = document.getElementById('racesContainer');
        
        if (Object.keys(racesByDate).length === 0) {
            container.innerHTML = '<div class="alert alert-info">No upcoming races scheduled.</div>';
            return;
        }
        
        let html = '<div class="accordion" id="accordionRaces">';
        
        for (const [date, dayRaces] of Object.entries(racesByDate)) {
            const dateHash = btoa(date).replace(/[^a-zA-Z0-9]/g, '');
            const formattedDate = new Date(date).toLocaleDateString('de-DE', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            html += `
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading${dateHash}">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${dateHash}" aria-expanded="true" aria-controls="collapse${dateHash}">
                            ${formattedDate} (${dayRaces.length} races)
                        </button>
                    </h2>
                    <div id="collapse${dateHash}" class="accordion-collapse collapse show" aria-labelledby="heading${dateHash}" data-bs-parent="#accordionRaces">
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
                                    <tbody>`;
            
            dayRaces.forEach(race => {
                const startTime = new Date(race.start_time).toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
                
                html += `
                    <tr>
                        <td>${startTime}</td>
                        <td>${escapeHtml(race.name)}</td>
                        <td>${escapeHtml(race.event_name)} (${race.year})</td>
                        <td>${race.distance} m</td>
                        <td>${race.participant_count}</td>
                        <td>
                            <a href="index.php?page=upcoming_races&race_id=${race.id}" class="btn btn-sm btn-primary">View Details</a>
                        </td>
                    </tr>`;
            });
            
            html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>`;
        }
        
        html += '</div>';
        container.innerHTML = html;
    }

    function updateLastUpdateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('de-DE', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        document.getElementById('updateTime').textContent = timeString;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function startAutoRefresh() {
        // Refresh every 30 seconds
        refreshInterval = setInterval(refreshRaces, 30000);
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    // Start auto-refresh when page loads
    document.addEventListener('DOMContentLoaded', function() {
        updateLastUpdateTime();
        startAutoRefresh();
    });

    // Stop auto-refresh when page is hidden, start when visible
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });
    </script>
    <?php
}
?> 