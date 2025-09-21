<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

header('Content-Type: application/json');

try {
    $conn = getDbConnection();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_race_participants':
            $race_id = $_GET['race_id'] ?? 0;
            
            // Get participants for a specific race (grouped by boat number and lane only)
            $stmt = $conn->prepare("
                SELECT 
                       rp.boat_number, 
                       rp.lane,
                       rb.club_name, 
                       rb.boat_name,
                       rb.id as registration_boat_id,
                       rb.crew_members,
                       GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', p.birth_year, ') - ', t.name) ORDER BY p.name SEPARATOR ', ') as participants
                FROM race_participants rp
                JOIN participants p ON rp.participant_id = p.id
                JOIN teams t ON rp.team_id = t.id
                LEFT JOIN registration_boats rb ON rp.registration_boat_id = rb.id
                WHERE rp.race_id = ?
                GROUP BY rp.boat_number, rp.lane, rb.club_name, rb.boat_name, rb.id, rb.crew_members
                ORDER BY rp.lane, rp.boat_number
            ");
            $stmt->execute([$race_id]);
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process participants to include individual crew member clubs
            foreach ($participants as &$participant) {
                if ($participant['crew_members']) {
                    $crew = json_decode($participant['crew_members'], true);
                    $crew_with_clubs = [];
                    
                    foreach ($crew as $member) {
                        $club = $member['club'] ?? 'Unbekannt';
                        $crew_with_clubs[] = $member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['birth_year'] . ') - ' . $club;
                    }
                    
                    $participant['crew_with_clubs'] = implode(', ', $crew_with_clubs);
                } else {
                    $participant['crew_with_clubs'] = $participant['participants'];
                }
            }
            
            echo json_encode(['success' => true, 'participants' => $participants]);
            break;
            
        case 'remove_boat_from_race':
            $race_id = $_POST['race_id'] ?? 0;
            $boat_number = $_POST['boat_number'] ?? 0;
            $lane = $_POST['lane'] ?? 0;
            
            // First, get the registration_boat_id before removing participants
            $stmt = $conn->prepare("SELECT DISTINCT rp.registration_boat_id 
                                   FROM race_participants rp 
                                   WHERE rp.race_id = ? AND rp.boat_number = ? AND rp.lane = ? 
                                   AND rp.registration_boat_id IS NOT NULL");
            $stmt->execute([$race_id, $boat_number, $lane]);
            $registration_boat_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Remove all participants for this specific boat (boat_number and lane)
            $stmt = $conn->prepare("DELETE rp FROM race_participants rp 
                                   WHERE rp.race_id = ? AND rp.boat_number = ? AND rp.lane = ?");
            $stmt->execute([$race_id, $boat_number, $lane]);
            
            // Mark specific registration boats as available again
            foreach ($registration_boat_ids as $registration_boat_id) {
                $stmt = $conn->prepare("UPDATE registration_boats SET status = 'approved' WHERE id = ? AND status = 'used'");
                $stmt->execute([$registration_boat_id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Boot erfolgreich aus Rennen entfernt']);
            break;
            
        case 'add_boat_to_race':
            $race_id = $_POST['race_id'] ?? 0;
            $registration_boat_id = $_POST['registration_boat_id'] ?? 0;
            
            // Get race details
            $stmt = $conn->prepare("SELECT * FROM races WHERE id = ?");
            $stmt->execute([$race_id]);
            $race = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$race) {
                echo json_encode(['success' => false, 'message' => 'Rennen nicht gefunden']);
                break;
            }
            
            // Get registration boat details
            $stmt = $conn->prepare("SELECT * FROM registration_boats WHERE id = ? AND status = 'approved'");
            $stmt->execute([$registration_boat_id]);
            $boat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$boat) {
                echo json_encode(['success' => false, 'message' => 'Boot nicht verfügbar']);
                break;
            }
            
            // Parse crew members with their individual clubs
            $crew_members = [];
            if ($boat['crew_members']) {
                $crew_members = json_decode($boat['crew_members'], true);
            }
            
            // Check if race already has 4 boats
            $stmt = $conn->prepare("SELECT COUNT(DISTINCT team_id) as boat_count FROM race_participants WHERE race_id = ?");
            $stmt->execute([$race_id]);
            $boat_count = $stmt->fetch(PDO::FETCH_ASSOC)['boat_count'];
            
            if ($boat_count >= 4) {
                echo json_encode(['success' => false, 'message' => 'Rennen ist bereits voll (max. 4 Boote)']);
                break;
            }
            
            // Get next boat number and lane
            $stmt = $conn->prepare("SELECT MAX(boat_number) as max_boat, MAX(lane) as max_lane FROM race_participants WHERE race_id = ?");
            $stmt->execute([$race_id]);
            $max_numbers = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $next_boat_number = ($max_numbers['max_boat'] ?? 0) + 1;
            $next_lane = ($max_numbers['max_lane'] ?? 0) + 1;
            
            // Add crew members as participants with their individual clubs
            if ($crew_members) {
                foreach ($crew_members as $member) {
                    $full_name = $member['first_name'] . ' ' . $member['last_name'];
                    $member_club = $member['club'] ?? 'Unbekannt';
                    
                    // Check if team for this club already exists
                    $stmt = $conn->prepare("SELECT id FROM teams WHERE name = ?");
                    $stmt->execute([$member_club]);
                    $existing_team = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_team) {
                        $team_id = $existing_team['id'];
                    } else {
                        // Create new team for this club
                        $stmt = $conn->prepare("INSERT INTO teams (name, description) VALUES (?, ?)");
                        $stmt->execute([$member_club, 'Club: ' . $member_club]);
                        $team_id = $conn->lastInsertId();
                    }
                    
                    // Check if participant already exists
                    $stmt = $conn->prepare("SELECT id FROM participants WHERE name = ? AND birth_year = ? AND team_id = ?");
                    $stmt->execute([$full_name, $member['birth_year'], $team_id]);
                    $existing_participant = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_participant) {
                        $participant_id = $existing_participant['id'];
                    } else {
                        // Create new participant
                        $stmt = $conn->prepare("INSERT INTO participants (team_id, name, birth_year) VALUES (?, ?, ?)");
                        $stmt->execute([$team_id, $full_name, $member['birth_year']]);
                        $participant_id = $conn->lastInsertId();
                    }
                    
                    // Add to race participants with registration_boat_id reference
                    $stmt = $conn->prepare("INSERT INTO race_participants (race_id, team_id, participant_id, boat_number, lane, registration_boat_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$race_id, $team_id, $participant_id, $next_boat_number, $next_lane, $registration_boat_id]);
                }
            }
            
            // Mark registration boat as used
            $stmt = $conn->prepare("UPDATE registration_boats SET status = 'used' WHERE id = ?");
            $stmt->execute([$registration_boat_id]);
            
            echo json_encode(['success' => true, 'message' => 'Boot erfolgreich zum Rennen hinzugefügt']);
            break;
            
        case 'get_available_boats':
            $event_id = $_GET['event_id'] ?? 0;
            $boat_type = $_GET['boat_type'] ?? '';
            
            // Get available boats for the event and boat type
            $stmt = $conn->prepare("SELECT * FROM registration_boats WHERE event_id = ? AND boat_type = ? AND status = 'approved' ORDER BY club_name");
            $stmt->execute([$event_id, $boat_type]);
            $boats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'boats' => $boats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?> 