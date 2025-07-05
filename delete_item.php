<?php
require_once 'config/database.php';
session_start();

// Initialisiere die Datenbankverbindung
$conn = getDbConnection();

// Security check: Make sure table and id parameters are present
if (!isset($_GET['table']) || !isset($_GET['id'])) {
    $_SESSION['message'] = 'Missing required parameters';
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php?page=admin');
    exit;
}

$table = $_GET['table'];
$id = (int) $_GET['id'];

// Validate table to prevent SQL injection
$validTables = ['years', 'events', 'races', 'teams', 'participants', 'race_participants', 'distance_times'];
if (!in_array($table, $validTables)) {
    $_SESSION['message'] = 'Invalid table specified';
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php?page=admin');
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Check for foreign key constraints before deleting
    $hasRelatedItems = false;
    
    // Check for related items based on table
    if ($table === 'years') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE year_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $hasRelatedItems = true;
            $relatedItems = 'events';
        }
    } else if ($table === 'events') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM races WHERE event_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $hasRelatedItems = true;
            $relatedItems = 'races';
        }
    } else if ($table === 'races') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM race_participants WHERE race_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $hasRelatedItems = true;
            $relatedItems = 'race participants';
        }
    } else if ($table === 'teams') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM participants WHERE team_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $hasRelatedItems = true;
            $relatedItems = 'participants';
        }
    } else if ($table === 'participants') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM race_participants WHERE participant_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $hasRelatedItems = true;
            $relatedItems = 'race participants';
        }
    } else if ($table === 'race_participants') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM distance_times WHERE race_participant_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $hasRelatedItems = true;
            $relatedItems = 'distance times';
        }
    }
    
    // Stop if foreign key constraints exist
    if ($hasRelatedItems) {
        $_SESSION['message'] = "Cannot delete: This item has related {$relatedItems}. Please delete those first.";
        $_SESSION['message_type'] = 'danger';
        header('Location: index.php?page=admin');
        exit;
    }
    
    // Execute the delete
    $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        $conn->commit();
        $_SESSION['message'] = 'Item deleted successfully';
        $_SESSION['message_type'] = 'success';
    } else {
        $conn->rollBack();
        $_SESSION['message'] = 'Item not found or already deleted';
        $_SESSION['message_type'] = 'warning';
    }
} catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['message'] = 'Error deleting item: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

// Redirect back to admin page
header('Location: index.php?page=admin');
exit;
?> 