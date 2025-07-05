<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rowing Regatta Management</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php
    // Session starten
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    ?>
    <div class="container">
        <header class="py-3 mb-4 border-bottom">
            <h1 class="text-center">Rowing Regatta Management</h1>
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav">
                            <li class="nav-item">
                                <a class="nav-link" href="index.php">Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="index.php?page=upcoming_races">Upcoming Races</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="index.php?page=historical_data">Historical Data</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="index.php?page=admin">Admin</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

        <main>
            <?php
            include_once 'config/database.php';
            
            $page = isset($_GET['page']) ? $_GET['page'] : 'home';
            
            switch ($page) {
                case 'upcoming_races':
                    include_once 'pages/upcoming_races.php';
                    break;
                case 'results':
                    include_once 'pages/results.php';
                    break;
                case 'historical_data':
                    include_once 'pages/historical_data.php';
                    break;
                case 'admin_login':
                    include_once 'pages/admin_login.php';
                    break;
                case 'admin':
                    include_once 'pages/admin.php';
                    break;
                default:
                    include_once 'pages/home.php';
                    break;
            }
            ?>
        </main>

        <footer class="py-3 my-4 border-top">
            <p class="text-center text-muted">© 2023 Rowing Regatta Management</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>
</html> 
