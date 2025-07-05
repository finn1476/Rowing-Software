<?php
// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Korrekte Pfadangabe zur auth.php
require_once __DIR__ . '/../config/auth.php';
$loginError = '';

// Wenn bereits eingeloggt, zur Admin-Seite weiterleiten
if (isAdminLoggedIn()) {
    $redirectUrl = isset($_SESSION['redirect_after_login']) 
        ? $_SESSION['redirect_after_login'] 
        : 'index.php?page=admin';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirectUrl);
    exit;
}

// Login-Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Debug-Info auskommentiert für Produktion
    // echo "<div style='background-color: #f8d7da; padding: 10px; margin-bottom: 10px;'>Debug: Input-Passwort='$password', erwartet='" . ADMIN_PASSWORD . "'</div>";
    
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $redirectUrl = isset($_SESSION['redirect_after_login']) 
            ? $_SESSION['redirect_after_login'] 
            : 'index.php?page=admin';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $loginError = 'Falsches Passwort. Bitte versuchen Sie es erneut.';
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3>Admin Login</h3>
                </div>
                <div class="card-body">
                    <?php if ($loginError): ?>
                        <div class="alert alert-danger">
                            <?php echo $loginError; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label for="password" class="form-label">Passwort</label>
                            <input type="password" class="form-control" id="password" name="password" required autofocus>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Anmelden</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-muted">
                    <a href="index.php" class="btn btn-link">Zurück zur Startseite</a>
                </div>
            </div>
        </div>
    </div>
</div> 