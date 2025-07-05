<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Entferne die Admin-Session
unset($_SESSION['admin_logged_in']);

// Setze einen Logout-Hinweis
$_SESSION['message'] = "Sie wurden erfolgreich abgemeldet.";
$_SESSION['message_type'] = "success";

// Leite zur Startseite um
header("Location: index.php");
exit;
?> 