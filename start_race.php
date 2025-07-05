<?php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: pages/admin_login.php');
    exit;
}
if (empty($_SESSION['marker_logged_in'])) {
    $_SESSION['redirect_after_login_marker'] = $_SERVER['REQUEST_URI'];
    header('Location: pages/marker_login.php');
    exit;
}
include_once 'config/database.php';
$conn = getDbConnection();

// Rennen starten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['race_id']) && !isset($_POST['false_start'])) {
    $raceId = (int)$_POST['race_id'];
    // Prüfe, ob das Rennen existiert und nicht bereits gestartet ist
    $check = $conn->prepare("SELECT status FROM races WHERE id = :id");
    $check->bindParam(':id', $raceId);
    $check->execute();
    $race = $check->fetch(PDO::FETCH_ASSOC);
    if ($race && $race['status'] !== 'started') {
        $stmt = $conn->prepare("UPDATE races SET status = 'started', actual_start_time = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $raceId);
        if ($stmt->execute()) {
            header('Location: start_race.php');
            exit;
        } else {
            echo '<div style="color:red;text-align:center;">Fehler beim Starten des Rennens!</div>';
        }
    } else {
        echo '<div style="color:red;text-align:center;">Rennen existiert nicht oder ist bereits gestartet!</div>';
    }
}

// Fehlstart erklären - Rennen zurücksetzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['race_id']) && isset($_POST['false_start'])) {
    $raceId = (int)$_POST['race_id'];
    
    // Transaktion starten
    $conn->beginTransaction();
    try {
        // Rennen auf 'upcoming' zurücksetzen und actual_start_time löschen
        $stmt = $conn->prepare("UPDATE races SET status = 'upcoming', actual_start_time = NULL WHERE id = :id");
        $stmt->bindParam(':id', $raceId);
        $stmt->execute();
        
        // Race participant IDs für dieses Rennen holen
        $stmt = $conn->prepare("SELECT id FROM race_participants WHERE race_id = :race_id");
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        $participantIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($participantIds)) {
            $placeholders = str_repeat('?,', count($participantIds) - 1) . '?';
            
            // Alle distance_times für dieses Rennen löschen
            $stmt = $conn->prepare("DELETE FROM distance_times WHERE race_participant_id IN ($placeholders)");
            $stmt->execute($participantIds);
            
            // Alle finish_times und finish_seconds in race_participants zurücksetzen
            $stmt = $conn->prepare("UPDATE race_participants SET finish_time = NULL, finish_seconds = NULL, position = NULL WHERE id IN ($placeholders)");
            $stmt->execute($participantIds);
        }
        
        $conn->commit();
        header('Location: start_race.php');
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        echo '<div style="color:red;text-align:center;">Fehler beim Zurücksetzen des Rennens: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Alle anstehenden Rennen laden
$races = $conn->query("SELECT r.*, e.name as event_name FROM races r JOIN events e ON r.event_id = e.id WHERE r.status = 'upcoming' ORDER BY r.start_time ASC")->fetchAll(PDO::FETCH_ASSOC);

// Alle laufenden Rennen laden
$runningRaces = $conn->query("SELECT r.*, e.name as event_name FROM races r JOIN events e ON r.event_id = e.id WHERE r.status = 'started' ORDER BY r.actual_start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Rennen starten</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px #0001; }
        h1, h2 { text-align: center; }
        h2 { margin-top: 40px; margin-bottom: 20px; color: #0074B7; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #e5f6ff; }
        form { margin: 0; }
        .btn { background: #0074B7; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:disabled { background: #aaa; cursor: not-allowed; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .running-race { background: #fff3cd; }
        .running-race td { border-bottom-color: #ffeaa7; }
        .no-races { text-align: center; color: #666; font-style: italic; padding: 20px; }
        .chat-message .sender { font-weight: bold; margin-right: 6px; }
        .chat-message .time { color: #888; font-size: 0.9em; margin-left: 6px; }
    </style>
</head>
<body>
<div id="chatPauseWarning" style="display:none;text-align:center;margin:30px 0 0 0;font-size:2.2rem;font-weight:bold;background:#ffeaea;border:2px solid #b80000;padding:18px;border-radius:12px;color:#b80000;">Aktualisierung pausiert!</div>
<div class="container">
    <h1>Rennen starten</h1>
    
    <!-- Anstehende Rennen -->
    <h2>Anstehende Rennen</h2>
    <?php if (count($races) === 0): ?>
        <p class="no-races">Keine anstehenden Rennen.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Event</th>
                <th>Rennen</th>
                <th>Startzeit</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($races as $race): ?>
            <tr>
                <td><?= htmlspecialchars($race['event_name']) ?></td>
                <td><?= htmlspecialchars($race['name']) ?></td>
                <td><?= htmlspecialchars($race['start_time']) ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="race_id" value="<?= $race['id'] ?>">
                        <button class="btn" type="submit">Start</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <!-- Laufende Rennen -->
    <h2>Aktuell laufende Rennen</h2>
    <?php if (count($runningRaces) === 0): ?>
        <p class="no-races">Keine Rennen laufen derzeit.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Event</th>
                <th>Rennen</th>
                <th>Geplante Startzeit</th>
                <th>Tatsächlicher Start</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($runningRaces as $race): ?>
            <tr class="running-race">
                <td><?= htmlspecialchars($race['event_name']) ?></td>
                <td><?= htmlspecialchars($race['name']) ?></td>
                <td><?= htmlspecialchars($race['start_time']) ?></td>
                <td><?= htmlspecialchars($race['actual_start_time']) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Fehlstart erklären? Das Rennen wird zurückgesetzt und alle Zeiten gelöscht!');">
                        <input type="hidden" name="race_id" value="<?= $race['id'] ?>">
                        <input type="hidden" name="false_start" value="1">
                        <button class="btn btn-danger" type="submit">Fehlstart erklären</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
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
document.getElementById('chatSendBtn').addEventListener('click', function() {
    const message = chatInput.value.trim();
    if (!message) return;
    fetch('send_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ sender_name: 'Start', message, chat_password: CHAT_PASSWORD })
    }).then(r => r.json()).then(() => {
        chatInput.value = '';
        fetchMessages();
    });
});
let chatInterval = null;
function startChatInterval() {
    if (chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(fetchMessages, 2000);
}
function stopChatInterval() {
    if (chatInterval) clearInterval(chatInterval);
    chatInterval = null;
}
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
fetch('heartbeat.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'role=start'
});
</script>
<!-- Chat-Frontend Ende -->
</body>
</html> 