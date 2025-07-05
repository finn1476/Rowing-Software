<?php
// Authentifizierungs-Konfiguration

// Admin-Passwort für den Zugriff auf das Admin-Panel
define('ADMIN_PASSWORD', 'admin123');

// Die drei Passwörter, die zum Löschen der Datenbank benötigt werden
define('DB_CLEAR_PASSWORD_1', 'delete1');
define('DB_CLEAR_PASSWORD_2', 'delete2');
define('DB_CLEAR_PASSWORD_3', 'delete3');

// Passwort für Zeitnehmer/Marker
define('MARKER_PASSWORD', 'zeit123');

// Passwort für das Chat-System
define('CHAT_PASSWORD', 'regattaChat2024');

// Hilfsfunktion zum Überprüfen, ob der Benutzer als Admin eingeloggt ist
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Hilfsfunktion zum Umleiten zur Login-Seite, wenn nicht eingeloggt
function redirectToLoginIfNotAdmin() {
    if (!isAdminLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . (strpos($_SERVER['REQUEST_URI'], '/index.php') === false ? '/index.php?page=admin_login' : '?page=admin_login'));
        exit;
    }
}

// Hilfsfunktion zum Überprüfen, ob der Benutzer als Marker eingeloggt ist
function isMarkerLoggedIn() {
    return isset($_SESSION['marker_logged_in']) && $_SESSION['marker_logged_in'] === true;
}
?> 