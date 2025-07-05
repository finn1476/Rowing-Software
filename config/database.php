<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rowing_regatta');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create a database connection
function getDbConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Initialize the database if it doesn't exist
function initializeDatabase() {
    try {
        // First try to connect without specifying a database
        $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if database exists, if not create it
        $conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
        $conn->exec("USE " . DB_NAME);
        
        // Create tables if they don't exist
        $tables = [
            "CREATE TABLE IF NOT EXISTS years (
                id INT AUTO_INCREMENT PRIMARY KEY,
                year INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                year_id INT,
                name VARCHAR(255) NOT NULL,
                event_date DATE NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (year_id) REFERENCES years(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS races (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT,
                name VARCHAR(255) NOT NULL,
                start_time DATETIME,
                actual_start_time DATETIME DEFAULT NULL,
                distance INT NOT NULL,
                distance_markers VARCHAR(255) COMMENT 'Comma-separated distance markers in meters',
                participants_per_boat INT DEFAULT NULL COMMENT 'Number of participants per boat',
                status ENUM('upcoming', 'started', 'completed', 'cancelled') DEFAULT 'upcoming',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS teams (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                team_id INT,
                name VARCHAR(255) NOT NULL,
                birth_year INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
            )",
            
            "CREATE TABLE IF NOT EXISTS race_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                race_id INT,
                team_id INT,
                participant_id INT,
                lane INT,
                finish_time TIME(3),
                finish_seconds FLOAT,
                position INT,
                boat_number VARCHAR(32) DEFAULT NULL COMMENT 'Bugnummer',
                boat_seat INT DEFAULT NULL COMMENT 'Sitzplatz im Boot',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
                FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
                FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS distance_times (
                id INT AUTO_INCREMENT PRIMARY KEY,
                race_participant_id INT,
                distance INT NOT NULL COMMENT 'Distance in meters',
                time TIME(3) NOT NULL COMMENT 'Time at this distance',
                seconds_elapsed FLOAT COMMENT 'Total seconds elapsed at this distance',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (race_participant_id) REFERENCES race_participants(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_name VARCHAR(64) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS live_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role ENUM('start','marker','finish') NOT NULL,
                marker_distance VARCHAR(32) DEFAULT NULL,
                last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_role_marker (role, marker_distance)
            )"
        ];
        
        foreach ($tables as $sql) {
            $conn->exec($sql);
        }
        
        // Add finish_seconds column if it doesn't exist yet
        try {
            // Check if column exists
            $stmt = $conn->prepare("SHOW COLUMNS FROM race_participants LIKE 'finish_seconds'");
            $stmt->execute();
            $columnExists = $stmt->fetchColumn();
            
            if (!$columnExists) {
                // Add the column
                $conn->exec("ALTER TABLE race_participants ADD COLUMN finish_seconds FLOAT AFTER finish_time");
                
                // Update existing records to calculate finish_seconds from finish_time
                $stmt = $conn->prepare("SELECT id, finish_time FROM race_participants WHERE finish_time IS NOT NULL");
                $stmt->execute();
                $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($participants as $participant) {
                    $finishSeconds = timeToSeconds($participant['finish_time']);
                    $updateStmt = $conn->prepare("UPDATE race_participants SET finish_seconds = :finish_seconds WHERE id = :id");
                    $updateStmt->bindParam(':finish_seconds', $finishSeconds);
                    $updateStmt->bindParam(':id', $participant['id']);
                    $updateStmt->execute();
                }
            }
        } catch (PDOException $e) {
            // Log the error but continue with initialization
            error_log("Error checking/adding finish_seconds column: " . $e->getMessage());
        }
        
        // Add participants_per_boat column if it doesn't exist
        try {
            // Check if participants_per_boat column exists
            $stmt = $conn->prepare("SHOW COLUMNS FROM races LIKE 'participants_per_boat'");
            $stmt->execute();
            $columnExists = $stmt->fetchColumn();
            if (!$columnExists) {
                $conn->exec("ALTER TABLE races ADD COLUMN participants_per_boat INT DEFAULT NULL COMMENT 'Number of participants per boat' AFTER distance_markers");
            }
        } catch (PDOException $e) {
            error_log("Error checking/adding participants_per_boat column: " . $e->getMessage());
        }
        
        // Add boat_number column if it doesn't exist
        try {
            // Check if boat_number column exists
            $stmt = $conn->prepare("SHOW COLUMNS FROM race_participants LIKE 'boat_number'");
            $stmt->execute();
            $columnExists = $stmt->fetchColumn();
            if (!$columnExists) {
                $conn->exec("ALTER TABLE race_participants ADD COLUMN boat_number VARCHAR(32) DEFAULT NULL COMMENT 'Bugnummer' AFTER position");
            }
        } catch (PDOException $e) {
            error_log("Error checking/adding boat_number column: " . $e->getMessage());
        }
        
        // Add boat_seat column if it doesn't exist
        try {
            // Check if boat_seat column exists
            $stmt = $conn->prepare("SHOW COLUMNS FROM race_participants LIKE 'boat_seat'");
            $stmt->execute();
            $columnExists = $stmt->fetchColumn();
            if (!$columnExists) {
                $conn->exec("ALTER TABLE race_participants ADD COLUMN boat_seat INT DEFAULT NULL COMMENT 'Sitzplatz im Boot' AFTER boat_number");
            }
        } catch (PDOException $e) {
            error_log("Error checking/adding boat_seat column: " . $e->getMessage());
        }
        
        // Add actual_start_time column if it doesn't exist
        try {
            $stmt = $conn->prepare("SHOW COLUMNS FROM races LIKE 'actual_start_time'");
            $stmt->execute();
            $columnExists = $stmt->fetchColumn();
            if (!$columnExists) {
                $conn->exec("ALTER TABLE races ADD COLUMN actual_start_time DATETIME DEFAULT NULL AFTER start_time");
            }
        } catch (PDOException $e) {
            error_log("Error checking/adding actual_start_time column: " . $e->getMessage());
        }
        
        // Create indexes for performance
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_race_participant_race_id ON race_participants(race_id)",
            "CREATE INDEX IF NOT EXISTS idx_race_participant_team_id ON race_participants(team_id)",
            "CREATE INDEX IF NOT EXISTS idx_distance_times_race_participant_id ON distance_times(race_participant_id)",
            "CREATE INDEX IF NOT EXISTS idx_distance_times_distance ON distance_times(distance)"
        ];
        
        foreach ($indexes as $sql) {
            try {
                $conn->exec($sql);
            } catch (PDOException $e) {
                // Ignore index creation errors
            }
        }
        
        return true;
    } catch(PDOException $e) {
        die("Database initialization failed: " . $e->getMessage());
    }
}

// Initialize the database on first load
initializeDatabase();

// Function to convert time to minutes and seconds format
function formatRaceTime($timeStr) {
    if (empty($timeStr)) return 'N/A';
    // Wenn es schon im Format MM:SS.ms oder HH:MM:SS.ms ist, wie gehabt
    $timeParts = explode(':', $timeStr);
    if (count($timeParts) === 3) {
        $hours = (int)$timeParts[0];
        $minutes = (int)$timeParts[1];
        $seconds = (float)$timeParts[2];
        $totalSeconds = $hours * 3600 + $minutes * 60 + $seconds;
    } elseif (count($timeParts) === 2) {
        $minutes = (int)$timeParts[0];
        $seconds = (float)$timeParts[1];
        $totalSeconds = $minutes * 60 + $seconds;
    } else {
        // Wenn es nur Sekunden sind (z.B. 480.92)
        $totalSeconds = (float)$timeStr;
    }
    // Jetzt korrekt als MM:SS.ms ausgeben
    $minutes = floor($totalSeconds / 60);
    $seconds = $totalSeconds - ($minutes * 60);
    return sprintf('%d:%06.3f', $minutes, $seconds);
}

// Function to convert time string to seconds
function timeToSeconds($timeStr) {
    if (empty($timeStr)) return null;
    
    // Parse the time
    $timeParts = explode(':', $timeStr);
    
    // HH:MM:SS.ms format
    if (count($timeParts) === 3) {
        $hours = (int)$timeParts[0];
        $minutes = (int)$timeParts[1];
        $seconds = (float)$timeParts[2];
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
    
    // MM:SS.ms format
    if (count($timeParts) === 2) {
        $minutes = (int)$timeParts[0];
        $seconds = (float)$timeParts[1];
        return ($minutes * 60) + $seconds;
    }
    
    // Just seconds
    return (float)$timeStr;
}
?> 