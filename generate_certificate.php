<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
// Include database connection
include_once 'config/database.php';

// Check if participant ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Race participant ID is required");
}

// Get participant ID
$participantId = (int)$_GET['id'];

// Check if PDF download is requested
$downloadAsPdf = isset($_GET['download']) && $_GET['download'] === 'pdf';

// Prüfe, ob Boot-Urkunde gewünscht ist
$isBootCertificate = isset($_GET['boot']) && $_GET['boot'] == 1;
$bootParticipants = [];

// Einheitliches Farbschema für Rudersport
$colors = [
    'primary' => '#00386B',     // Dunkelblau - typisch für Wassersport
    'secondary' => '#0074B7',   // Helles Blau
    'background' => '#f0f7ff',  // Sehr helles Blau
    'accent' => '#E5F6FF',      // Noch helleres Blau
    'border' => '#00386B'       // Dunkelblau für Ränder
];

try {
    $conn = getDbConnection();
    
    // Get participant details with race info
    $stmt = $conn->prepare("
        SELECT rp.*, r.name as race_name, r.distance, r.start_time, 
               e.name as event_name, e.event_date, 
               y.year, y.name as year_name,
               t.name as team_name, 
               p.name as participant_name, p.birth_year
        FROM race_participants rp
        JOIN races r ON rp.race_id = r.id
        JOIN events e ON r.event_id = e.id
        JOIN years y ON e.year_id = y.id
        JOIN teams t ON rp.team_id = t.id
        JOIN participants p ON rp.participant_id = p.id
        WHERE rp.id = :id
    ");
    $stmt->bindParam(':id', $participantId);
    $stmt->execute();
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participant) {
        die("Error: Race participant not found");
    }
    
    // Get total participants in this race for context
    $stmtTotal = $conn->prepare("
        SELECT COUNT(*) as total_participants
        FROM race_participants
        WHERE race_id = :race_id
    ");
    $stmtTotal->bindParam(':race_id', $participant['race_id']);
    $stmtTotal->execute();
    $totalResult = $stmtTotal->fetch(PDO::FETCH_ASSOC);
    $totalParticipants = $totalResult['total_participants'];
    
    // Format position
    $position = $participant['position'];
    if ($position == 1) {
        $positionText = "1.";
        $positionEmoji = "🥇";
        $positionWord = "ersten";
    } elseif ($position == 2) {
        $positionText = "2.";
        $positionEmoji = "🥈";
        $positionWord = "zweiten";
    } elseif ($position == 3) {
        $positionText = "3.";
        $positionEmoji = "🥉";
        $positionWord = "dritten";
    } else {
        $positionText = $position . ".";
        $positionEmoji = "";
        $positionWord = $position . ".";
    }
    
    // Format date
    $eventDate = date('d.m.Y', strtotime($participant['event_date']));
    
    // Format time
    $finishTime = $participant['finish_time'];
    if ($finishTime) {
        $timeParts = explode(':', $finishTime);
        if (count($timeParts) === 2) {
            $minutes = (int)$timeParts[0];
            $seconds = (float)$timeParts[1];
            $formattedTime = sprintf("%d:%05.2f", $minutes, $seconds);
        } else {
            $formattedTime = $finishTime;
        }
    } else {
        $formattedTime = '';
    }
    
    // Content-Type für HTML setzen
    header('Content-Type: text/html; charset=utf-8');
    
    if ($isBootCertificate) {
        // Hole alle Teilnehmer und Teams für dieses Boot (gleiche race_id, lane, boat_number)
        $bootStmt = $conn->prepare("SELECT rp.id, p.name, p.birth_year, t.name as team_name, rp.position, rp.finish_seconds FROM race_participants rp JOIN participants p ON rp.participant_id = p.id JOIN teams t ON rp.team_id = t.id WHERE rp.race_id = :race_id AND rp.lane = :lane AND (rp.boat_number = :boat_number OR (rp.boat_number IS NULL AND :boat_number IS NULL))");
        $bootStmt->bindParam(':race_id', $participant['race_id']);
        $bootStmt->bindParam(':lane', $participant['lane']);
        $bootStmt->bindParam(':boat_number', $participant['boat_number']);
        $bootStmt->execute();
        $bootParticipants = $bootStmt->fetchAll(PDO::FETCH_ASSOC);
        // Alle Teams des Boots (doppelte entfernen)
        $bootTeams = array_unique(array_map(function($bp){ return $bp['team_name']; }, $bootParticipants));
        // Zeit des Boots: kleinste finish_seconds aller Teilnehmer
        $bootFinishSeconds = null;
        foreach ($bootParticipants as $bp) {
            if ($bp['finish_seconds'] !== null && ($bootFinishSeconds === null || $bp['finish_seconds'] < $bootFinishSeconds)) {
                $bootFinishSeconds = $bp['finish_seconds'];
            }
        }
        // Zeit formatieren
        $bootFinishTime = $bootFinishSeconds !== null ? formatRaceTime($bootFinishSeconds) : '';
        // Alle Boote im Rennen mit Zeit laden
        $allBoatsStmt = $conn->prepare("SELECT lane, boat_number, MIN(finish_seconds) as finish_seconds FROM race_participants WHERE race_id = :race_id GROUP BY lane, boat_number");
        $allBoatsStmt->bindParam(':race_id', $participant['race_id']);
        $allBoatsStmt->execute();
        $allBoats = $allBoatsStmt->fetchAll(PDO::FETCH_ASSOC);
        // Nach Zeit sortieren und Platz bestimmen
        usort($allBoats, function($a, $b) {
            if ($a['finish_seconds'] === null) return 1;
            if ($b['finish_seconds'] === null) return -1;
            return $a['finish_seconds'] <=> $b['finish_seconds'];
        });
        $bootPosition = '-';
        foreach ($allBoats as $idx => $b) {
            if ($b['lane'] == $participant['lane'] && $b['boat_number'] == $participant['boat_number']) {
                $bootPosition = $idx + 1;
                break;
            }
        }
        // Medaillen-Emoji für Boot
        $bootMedal = '';
        if ($bootPosition == 1) $bootMedal = '🥇';
        elseif ($bootPosition == 2) $bootMedal = '🥈';
        elseif ($bootPosition == 3) $bootMedal = '🥉';
        $totalBoats = count($allBoats);
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urkunde - <?= htmlspecialchars($participant['participant_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        @media screen {
            body {
                font-family: 'Open Sans', sans-serif;
                background-color: #f5f5f5;
                margin: 0;
                padding: 20px;
                color: #333;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .controls {
                margin: 0 auto 20px auto;
                max-width: 210mm;
                display: flex;
                justify-content: flex-end;
                align-items: center;
                width: 100%;
            }
            
            .certificate-container {
                width: 210mm;
                min-height: 297mm;
                background-color: #fff;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                position: relative;
                margin: 0 auto;
                padding: 0;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
            }
            
            .certificate-inner {
                background-color: #fff;
                width: 100%;
                height: 100%;
                margin: 0;
                position: relative;
                display: flex;
                flex-direction: column;
                flex: 1;
            }
            
            .certificate-border {
                border: 2px solid #1e4e8c;
                border-radius: 0;
                margin: 30mm 15mm 15mm 15mm;
                padding: 10mm;
                position: relative;
                min-height: calc(297mm - 65mm);
                display: flex;
                flex-direction: column;
                flex: 1;
            }
            
            .btn {
                padding: 8px 16px;
                background-color: <?= $colors['primary'] ?>;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                text-decoration: none;
                display: inline-block;
            }
            
            .btn:hover {
                background-color: <?= $colors['secondary'] ?>;
            }
            
            .btn-group {
                display: flex;
                gap: 10px;
                position: relative;
            }
        }
        
        /* Common styles for both screen and print */
        .certificate-header {
            text-align: center;
            padding-bottom: 15px;
            margin-bottom: 30px;
            border-bottom: 1px solid <?= $colors['primary'] ?>;
        }
        
        .certificate-title {
            font-family: 'Noto Serif', serif;
            font-size: 42px;
            color: <?= $colors['primary'] ?>;
            margin: 0;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .certificate-subtitle {
            font-size: 22px;
            color: <?= $colors['primary'] ?>;
            margin: 10px 0;
        }
        
        .certificate-body {
            text-align: center;
            margin: 40px 0;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .participant-name {
            font-family: 'Noto Serif', serif;
            font-size: 38px;
            color: <?= $colors['primary'] ?>;
            margin: 10px 0 20px 0;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .birth-year {
            font-size: 22px;
            font-weight: normal;
            display: inline-block;
        }
        
        .team-name {
            font-size: 26px;
            color: #444;
            margin: 5px 0 25px 0;
            font-weight: 600;
        }
        
        .achievement {
            font-size: 24px;
            margin: 30px 0;
        }
        
        .position {
            font-size: 32px;
            font-weight: bold;
            color: <?= $colors['primary'] ?>;
            margin: 0 5px;
        }
        
        .race-details {
            font-size: 20px;
            margin: 25px 0;
            line-height: 1.5;
        }
        
        .race-name {
            font-weight: bold;
            font-size: 24px;
            color: <?= $colors['primary'] ?>;
            display: block;
            margin: 10px 0;
        }
        
        .time-details {
            font-size: 20px;
            margin: 20px 0;
            color: #444;
            padding: 10px;
            background-color: <?= $colors['accent'] ?>;
            display: inline-block;
        }
        
        .finish-time {
            font-weight: bold;
            font-size: 24px;
            color: <?= $colors['primary'] ?>;
        }
        
        .certificate-footer {
            text-align: center;
            margin-top: auto;
            position: relative;
            padding-bottom: 20px;
            width: 100%;
        }
        
        .signature-area {
            display: flex;
            justify-content: space-around;
            margin: 20px 80px;
        }
        
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            margin: 10px auto;
            padding-top: 5px;
        }
        
        .signature-text {
            font-size: 16px;
        }
        
        .certificate-date {
            font-size: 16px;
            margin-top: 30px;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 150px;
            color: rgba(0, 0, 0, 0.02);
            z-index: 1;
            font-family: 'Noto Serif', serif;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .medal {
            font-size: 48px;
            display: block;
            margin: 15px auto;
        }
        
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            
            html, body {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                background-color: white;
            }
            
            .certificate-container {
                margin: 0;
                padding: 30px;
                width: 100%;
                height: 100%;
                border: none;
                box-shadow: none;
                page-break-after: always;
                box-sizing: border-box;
                position: relative;
                overflow: hidden;
            }
            
            .certificate-footer {
                position: absolute;
                bottom: 60px;
                left: 0;
                right: 0;
            }
            
            .controls, .btn {
                display: none !important;
            }
        }
        
        .print-hint {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
            text-align: right;
            width: 100%;
        }
        
        .info-btn {
            width: 38px;
            min-width: 38px;
            height: 38px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        
        .info-icon {
            font-style: normal;
            font-weight: bold;
            font-size: 16px;
        }
        
        .tooltip {
            display: none;
            position: absolute;
            right: auto;
            left: 49px; /* Position from info button */
            top: 45px;
            background-color: #333;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            width: 300px;
            text-align: left;
            font-size: 14px;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .tooltip:after {
            content: '';
            position: absolute;
            top: -10px;
            right: auto;
            left: 15px;
            border-width: 5px;
            border-style: solid;
            border-color: transparent transparent #333 transparent;
        }
    </style>
</head>
<body>
    <!-- Controls outside of print area -->
    <div class="controls">
        <div class="btn-group">
            <button class="btn" onclick="window.print()">Urkunde drucken</button>
            <button class="btn info-btn" id="infoBtn">
                <i class="info-icon">i</i>
            </button>
            <div class="tooltip" id="infoTooltip">Tipp: Im Druckdialog "Als PDF speichern" wählen, um die Urkunde als PDF zu erhalten.</div>
        </div>
    </div>
    
    <div class="certificate-container">
        <div class="certificate-inner">
            <div class="certificate-border">
                <div class="certificate-header">
                    <h1 class="certificate-title">Urkunde</h1>
                    <h2 class="certificate-subtitle"><?= htmlspecialchars($participant['event_name']) ?> - <?= $participant['year'] ?></h2>
                </div>
                
                <div class="certificate-body">
                    <p>Diese Urkunde wird überreicht an</p>
                    <?php if ($isBootCertificate && !empty($bootParticipants)): ?>
                        <div class="participant-name" style="margin-bottom: 10px;">
                            <?php foreach ($bootParticipants as $bp): ?>
                                <div><?= htmlspecialchars($bp['name']) ?><?php if (!empty($bp['birth_year'])): ?> <span class="birth-year">(<?= substr($bp['birth_year'], -2) ?>)</span><?php endif; ?></div>
                            <?php endforeach; ?>
                        </div>
                        <h2 class="team-name">von <?= htmlspecialchars(implode(', ', $bootTeams)) ?></h2>
                    <?php elseif (!empty($participant['participant_name'])): ?>
                    <p class="participant-name">
                        <?= htmlspecialchars($participant['participant_name']) ?> 
                        <?php if (!empty($participant['birth_year'])): ?>
                            <span class="birth-year">(<?= substr($participant['birth_year'], -2) ?>)</span>
                        <?php endif; ?>
                    </p>
                        <h2 class="team-name">vom <?= htmlspecialchars($participant['team_name']) ?></h2>
                    <?php endif; ?>
                    
                    <p class="achievement">
                        <?php if ($isBootCertificate): ?>
                            für das Erreichen des <span class="position"><?= $bootPosition ?>.</span> Platzes<?php if ($bootMedal): ?><span class="medal"><?= $bootMedal ?></span><?php endif; ?><?php if ($totalBoats): ?> <span>von <?= $totalBoats ?> Booten</span><?php endif; ?>
                        <?php else: ?>
                            für das Erreichen des <span class="position"><?= $positionText ?></span> Platzes<?php if ($positionEmoji): ?><span class="medal"><?= $positionEmoji ?></span><?php endif; ?><?php if ($totalParticipants): ?> <span>von <?= $totalParticipants ?> Teilnehmern</span><?php endif; ?>
                        <?php endif; ?>
                    </p>
                    
                    <p class="race-details">
                        im Rennen 
                        <span class="race-name"><?= htmlspecialchars($participant['race_name']) ?></span>
                        über eine Distanz von <?= $participant['distance'] ?> Metern
                    </p>
                    
                    <?php if ($isBootCertificate && $bootFinishTime): ?>
                        <p class="time-details">
                            Mit einer Zeit von <span class="finish-time"><?= $bootFinishTime ?></span>
                        </p>
                    <?php elseif ($participant['finish_time']): ?>
                    <p class="time-details">
                        Mit einer Zeit von <span class="finish-time"><?= $formattedTime ?></span>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="certificate-footer">
                    <div class="signature-area">
                        <div>
                            <div class="signature-line"></div>
                            <p class="signature-text">Rennleiter</p>
                        </div>
                        <div>
                            <div class="signature-line"></div>
                            <p class="signature-text">Veranstalter</p>
                        </div>
                    </div>
                    <p class="certificate-date">Ausgestellt am <?= $eventDate ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Info-Tooltip Funktionalität
        document.addEventListener('DOMContentLoaded', function() {
            const infoBtn = document.getElementById('infoBtn');
            const tooltip = document.getElementById('infoTooltip');
            
            infoBtn.addEventListener('mouseenter', function() {
                tooltip.style.display = 'block';
            });
            
            infoBtn.addEventListener('mouseleave', function() {
                tooltip.style.display = 'none';
            });
        });
        
        // Automatisches Drucken, wenn als PDF heruntergeladen wird
        <?php if ($downloadAsPdf): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
                setTimeout(function() {
                    window.close();
                }, 1000);
            }, 500);
        };
        <?php endif; ?>
    </script>
</body>
</html> 