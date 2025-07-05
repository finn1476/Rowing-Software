<?php
session_start();
if (empty($_SESSION['marker_logged_in'])) {
    $_SESSION['redirect_after_login_marker'] = $_SERVER['REQUEST_URI'];
    header('Location: pages/marker_login.php');
    exit;
}
include_once 'config/database.php';
$conn = getDbConnection();

// Aktuelles laufendes Rennen finden (ggf. per GET race_id)
if (isset($_GET['race_id'])) {
    $race = $conn->prepare("SELECT * FROM races WHERE id = :id AND status = 'started'");
    $race->bindParam(':id', $_GET['race_id']);
    $race->execute();
    $race = $race->fetch(PDO::FETCH_ASSOC);
} else {
    $race = $conn->query("SELECT * FROM races WHERE status = 'started' ORDER BY actual_start_time ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
if (!$race) {
    $nowBerlin = (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('H:i:s');
    $nextRace = $conn->query("SELECT * FROM races WHERE status = 'upcoming' ORDER BY start_time ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $nextRaceTime = $nextRace ? (new DateTime($nextRace['start_time'], new DateTimeZone('Europe/Berlin')))->format('d.m.Y H:i') : null;
    echo '<div style="text-align:center; margin-top:80px; font-size:2rem; color:#0074B7;">
        <div style="font-size:4rem;">🦆</div>
        <b>Hier ist gerade mehr Ruhe als auf dem Wannsee bei Windstille!</b><br>
        Bald geht&#39;s weiter – einfach entspannen und die Enten zählen... 🦆🦆🦆<br><br>';
    echo 'Aktuelle Zeit: <b>' . htmlspecialchars($nowBerlin) . '</b><br>';
    if ($nextRaceTime) {
        echo 'Nächstes geplantes Rennen: <b>' . htmlspecialchars($nextRaceTime) . '</b>';
    } else {
        echo 'Kein weiteres Rennen geplant.';
    }
    echo '<br>(Die Seite aktualisiert sich automatisch, sobald ein Rennen startet.)';
    echo '</div>';
    echo '<script>setInterval(function(){ location.reload(); }, 5000);</script>';
    // Chat-Frontend auch im Kein-Rennen-Fall anzeigen
    ?>
    <div class="chat-container" style="max-width:500px;margin:40px auto 0 auto;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;padding:20px;">
        <h2>Chat</h2>
        <div class="chat-messages" id="chatMessages" style="height:200px;overflow-y:auto;border:1px solid #ccc;border-radius:4px;padding:10px;background:#fafafa;margin-bottom:10px;"></div>
        <form id="chatForm" class="chat-input-row" autocomplete="off" style="display:flex;gap:8px;">
            <input type="text" id="chatInput" placeholder="Nachricht eingeben..." maxlength="500" required style="flex:1;padding:6px;border-radius:4px;border:1px solid #ccc;" />
            <button type="button" id="chatSendBtn" style="padding:6px 16px;border-radius:4px;border:none;background:#007bff;color:#fff;cursor:pointer;">Senden</button>
        </form>
    </div>
    <script>
    const CHAT_PASSWORD = 'regattaChat2024';
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const markerName = 'Marker (kein Rennen)';
    function escapeHtml(text) {
        return text.replace(/[&<>"']/g, function(m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]); });
    }
    function fetchMessages() {
        fetch('get_messages.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'chat_password=' + encodeURIComponent(CHAT_PASSWORD)
        })
            .then(r => r.json())
            .then(data => {
                if (!data.messages) return;
                chatMessages.innerHTML = '';
                data.messages.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = 'chat-message';
                    div.innerHTML = `<span class=\"sender\">${escapeHtml(msg.sender_name)}</span>: ` +
                        `<span class=\"text\">${escapeHtml(msg.message)}</span> ` +
                        `<span class=\"time\">${new Date(msg.created_at).toLocaleTimeString('de-DE', {hour: '2-digit', minute:'2-digit'})}</span>`;
                    chatMessages.appendChild(div);
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
    }
    let chatInterval = null;
    function startChatInterval() {
        if (chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(fetchMessages, 2000);
    }
    function stopChatInterval() {
        if (chatInterval) clearInterval(chatInterval);
        chatInterval = null;
    }
    chatInput.addEventListener('focus', function() {
        stopChatInterval();
    });
    chatInput.addEventListener('blur', function() {
        startChatInterval();
    });
    startChatInterval();
    fetchMessages();
    document.getElementById('chatSendBtn').addEventListener('click', function() {
        const message = chatInput.value.trim();
        if (!message) return;
        fetch('send_message.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ sender_name: markerName, message, chat_password: CHAT_PASSWORD })
        }).then(r => r.json()).then(() => {
            chatInput.value = '';
            fetchMessages();
        });
    });
    </script>
    <?php
    exit;
}
$raceId = $race['id'];

// Prüfe, ob mehrere Rennen laufen
$runningCount = $conn->query("SELECT COUNT(*) FROM races WHERE status = 'started'")->fetchColumn();
if ($runningCount > 1) {
    echo '<div style="color:#b80; text-align:center; margin:30px 0; font-size:2.2rem; font-weight:bold; background:#fffbe6; border:2px solid #b80; padding:18px; border-radius:12px;">Achtung: Es laufen mehrere Rennen gleichzeitig!</div>';
}

// Marker aus dem Rennen holen
$markers = array_map('intval', array_filter(array_map('trim', explode(',', $race['distance_markers']))));
if (empty($markers)) {
    echo '<h2>Keine Marker für dieses Rennen definiert.</h2>';
    exit;
}

// Aktuellen Marker bestimmen (per GET, sonst ersten nehmen)
$currentMarker = isset($_GET['marker']) ? (int)$_GET['marker'] : $markers[0];

// Alle Boote für das Rennen holen
$boats = $conn->query("SELECT lane, boat_number FROM race_participants WHERE race_id = $raceId GROUP BY lane, boat_number ORDER BY lane, boat_number")->fetchAll(PDO::FETCH_ASSOC);

// Bereits eingetragene Zeiten für diesen Marker
$existing = $conn->query("SELECT rp.lane, rp.boat_number FROM distance_times dt JOIN race_participants rp ON dt.race_participant_id = rp.id WHERE rp.race_id = $raceId AND dt.distance = $currentMarker")->fetchAll(PDO::FETCH_ASSOC);
$existingSet = array();
foreach ($existing as $e) {
    $existingSet[$e['lane'].'_'.$e['boat_number']] = true;
}

// Debug-Tabelle und Zeitinfos nur anzeigen, wenn debug=1
$showDebug = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($showDebug) {
    // Debug-Tabelle für distance_times des aktuellen Rennens
    $debugTimes = $conn->query("SELECT dt.*, rp.lane, rp.boat_number FROM distance_times dt JOIN race_participants rp ON dt.race_participant_id = rp.id WHERE rp.race_id = $raceId ORDER BY dt.distance, rp.lane, rp.boat_number")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($debugTimes)) {
        echo '<div style="margin:40px 0;">';
        echo '<h3>Debug: distance_times für dieses Rennen</h3>';
        echo '<table style="font-size:0.95em; border:1px solid #ccc; background:#fff; margin:0 auto;">';
        echo '<tr><th>lane</th><th>bug</th><th>distance</th><th>time</th><th>seconds_elapsed</th><th>created_at</th></tr>';
        foreach ($debugTimes as $dt) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($dt['lane']) . '</td>';
            echo '<td>' . htmlspecialchars($dt['boat_number']) . '</td>';
            echo '<td>' . htmlspecialchars($dt['distance']) . '</td>';
            echo '<td>' . htmlspecialchars($dt['time']) . '</td>';
            echo '<td>' . htmlspecialchars($dt['seconds_elapsed']) . '</td>';
            echo '<td>' . htmlspecialchars($dt['created_at']) . '</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
    // Debug-Infos zu Zeitberechnung
    $serverNow = (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d H:i:s');
    $actualStart = $race['actual_start_time'] ?? '';
    $debugDiff = '';
    if (!empty($actualStart)) {
        $nowDT = new DateTime('now', new DateTimeZone('Europe/Berlin'));
        $startDT = new DateTime($actualStart, new DateTimeZone('Europe/Berlin'));
        $diffSec = $nowDT->getTimestamp() - $startDT->getTimestamp();
        $debugDiff = $diffSec;
    } else {
        $debugDiff = 'n/a';
    }
    echo '<div style="margin:20px 0; padding:10px; background:#ffe; border:1px solid #cc0;">';
    echo '<b>DEBUG Zeit:</b><br>Serverzeit: ' . htmlspecialchars($serverNow) . '<br>actual_start_time: ' . htmlspecialchars($actualStart) . '<br>Diff (Sekunden): ' . htmlspecialchars($debugDiff) . '<br></div>';
}

// Zeit speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lane'], $_POST['boat_number'])) {
    $lane = (int)$_POST['lane'];
    $boatNumber = $_POST['boat_number'];
    $ids = $conn->query("SELECT id FROM race_participants WHERE race_id = $raceId AND lane = $lane AND (boat_number = " . $conn->quote($boatNumber) . " OR (boat_number IS NULL AND " . ($boatNumber === '' ? '1' : '0') . "))")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($race['actual_start_time'])) {
        $nowDT = new DateTime('now', new DateTimeZone('Europe/Berlin'));
        $startDT = new DateTime($race['actual_start_time'], new DateTimeZone('Europe/Berlin'));
        $seconds = $nowDT->getTimestamp() - $startDT->getTimestamp();
        if ($seconds < 0) $seconds = 0;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        $time = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    } else {
        $seconds = null;
        $time = null;
    }
    foreach ($ids as $rpId) {
        $stmt = $conn->prepare("INSERT INTO distance_times (race_participant_id, distance, time, seconds_elapsed) VALUES (:rp_id, :distance, :time, :seconds)");
        $stmt->bindParam(':rp_id', $rpId);
        $stmt->bindParam(':distance', $currentMarker);
        $stmt->bindParam(':time', $time);
        $stmt->bindParam(':seconds', $seconds);
        $stmt->execute();
    }
    header('Location: marker_time.php?marker=' . $currentMarker);
    exit;
}

// Prüfen, ob alle Boote für diesen Marker Zeit haben
$allDone = true;
foreach ($boats as $b) {
    $key = $b['lane'].'_'.$b['boat_number'];
    if (empty($existingSet[$key])) {
        $allDone = false;
        break;
    }
}

// Nächster Marker
$markerIdx = array_search($currentMarker, $markers);
$nextMarker = $markerIdx !== false && isset($markers[$markerIdx+1]) ? $markers[$markerIdx+1] : null;

// Prüfen, ob alle Marker für dieses Rennen erledigt sind
$allMarkersDone = $allDone && !$nextMarker;

// Nächstes Rennen finden
$nextRace = $conn->query("SELECT * FROM races WHERE status = 'started' AND id != $raceId ORDER BY actual_start_time ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$marker_name = 'Marker ' . intval($currentMarker);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Marker <?= htmlspecialchars($currentMarker) ?> - <?= htmlspecialchars($race['name']) ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script>
    let reloadInterval = setInterval(function() { location.reload(); }, 5000);
    let reloadPausedSince = null;
    function pauseReload() {
        if (reloadInterval) clearInterval(reloadInterval);
        reloadInterval = null;
        reloadPausedSince = Date.now();
    }
    function resumeReload() {
        if (!reloadInterval) reloadInterval = setInterval(function() { location.reload(); }, 5000);
        reloadPausedSince = null;
    }
    function updatePauseWarning() {
        const warn = document.getElementById('chatPauseWarning');
        if (reloadPausedSince) {
            const seconds = Math.floor((Date.now() - reloadPausedSince) / 1000);
            warn.innerHTML = 'Aktualisierung pausiert! (' + seconds + 's)';
        }
    }
    setInterval(updatePauseWarning, 1000);
    </script>
    <style>
        html { font-size: 95%; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1100px; margin: 30px auto; background: #fff; padding: 28px 10px; border-radius: 14px; box-shadow: 0 4px 24px #0002; }
        h1 { text-align: center; font-size: 1.5rem; margin-bottom: 18px; }
        .marker { font-size: 1.1rem; margin: 16px 0; text-align: center; font-weight: bold; }
        table { width: auto; margin: 16px auto 0 auto; border-collapse: separate; border-spacing: 0; font-size: 1rem; }
        th, td { padding: 10px 6px; border-bottom: 2px solid #cce3f6; text-align: left; }
        th { background: #e5f6ff; font-size: 1.1rem; }
        tr:nth-child(even) { background: #f7fbff; }
        tr:nth-child(odd) { background: #eaf3fa; }
        td.lane, td.bug { font-size: 1.3rem; font-weight: bold; color: #0074B7; letter-spacing: 1px; background: #e5f6ff; border-radius: 6px; border: 2px solid #b3d8f6; text-align: center; }
        td.lane { width: 70px; }
        td.bug { width: 100px; }
        td.aktion { width: 1%; white-space: nowrap; text-align: center; padding-left: 0; padding-right: 0; }
        form { margin: 0; }
        .btn { background: #0074B7; color: #fff; border: none; padding: 12px 18px; border-radius: 7px; cursor: pointer; font-size: 1.1rem; font-weight: bold; box-shadow: 0 2px 8px #0002; transition: background 0.2s; }
        .btn:active { background: #005a8c; }
        .btn:disabled { background: #aaa; cursor: not-allowed; }
        .done { color: #0a0; font-weight: bold; font-size: 1rem; }
        .chat-message .sender { font-weight: bold; margin-right: 6px; }
        .chat-message .time { color: #888; font-size: 0.9em; margin-left: 6px; }
    </style>
</head>
<body>
<div id="chatPauseWarning" style="display:none;text-align:center;margin:30px 0 0 0;font-size:2.2rem;font-weight:bold;background:#ffeaea;border:2px solid #b80000;padding:18px;border-radius:12px;color:#b80000;">Aktualisierung pausiert!</div>
<div class="container">
    <h1>Marker <?= htmlspecialchars($currentMarker) ?> - <?= htmlspecialchars($race['name']) ?></h1>
    <div class="marker">Marker: <b><?= htmlspecialchars($currentMarker) ?> Meter</b></div>
    <table>
        <thead>
            <tr><th>Lane</th><th>Bugnummer</th><th>Aktion</th></tr>
        </thead>
        <tbody>
        <?php foreach ($boats as $b): $key = $b['lane'].'_'.$b['boat_number']; ?>
            <tr>
                <td class="lane"><?= htmlspecialchars($b['lane']) ?></td>
                <td class="bug"><?= htmlspecialchars($b['boat_number']) ?></td>
                <td class="aktion">
                    <?php if (!empty($existingSet[$key])): ?>
                        <span class="done">Erfasst</span>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="lane" value="<?= $b['lane'] ?>">
                            <input type="hidden" name="boat_number" value="<?= $b['boat_number'] ?>">
                            <button class="btn" type="submit">Zeit nehmen</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($allDone): ?>
        <div style="margin:40px 0; color:#0074B7; font-size:2.2rem; text-align:center; font-weight:bold;">
            Alle Zeiten für diesen Marker erfasst.
            <?php if ($nextRace): ?>
                <br><span id="autonext" style="display:inline-block;margin-top:20px;">Wechsel zum nächsten Rennen in 2 Sekunden...</span>
                <script>
                    setTimeout(function() {
                        window.location.href = 'marker_time.php?marker=<?= $currentMarker ?>&race_id=<?= $nextRace['id'] ?>';
                    }, 2000);
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Chat-Frontend Start -->
<div class="chat-container" style="max-width:500px;margin:40px auto 0 auto;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;padding:20px;">
    <h2>Chat</h2>
    <div class="chat-messages" id="chatMessages" style="height:200px;overflow-y:auto;border:1px solid #ccc;border-radius:4px;padding:10px;background:#fafafa;margin-bottom:10px;"></div>
    <form id="chatForm" class="chat-input-row" autocomplete="off" style="display:flex;gap:8px;">
        <input type="text" id="chatInput" placeholder="Nachricht eingeben..." maxlength="500" required style="flex:1;padding:6px;border-radius:4px;border:1px solid #ccc;" />
        <button type="button" id="chatSendBtn" style="padding:6px 16px;border-radius:4px;border:none;background:#007bff;color:#fff;cursor:pointer;">Senden</button>
    </form>
</div>
<script>
const CHAT_PASSWORD = 'regattaChat2024';
const chatMessages = document.getElementById('chatMessages');
const chatForm = document.getElementById('chatForm');
const chatInput = document.getElementById('chatInput');
const markerName = <?php echo json_encode($marker_name); ?>;
function escapeHtml(text) {
    return text.replace(/[&<>"']/g, function(m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]); });
}
function fetchMessages() {
    fetch('get_messages.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'chat_password=' + encodeURIComponent(CHAT_PASSWORD)
    })
        .then(r => r.json())
        .then(data => {
            if (!data.messages) return;
            chatMessages.innerHTML = '';
            data.messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'chat-message';
                div.innerHTML = `<span class=\"sender\">${escapeHtml(msg.sender_name)}</span>: ` +
                    `<span class=\"text\">${escapeHtml(msg.message)}</span> ` +
                    `<span class=\"time\">${new Date(msg.created_at).toLocaleTimeString('de-DE', {hour: '2-digit', minute:'2-digit'})}</span>`;
                chatMessages.appendChild(div);
            });
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
}
let chatInterval = null;
function startChatInterval() {
    if (chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(fetchMessages, 2000);
}
function stopChatInterval() {
    if (chatInterval) clearInterval(chatInterval);
    chatInterval = null;
}
chatInput.addEventListener('focus', function() {
    stopChatInterval();
    pauseReload();
    document.getElementById('chatPauseWarning').style.display = 'block';
    updatePauseWarning();
});
chatInput.addEventListener('blur', function() {
    startChatInterval();
    resumeReload();
    document.getElementById('chatPauseWarning').style.display = 'none';
});
startChatInterval();
fetchMessages();

document.getElementById('chatSendBtn').addEventListener('click', function() {
    const message = chatInput.value.trim();
    if (!message) return;
    fetch('send_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ sender_name: markerName, message, chat_password: CHAT_PASSWORD })
    }).then(r => r.json()).then(() => {
        chatInput.value = '';
        fetchMessages();
    });
});

fetch('heartbeat.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'role=marker&marker_distance=' + encodeURIComponent(<?= $currentMarker ?>)
});
</script>
<!-- Chat-Frontend Ende -->
</body>
</html>