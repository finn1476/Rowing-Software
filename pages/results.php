<?php
$conn = getDbConnection();

// Get filter parameters
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : null;
$eventFilter = isset($_GET['event']) ? (int)$_GET['event'] : null;

// Get all available years first
$yearsStmt = $conn->prepare("
    SELECT DISTINCT y.id, y.year
    FROM years y
    JOIN events e ON y.id = e.year_id
    JOIN races r ON e.id = r.event_id
    WHERE r.status = 'completed'
    ORDER BY y.year DESC
");
$yearsStmt->execute();
$years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);

// If no year filter but years exist, use the most recent
if (!$yearFilter && count($years) > 0) {
    $yearFilter = $years[0]['id'];
}

// Get events for the selected year
$eventsQuery = "
    SELECT DISTINCT e.id, e.name, e.event_date
    FROM events e
    JOIN races r ON e.id = r.event_id
    WHERE r.status = 'completed'
";

if ($yearFilter) {
    $eventsQuery .= " AND e.year_id = :year_id";
}

$eventsQuery .= " ORDER BY e.event_date DESC";

$eventsStmt = $conn->prepare($eventsQuery);

if ($yearFilter) {
    $eventsStmt->bindParam(':year_id', $yearFilter);
}

$eventsStmt->execute();
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// If no event filter but events exist, use the most recent
if (!$eventFilter && count($events) > 0) {
    $eventFilter = $events[0]['id'];
}

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
        // Get results for this race
        $stmt = $conn->prepare("
            SELECT rp.*, t.name as team_name,
            p.name as participant_name, p.birth_year
            FROM race_participants rp
            JOIN teams t ON rp.team_id = t.id
            JOIN participants p ON rp.participant_id = p.id
            WHERE rp.race_id = :race_id
            ORDER BY rp.position ASC, rp.finish_time ASC
        ");
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Gruppieren nach Boot (race_id, lane, boat_number)
        $groupedBoats = [];
        foreach ($results as $rp) {
            $key = $rp['race_id'] . '_' . $rp['lane'] . '_' . ($rp['boat_number'] ?? '');
            if (!isset($groupedBoats[$key])) {
                $groupedBoats[$key] = [
                    'ids' => [],
                    'team_names' => [],
                    'participants' => [],
                    'lane' => $rp['lane'],
                    'position' => $rp['position'],
                    'finish_time' => $rp['finish_time'],
                    'boat_number' => $rp['boat_number'],
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
        
        // Get distance markers
        $distanceMarkers = !empty($race['distance_markers']) ? 
                           array_map('intval', explode(',', $race['distance_markers'])) : 
                           [];
                           
        // If no distance markers but we have distance times, get unique distances as markers
        if (empty($distanceMarkers)) {
            $distanceStmt = $conn->prepare("
                SELECT DISTINCT dt.distance
                FROM distance_times dt
                JOIN race_participants rp ON dt.race_participant_id = rp.id
                WHERE rp.race_id = :race_id
                ORDER BY dt.distance
            ");
            $distanceStmt->bindParam(':race_id', $raceId);
            $distanceStmt->execute();
            $distanceMarkers = $distanceStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Get distance times for all participants
        $distanceTimesStmt = $conn->prepare("
            SELECT dt.*, rp.id as participant_id, rp.team_id, rp.lane, rp.race_id, rp.boat_number
            FROM distance_times dt
            JOIN race_participants rp ON dt.race_participant_id = rp.id
            WHERE rp.race_id = :race_id
            ORDER BY dt.distance, rp.position ASC, rp.lane ASC
        ");
        $distanceTimesStmt->bindParam(':race_id', $raceId);
        $distanceTimesStmt->execute();
        $allDistanceTimes = $distanceTimesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Gruppierung für Distance Times und Split Times nach Boot
        $distanceTimesByBoot = [];
        foreach ($allDistanceTimes as $dt) {
            $key = $dt['participant_id'];
            $bootKey = $dt['race_id'] . '_' . $dt['lane'] . '_' . ($dt['boat_number'] ?? '');
            if (!isset($distanceTimesByBoot[$bootKey])) {
                $distanceTimesByBoot[$bootKey] = [
                    'participant_ids' => [],
                    'lane' => $dt['lane'],
                    'race_id' => $dt['race_id'],
                    'boat_number' => $dt['boat_number'],
                    'distance_times' => [],
                ];
            }
            $distanceTimesByBoot[$bootKey]['participant_ids'][] = $dt['participant_id'];
            $distanceTimesByBoot[$bootKey]['distance_times'][$dt['distance']][] = $dt;
        }
        // Mapping von BootKey auf Teilnehmernamen
        $bootParticipants = [];
        foreach ($groupedBoats as $key => $boot) {
            $bootParticipants[$key] = $boot['participants'];
        }
        
        // Display race results
        ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h2>Race Results: <?php echo htmlspecialchars($race['name']); ?></h2>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h4>Race Details</h4>
                        <p><strong>Event:</strong> <?php echo htmlspecialchars($race['event_name']); ?></p>
                        <p><strong>Year:</strong> <?php echo $race['year']; ?></p>
                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($race['start_time'])); ?></p>
                        <p><strong>Start Time:</strong> <?php echo date('g:i a', strtotime($race['start_time'])); ?></p>
                        <p><strong>Distance:</strong> <?php echo $race['distance']; ?> meters</p>
                        <?php if (!empty($race['distance_markers'])): ?>
                            <p><strong>Distance Markers:</strong> <?php echo $race['distance_markers']; ?> meters</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Actions
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="printResults()">Ergebnisse drucken</a></li>
                                <?php if (count($results) > 0 && $results[0]['position'] == 1): ?>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="printCertificate(<?php echo $results[0]['id']; ?>)">Siegerurkunde ansehen</a></li>
                                <li><a class="dropdown-item" href="../generate_certificate.php?id=<?php echo $results[0]['id']; ?>&download=pdf">Siegerurkunde als PDF</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#printAllCertificatesModal">Alle Urkunden drucken</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <h4>Results</h4>
                <?php
                // Positionen nach Finish Time berechnen (schnellste Zeit = 1)
                $finishTimesForSort = [];
                foreach ($groupedBoats as $key => $boat) {
                    $finishSeconds = array_map(function($id) use ($results) {
                        foreach ($results as $r) if ($r['id'] == $id) return $r['finish_seconds'];
                        return null;
                    }, $boat['ids']);
                    $finishSeconds = array_filter($finishSeconds);
                    $firstFinish = !empty($finishSeconds) ? reset($finishSeconds) : null;
                    $finishTimesForSort[$key] = $firstFinish;
                }
                asort($finishTimesForSort);
                $positionMap = [];
                $pos = 1;
                foreach ($finishTimesForSort as $key => $val) {
                    if ($val !== null) {
                        $positionMap[$key] = $pos++;
                    } else {
                        $positionMap[$key] = null;
                    }
                }
                ?>
                <?php if (count($groupedBoats) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Lane</th>
                                    <th>Team</th>
                                    <th>Teilnehmer</th>
                                    <th>Finish Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedBoats as $key => $boat): ?>
                                    <tr <?php echo (isset($positionMap[$key]) && $positionMap[$key] <= 3) ? 'class="table-success"' : ''; ?>>
                                        <td>
                                            <?php 
                                            $pos = $positionMap[$key];
                                            if ($pos == 1) {
                                                echo '<strong class="text-success">1. 🥇</strong>';
                                            } elseif ($pos == 2) {
                                                echo '<strong class="text-secondary">2. 🥈</strong>';
                                            } elseif ($pos == 3) {
                                                echo '<strong class="text-warning">3. 🥉</strong>';
                                            } elseif ($pos) {
                                                echo $pos . '.';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $boat['lane']; ?></td>
                                        <td><?php echo implode(', ', array_unique($boat['team_names'])); ?></td>
                                        <td><?php echo implode(', ', $boat['participants']); ?></td>
                                        <td><?php 
                                            // Zeige die erste finish_seconds-Zeit als formatRaceTime an (wie Debug)
                                            $finishSeconds = array_map(function($id) use ($results) {
                                                foreach ($results as $r) if ($r['id'] == $id) return $r['finish_seconds'];
                                                return null;
                                            }, $boat['ids']);
                                            $finishSeconds = array_filter($finishSeconds);
                                            if (!empty($finishSeconds)) {
                                                $firstFinish = reset($finishSeconds);
                                                echo formatRaceTime($firstFinish);
                                            } else {
                                                echo 'DNF';
                                            }
                                        ?></td>
                                        <td>
                                            <?php if (!empty($boat['ids'])): ?>
                                                <div class="dropdown-center">
                                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-trophy"></i> Urkunden
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0);" onclick="printBootCertificate(<?php echo $boat['ids'][0]; ?>)">
                                                            <b>Alle Urkunden für dieses Boot drucken</b>
                                                        </a>
                                                    </li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No results available for this race yet.</div>
                <?php endif; ?>
                
                <?php if (!empty($distanceMarkers) && !empty($allDistanceTimes)): ?>
                    <h4 class="mt-4">Incremental Distance Times</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Lane</th>
                                    <th>Teilnehmer</th>
                                    <?php foreach ($distanceMarkers as $marker): ?>
                                        <th><?php echo $marker; ?>m</th>
                                    <?php endforeach; ?>
                                    <th>Finish</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedBoats as $bootKey => $boot): ?>
                                    <tr>
                                        <td><?php echo $boot['lane']; ?></td>
                                        <td><?php echo implode(', ', $boot['participants']); ?></td>
                                        <?php foreach ($distanceMarkers as $marker): ?>
                                            <td>
                                                <?php 
                                                $dtList = $distanceTimesByBoot[$bootKey]['distance_times'][$marker] ?? [];
                                                // Zeige die beste (schnellste) Zeit des Boots an
                                                if (!empty($dtList)) {
                                                    $best = min(array_map(function($dt){ return $dt['seconds_elapsed']; }, $dtList));
                                                    echo formatRaceTime($best);
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td><?php 
                                            $finishSeconds = array_map(function($id) use ($results) {
                                                foreach ($results as $r) if ($r['id'] == $id) return $r['finish_seconds'];
                                                return null;
                                            }, $boot['ids']);
                                            $finishSeconds = array_filter($finishSeconds);
                                            if (!empty($finishSeconds)) {
                                                $firstFinish = reset($finishSeconds);
                                                echo formatRaceTime($firstFinish);
                                            } else {
                                                echo 'DNF';
                                            }
                                        ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php $showDebug = isset($_GET['debug']) && $_GET['debug'] == 1; ?>
                    <?php if ($showDebug): ?>
                    <h5>Debug: Distance Times pro Boot und Distanz</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>BootKey</th>
                                    <th>Distanz</th>
                                    <th>distance_time ID</th>
                                    <th>time</th>
                                    <th>seconds_elapsed</th>
                                    <th>participant_id</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($distanceTimesByBoot as $bootKey => $boot):
                                foreach ($boot['distance_times'] as $dist => $dtList):
                                    foreach ($dtList as $dt): ?>
                                <tr>
                                    <td><?php echo $bootKey; ?></td>
                                    <td><?php echo $dist; ?></td>
                                    <td><?php echo $dt['id']; ?></td>
                                    <td><?php echo $dt['time']; ?></td>
                                    <td><?php echo $dt['seconds_elapsed']; ?></td>
                                    <td><?php echo $dt['participant_id']; ?></td>
                                </tr>
                            <?php endforeach; endforeach; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($distanceMarkers) && !empty($allDistanceTimes)): ?>
                    <h4 class="mt-4">Split Times</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Lane</th>
                                    <th>Teilnehmer</th>
                                    <?php 
                                    $prevMarker = 0;
                                    foreach ($distanceMarkers as $marker): 
                                        $interval = $marker - $prevMarker;
                                        $prevMarker = $marker;
                                    ?>
                                        <th><?php echo $prevMarker > 0 ? "{$interval}m ($marker)" : $marker."m"; ?></th>
                                    <?php endforeach; ?>
                                    <th>Final <?php echo $race['distance'] - $prevMarker; ?>m</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedBoats as $bootKey => $boot): ?>
                                    <tr>
                                        <td><?php echo $boot['lane']; ?></td>
                                        <td><?php echo implode(', ', $boot['participants']); ?></td>
                                        <?php 
                                        $prevTime = 0;
                                        $prevMarker = 0;
                                        foreach ($distanceMarkers as $marker): 
                                            $dtList = $distanceTimesByBoot[$bootKey]['distance_times'][$marker] ?? [];
                                            $splitTime = null;
                                            if (!empty($dtList)) {
                                                $best = min(array_map(function($dt){ return $dt['seconds_elapsed']; }, $dtList));
                                                $splitTime = $best - $prevTime;
                                                $prevTime = $best;
                                            }
                                            $prevMarker = $marker;
                                        ?>
                                            <td>
                                                <?php 
                                                if ($splitTime !== null) {
                                                    echo formatRaceTime(gmdate("H:i:s", $splitTime));
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <?php 
                                            // Final Split: Finish Time - letzter Marker
                                            $finishSeconds = array_map(function($id) use ($results) {
                                                foreach ($results as $r) if ($r['id'] == $id) return $r['finish_seconds'];
                                                return null;
                                            }, $boot['ids']);
                                            $finishSeconds = array_filter($finishSeconds);
                                            if (!empty($finishSeconds) && $prevTime > 0) {
                                                $bestFinish = min($finishSeconds);
                                                $finalSplit = $bestFinish - $prevTime;
                                                echo formatRaceTime(gmdate("H:i:s", $finalSplit));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <?php if ($showDebug): ?>
                <h4>Debug: Rohdaten aus der Datenbank</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>BootKey</th>
                                <th>Finish Times (finish_time)</th>
                                <th>Finish Seconds (finish_seconds)</th>
                                <th>Distance Times (time)</th>
                                <th>Distance Seconds (seconds_elapsed)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($groupedBoats as $bootKey => $boot): ?>
                            <tr>
                                <td><?php echo $bootKey; ?></td>
                                <td>
                                    <?php foreach ($boot['ids'] as $id) {
                                        foreach ($results as $r) if ($r['id'] == $id) echo $r['finish_time'] . '<br>';
                                    } ?>
                                </td>
                                <td>
                                    <?php 
                                    $finishSeconds = array_map(function($id) use ($results) {
                                        foreach ($results as $r) if ($r['id'] == $id) return $r['finish_seconds'];
                                        return null;
                                    }, $boot['ids']);
                                    $finishSeconds = array_filter($finishSeconds);
                                    echo 'finish_seconds: [' . implode(', ', $finishSeconds) . ']';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (isset($distanceTimesByBoot[$bootKey])) {
                                        foreach ($distanceTimesByBoot[$bootKey]['distance_times'] as $dist => $dtList) {
                                            echo $dist . 'm: ';
                                            foreach ($dtList as $dt) echo $dt['time'] . ' | ';
                                            echo '<br>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (isset($distanceTimesByBoot[$bootKey])) {
                                        foreach ($distanceTimesByBoot[$bootKey]['distance_times'] as $dist => $dtList) {
                                            echo $dist . 'm: ';
                                            foreach ($dtList as $dt) echo $dt['seconds_elapsed'] . ' | ';
                                            echo '<br>';
                                        }
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        
        <!-- Modal for printing all certificates -->
        <div class="modal fade" id="printAllCertificatesModal" tabindex="-1" aria-labelledby="printAllCertificatesModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="printAllCertificatesModalLabel">Urkunden drucken</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Wählen Sie aus, für welche Teilnehmer Urkunden gedruckt werden sollen:</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="certificateOption" id="certificateTop3" value="top3" checked>
                            <label class="form-check-label" for="certificateTop3">
                                Top 3 Platzierungen (Podium)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="certificateOption" id="certificateAll" value="all">
                            <label class="form-check-label" for="certificateAll">
                                Alle Teilnehmer mit Position
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="certificateOption" id="certificateCustom" value="custom">
                            <label class="form-check-label" for="certificateCustom">
                                Benutzerdefinierte Auswahl
                            </label>
                        </div>
                        
                        <div id="customCertificateSelection" class="mt-3" style="display: none;">
                            <?php foreach ($results as $result): ?>
                                <?php if ($result['position']): ?>
                                <div class="form-check">
                                    <input class="form-check-input cert-participant" type="checkbox" value="<?php echo $result['id']; ?>" id="cert<?php echo $result['id']; ?>">
                                    <label class="form-check-label" for="cert<?php echo $result['id']; ?>">
                                        <?php echo "Position {$result['position']}: " . htmlspecialchars($result['team_name']); ?>
                                    </label>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                        <button type="button" class="btn btn-primary" id="printSelectedCertificates">Urkunden drucken</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            // Function to print the results page
            function printResults() {
                window.print();
            }
            
            // Show/hide custom selection based on radio choice
            document.querySelectorAll('input[name="certificateOption"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const customSection = document.getElementById('customCertificateSelection');
                    customSection.style.display = this.value === 'custom' ? 'block' : 'none';
                });
            });
            
            // Handle certificate printing button
            document.getElementById('printSelectedCertificates').addEventListener('click', function() {
                const selectedOption = document.querySelector('input[name="certificateOption"]:checked').value;
                let participantIds = [];
                
                if (selectedOption === 'top3') {
                    // Get top 3 finishers
                    <?php
                    $top3 = array_filter($results, function($r) {
                        return $r['position'] && $r['position'] <= 3;
                    });
                    $top3Ids = array_column($top3, 'id');
                    echo "participantIds = " . json_encode($top3Ids) . ";";
                    ?>
                } else if (selectedOption === 'all') {
                    // Get all participants with positions
                    <?php
                    $withPositions = array_filter($results, function($r) {
                        return $r['position'];
                    });
                    $positionIds = array_column($withPositions, 'id');
                    echo "participantIds = " . json_encode($positionIds) . ";";
                    ?>
                } else if (selectedOption === 'custom') {
                    // Get selected custom participants
                    document.querySelectorAll('.cert-participant:checked').forEach(checkbox => {
                        participantIds.push(checkbox.value);
                    });
                }
                
                // Open certificate pages in new tabs
                participantIds.forEach(id => {
                    printCertificate(id);
                });
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('printAllCertificatesModal'));
                modal.hide();
            });

            function printCertificate(id) {
                console.log("Öffne Urkunde für ID:", id);
                window.open(`../generate_certificate.php?id=${id}`, `_blank_${id}`);
            }

            function printBootCertificate(id) {
                window.open(`../generate_certificate.php?id=${id}&boot=1`, `_blank_boot_${id}`);
            }
        </script>
        <?php
    }
} else {
    // Get completed races
    $racesQuery = "
        SELECT r.*, e.name as event_name, e.event_date, y.year, COUNT(rp.id) as participant_count
        FROM races r
        JOIN events e ON r.event_id = e.id
        JOIN years y ON e.year_id = y.id
        LEFT JOIN race_participants rp ON r.id = rp.race_id
        WHERE r.status = 'completed'
    ";
    
    if ($yearFilter) {
        $racesQuery .= " AND y.id = :year_id";
    }
    
    if ($eventFilter) {
        $racesQuery .= " AND e.id = :event_id";
    }
    
    $racesQuery .= " GROUP BY r.id ORDER BY r.start_time DESC";
    
    $stmt = $conn->prepare($racesQuery);
    
    if ($yearFilter) {
        $stmt->bindParam(':year_id', $yearFilter);
    }
    
    if ($eventFilter) {
        $stmt->bindParam(':event_id', $eventFilter);
    }
    
    $stmt->execute();
    $races = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize races by event
    $racesByEvent = [];
    foreach ($races as $race) {
        $eventName = $race['event_name'];
        if (!isset($racesByEvent[$eventName])) {
            $racesByEvent[$eventName] = [
                'event_name' => $eventName,
                'event_date' => $race['event_date'],
                'year' => $race['year'],
                'races' => []
            ];
        }
        $racesByEvent[$eventName]['races'][] = $race;
    }
    
    // Display races results by year
    ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h2>Race Results</h2>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <form method="get" action="index.php" class="row g-3">
                    <input type="hidden" name="page" value="results">
                    <div class="col-auto">
                        <label for="yearFilter" class="col-form-label">Jahr:</label>
                    </div>
                    <div class="col-auto">
                        <select name="year" id="yearFilter" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year['id']; ?>" <?php echo ($yearFilter == $year['id']) ? 'selected' : ''; ?>>
                                    <?php echo $year['year']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-auto">
                        <label for="eventFilter" class="col-form-label">Veranstaltung:</label>
                    </div>
                    <div class="col-auto">
                        <select name="event" id="eventFilter" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>" <?php echo ($eventFilter == $event['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['name']) . ' (' . date('d.m.Y', strtotime($event['event_date'])) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if (count($racesByEvent) > 0): ?>
                <div class="accordion" id="accordionResults">
                    <?php foreach ($racesByEvent as $eventKey => $event): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo md5($eventKey); ?>">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo md5($eventKey); ?>" aria-expanded="true" aria-controls="collapse<?php echo md5($eventKey); ?>">
                                    <?php echo htmlspecialchars($event['event_name']); ?> (<?php echo date('d.m.Y', strtotime($event['event_date'])); ?>)
                                </button>
                            </h2>
                            <div id="collapse<?php echo md5($eventKey); ?>" class="accordion-collapse collapse show" aria-labelledby="heading<?php echo md5($eventKey); ?>" data-bs-parent="#accordionResults">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Race</th>
                                                    <th>Time</th>
                                                    <th>Distance</th>
                                                    <th>Teams</th>
                                                    <th>Winner</th>
                                                    <th>Teilnehmer</th>
                                                    <th>Jg.</th>
                                                    <th>Winning Time</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($event['races'] as $race): 
                                                    // Get the winner
                                                    $winnerStmt = $conn->prepare("
                                                        SELECT rp.*, t.name as team_name,
                                                        p.name as participant_name, p.birth_year
                                                        FROM race_participants rp
                                                        JOIN teams t ON rp.team_id = t.id
                                                        JOIN participants p ON rp.participant_id = p.id
                                                        WHERE rp.race_id = :race_id AND rp.position = 1
                                                        LIMIT 1
                                                    ");
                                                    $winnerStmt->bindParam(':race_id', $race['id']);
                                                    $winnerStmt->execute();
                                                    $winner = $winnerStmt->fetch(PDO::FETCH_ASSOC);
                                                    
                                                    $birthYearShort = $winner && $winner['birth_year'] ? substr($winner['birth_year'], -2) : '';
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($race['name']); ?></td>
                                                        <td><?php echo date('H:i', strtotime($race['start_time'])); ?></td>
                                                        <td><?php echo $race['distance']; ?> m</td>
                                                        <td><?php echo $race['participant_count']; ?></td>
                                                        <td>
                                                            <?php if ($winner): ?>
                                                                <strong><?php echo htmlspecialchars($winner['team_name']); ?></strong>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($winner): ?>
                                                                <?php echo htmlspecialchars($winner['participant_name']); ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $birthYearShort; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($winner && $winner['finish_time']): ?>
                                                                <?php echo $winner['finish_time']; ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="winner-actions">
                                                                <a href="javascript:void(0);" onclick="printCertificate(<?php echo $winner['id']; ?>)" class="btn btn-sm btn-outline-success">
                                                                    <i class="bi bi-trophy"></i> Urkunde
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
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No race results available for this selection.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?> 
