<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$conn = getDbConnection();

if (isset($_GET['id'])) {
    $boat_id = $_GET['id'];
    
    try {
        $stmt = $conn->prepare("UPDATE registration_boats SET status = 'approved' WHERE id = ?");
        $stmt->execute([$boat_id]);
        
        echo "<h1>Boot genehmigt!</h1>";
        echo "<p>Die Bootmeldung wurde erfolgreich genehmigt.</p>";
        echo "<p><a href='race_creator.php'>← Zurück zur Rennenerstellung</a></p>";
        echo "<p><a href='registration_admin.php'>← Zurück zum Meldeportal Admin</a></p>";
        
    } catch (Exception $e) {
        echo "<h1>Fehler!</h1>";
        echo "<p>Fehler beim Genehmigen: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h1>Keine ID angegeben</h1>";
    echo "<p><a href='race_creator.php'>← Zurück zur Rennenerstellung</a></p>";
}
?> 