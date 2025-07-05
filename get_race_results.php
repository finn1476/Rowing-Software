<?php
// Fehlerbehandlung nur im Log, nicht auf der Seite
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

// Header für JSON-Antwort
header('Content-Type: application/json');

/**
 * Funktion zum Senden einer JSON-Fehlerantwort
 * 
 * @param string $message Fehlermeldung
 * @param int $code HTTP-Statuscode
 * @return void
 */
function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    
    // Das "data"-Attribut wird in der Frontend-JS-Funktion loadCurrentPositions() verwendet
    $response = json_encode([
        'success' => false,
        'message' => $message,
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
    
    // Sende die Antwort zum Browser
    echo $response;
    
    // Stelle sicher, dass alle Ausgaben gesendet wurden
    if (ob_get_length()) ob_end_flush();
    flush();
    exit;
}

// Prüfen ob race_id vorhanden ist
if (!isset($_GET['race_id']) || empty($_GET['race_id'])) {
    sendErrorResponse('Parameter race_id ist erforderlich', 400);
}

$race_id = intval($_GET['race_id']);

// Datenbank-Konfiguration einbinden
if (!file_exists('config/database.php')) {
    sendErrorResponse('Datenbank-Konfigurationsdatei nicht gefunden', 500);
}

require_once 'config/database.php';

try {
    // Datenbankverbindung
    $conn = getDbConnection();
    
    // Prüfen, ob das Rennen existiert
    $stmt = $conn->prepare("SELECT id FROM races WHERE id = ?");
    $stmt->execute([$race_id]);
    
    if ($stmt->rowCount() === 0) {
        sendErrorResponse("Rennen mit ID $race_id nicht gefunden", 404);
    }
    
    // SQL für race_participants mit join
    $query = "
        SELECT 
            rp.id,
            rp.participant_id,
            p.name AS participant_name,
            t.name AS team_name,
            rp.lane,
            rp.position,
            rp.finish_time,
            COALESCE(rp.finish_seconds, 0) AS finish_seconds
        FROM 
            race_participants rp
        JOIN 
            participants p ON rp.participant_id = p.id
        JOIN 
            teams t ON rp.team_id = t.id
        WHERE 
            rp.race_id = ?
        ORDER BY 
            CASE WHEN rp.position IS NULL THEN 999 ELSE rp.position END ASC,
            CASE WHEN rp.finish_seconds IS NULL OR rp.finish_seconds = 0 THEN 999999 ELSE rp.finish_seconds END ASC,
            rp.lane ASC
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Fehler bei der Vorbereitung der SQL-Abfrage");
    }
    
    $result = $stmt->execute([$race_id]);
    
    if (!$result) {
        throw new Exception("Fehler bei der Ausführung der SQL-Abfrage");
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Zeiten in Sekunden umwandeln, falls nötig
    foreach ($results as &$result) {
        if (empty($result['finish_seconds']) || $result['finish_seconds'] == 0) {
            $result['finish_seconds'] = timeToSeconds($result['finish_time']);
        }
    }
    
    // JSON Antwort
    $response = json_encode([
        'success' => true,
        'race_id' => $race_id,
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);
    
    echo $response;
    
    // Stelle sicher, dass alle Ausgaben gesendet wurden
    if (ob_get_length()) ob_end_flush();
    flush();
    
} catch (PDOException $e) {
    error_log("Datenbankfehler in get_race_results.php: " . $e->getMessage());
    sendErrorResponse('Datenbankfehler beim Abrufen der Rennergebnisse', 500);
} catch (Exception $e) {
    error_log("Fehler in get_race_results.php: " . $e->getMessage());
    sendErrorResponse('Fehler beim Abrufen der Rennergebnisse', 500);
}
?> 