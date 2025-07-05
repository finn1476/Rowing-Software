<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';
$loginError = '';

if (isMarkerLoggedIn()) {
    $redirectUrl = isset($_SESSION['redirect_after_login_marker']) 
        ? $_SESSION['redirect_after_login_marker'] 
        : '../marker_time.php';
    unset($_SESSION['redirect_after_login_marker']);
    header('Location: ' . $redirectUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if ($password === MARKER_PASSWORD) {
        $_SESSION['marker_logged_in'] = true;
        $redirectUrl = isset($_SESSION['redirect_after_login_marker']) 
            ? $_SESSION['redirect_after_login_marker'] 
            : '../marker_time.php';
        unset($_SESSION['redirect_after_login_marker']);
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $loginError = 'Falsches Passwort. Bitte versuchen Sie es erneut.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Zeitnehmer Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #e5f6ff; font-family: Arial, sans-serif; }
        .login-container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px #0002; padding: 36px 32px; }
        .login-header { text-align: center; margin-bottom: 24px; }
        .login-header .icon { font-size: 3.5rem; margin-bottom: 8px; }
        .login-header h3 { margin: 0; color: #0074B7; font-size: 2rem; }
        .login-form label { font-weight: bold; color: #0074B7; }
        .login-form input[type="password"] { width: 100%; padding: 12px; font-size: 1.2rem; border-radius: 8px; border: 1px solid #b3d8f6; margin-bottom: 18px; }
        .login-form button { width: 100%; background: #0074B7; color: #fff; border: none; padding: 14px; border-radius: 8px; font-size: 1.2rem; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .login-form button:hover { background: #005a8c; }
        .login-footer { text-align: center; margin-top: 18px; color: #888; }
        .alert { background: #ffe0e0; color: #b00; border-radius: 8px; padding: 10px; margin-bottom: 18px; text-align: center; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <div class="icon">⏱️</div>
        <h3>Zeitnehmer Login</h3>
    </div>
    <form method="post" class="login-form">
        <?php if ($loginError): ?>
            <div class="alert"><?php echo $loginError; ?></div>
        <?php endif; ?>
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
        <button type="submit">Anmelden</button>
    </form>
    <div class="login-footer">
        <a href="../index.php">Zurück zur Startseite</a>
    </div>
</div>
</body>
</html> 