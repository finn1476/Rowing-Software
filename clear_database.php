<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Überprüfe ob der Benutzer als Admin eingeloggt ist
redirectToLoginIfNotAdmin();

// Initialisiere die Datenbankverbindung
$conn = getDbConnection();

// Check if all three passwords are provided and correct
if (isset($_POST['password1']) && isset($_POST['password2']) && isset($_POST['password3'])) {
    $password1 = $_POST['password1'];
    $password2 = $_POST['password2'];
    $password3 = $_POST['password3'];
    
    if ($password1 === DB_CLEAR_PASSWORD_1 && 
        $password2 === DB_CLEAR_PASSWORD_2 && 
        $password3 === DB_CLEAR_PASSWORD_3) {
        
        try {
            // Temporarily disable foreign key checks
            $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Order of tables to truncate to avoid foreign key constraint issues
            $tables = [
                'distance_times',
                'race_participants',
                'participants',
                'races',
                'events',
                'teams',
                'years',
                // Registration system tables
                'registration_singles',
                'registration_boats',
                'registration_events'
            ];
            
            // Truncate each table
            foreach ($tables as $table) {
                $conn->exec("TRUNCATE TABLE {$table}");
            }
            
            // Re-enable foreign key checks
            $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Set success message
            $_SESSION['message'] = "Datenbank erfolgreich geleert";
            $_SESSION['message_type'] = "success";
            
            // Redirect back to admin page
            header("Location: index.php?page=admin");
            exit;
            
        } catch (PDOException $e) {
            // Set error message
            $_SESSION['message'] = "Fehler beim Leeren der Datenbank: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
            
            // Redirect back to admin page
            header("Location: index.php?page=admin");
            exit;
        }
    } else {
        $error = "Ein oder mehrere Passwörter sind falsch. Alle drei Passwörter müssen korrekt sein.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank leeren</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h2><i class="bi bi-exclamation-triangle"></i> Datenbank leeren</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h4 class="alert-heading">Warnung!</h4>
                    <p>Sie sind dabei, ALLE Daten aus der Datenbank zu löschen. Diese Aktion kann nicht rückgängig gemacht werden.</p>
                    <p>Die folgenden Tabellen werden geleert:</p>
                    <ul>
                        <li>Jahre</li>
                        <li>Veranstaltungen</li>
                        <li>Rennen</li>
                        <li>Teams</li>
                        <li>Teilnehmer</li>
                        <li>Rennteilnehmer</li>
                        <li>Distanzzeiten</li>
                        <li>Registrierungs-Events</li>
                        <li>Boot-Registrierungen</li>
                        <li>Einzel-Registrierungen</li>
                    </ul>
                    <p>Die Datenbankstruktur bleibt erhalten, aber alle Daten werden entfernt.</p>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger mt-3">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="post" class="mt-4">
                    <h4>Sicherheitsbestätigung</h4>
                    <p>Bitte geben Sie die drei Sicherheitspasswörter ein, um das Löschen zu bestätigen:</p>
                    
                    <div class="mb-3">
                        <label for="password1" class="form-label">Passwort 1</label>
                        <input type="password" class="form-control" id="password1" name="password1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password2" class="form-label">Passwort 2</label>
                        <input type="password" class="form-control" id="password2" name="password2" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password3" class="form-label">Passwort 3</label>
                        <input type="password" class="form-control" id="password3" name="password3" required>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php?page=admin" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Ja, alle Daten löschen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
?> 