<?php
session_start();
include_once __DIR__ . '/../config/auth.php';
redirectToLoginIfNotAdmin();
include_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();
$statuses = $conn->query('SELECT * FROM live_status ORDER BY role, marker_distance, last_ping')->fetchAll(PDO::FETCH_ASSOC);
// Bündeln: Nur der jeweils letzte Ping pro Rolle/Marker
$grouped = [];
foreach ($statuses as $s) {
    $key = $s['role'] . '|' . ($s['role'] === 'marker' ? $s['marker_distance'] : '-');
    if (!isset($grouped[$key]) || strtotime($s['last_ping']) > strtotime($grouped[$key]['last_ping'])) {
        $grouped[$key] = $s;
    }
}
$statuses = array_values($grouped);
function statusColor($lastPing) {
    $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
    $ping = new DateTime($lastPing, new DateTimeZone('Europe/Berlin'));
    $diff = $now->getTimestamp() - $ping->getTimestamp();
    if ($diff < 60) return '#0a0'; // grün
    if ($diff < 120) return '#ffa500'; // gelb
    return '#b80000'; // rot
}
function statusText($lastPing) {
    $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
    $ping = new DateTime($lastPing, new DateTimeZone('Europe/Berlin'));
    $diff = $now->getTimestamp() - $ping->getTimestamp();
    if ($diff < 60) return 'Online (' . $diff . 's)';
    if ($diff < 120) return 'Warnung (' . $diff . 's)';
    return 'Offline (' . $diff . 's)';
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Live-Status Übersicht</title>
    <script>setInterval(function(){ location.reload(); }, 10000);</script>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 20px; }
        h2 { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 8px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #e5f6ff; }
        .status-dot { display: inline-block; width: 18px; height: 18px; border-radius: 50%; margin-right: 8px; vertical-align: middle; }
        .role { font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>Live-Status Übersicht</h2>
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Rolle</th>
                <th>Marker (Meter)</th>
                <th>Letzter Kontakt</th>
                <th>Löschen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($statuses as $s): ?>
            <tr>
                <td><span class="status-dot" style="background:<?= statusColor($s['last_ping']) ?>;"></span> <?= statusText($s['last_ping']) ?></td>
                <td class="role">
                    <?= htmlspecialchars(ucfirst($s['role'])) ?>
                </td>
                <td><?= $s['role'] === 'marker' ? htmlspecialchars($s['marker_distance']) : '-' ?></td>
                <td><?= htmlspecialchars($s['last_ping']) ?></td>
                <td><span class="delete-livestatus" data-id="<?= $s['id'] ?>" style="color:#b00;cursor:pointer;font-size:1.2em;" title="Eintrag löschen">🗑️</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:30px;color:#888;font-size:0.95em;text-align:center;">Grün: Online (&lt;1min) &nbsp; Gelb: Warnung (1-2min) &nbsp; Rot: Offline (&gt;2min)</div>
</div>
<script>
document.querySelectorAll('.delete-livestatus').forEach(el => {
    el.onclick = function() {
        if (confirm('Diesen Live-Status-Eintrag wirklich löschen?')) {
            fetch('../delete_live_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + encodeURIComponent(el.dataset.id)
            }).then(() => location.reload());
        }
    };
});
</script>
</body>
</html> 