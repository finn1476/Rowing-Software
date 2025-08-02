<?php
header('Content-Type: application/json');
require_once 'config/database.php';

$conn = getDbConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'events':
            $stmt = $conn->query("SELECT * FROM registration_events WHERE is_active = 1 ORDER BY event_date ASC");
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $events]);
            break;
            
        case 'boats':
            $event_id = $_GET['event_id'] ?? null;
            $where_clause = "";
            $params = [];
            
            if ($event_id) {
                $where_clause = "WHERE rb.event_id = ?";
                $params[] = $event_id;
            }
            
            $sql = "SELECT rb.*, re.name as event_name FROM registration_boats rb 
                   LEFT JOIN registration_events re ON rb.event_id = re.id 
                   $where_clause ORDER BY rb.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $boats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($boats as &$boat) {
                if ($boat['crew_members']) {
                    $boat['crew_members'] = json_decode($boat['crew_members'], true);
                }
            }
            
            echo json_encode(['success' => true, 'data' => $boats]);
            break;
            
        case 'singles':
            $event_id = $_GET['event_id'] ?? null;
            $where_clause = "";
            $params = [];
            
            if ($event_id) {
                $where_clause = "WHERE rs.event_id = ?";
                $params[] = $event_id;
            }
            
            $sql = "SELECT rs.*, re.name as event_name FROM registration_singles rs 
                   LEFT JOIN registration_events re ON rs.event_id = re.id 
                   $where_clause ORDER BY rs.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $singles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($singles as &$single) {
                if ($single['preferred_boat_types']) {
                    $single['preferred_boat_types'] = json_decode($single['preferred_boat_types'], true);
                }
            }
            
            echo json_encode(['success' => true, 'data' => $singles]);
            break;
            
        case 'stats':
            // Get registration statistics
            $stats = [];
            
            // Total events
            $stmt = $conn->query("SELECT COUNT(*) as count FROM registration_events WHERE is_active = 1");
            $stats['total_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Total boat registrations
            $stmt = $conn->query("SELECT COUNT(*) as count FROM registration_boats");
            $stats['total_boats'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Total single registrations
            $stmt = $conn->query("SELECT COUNT(*) as count FROM registration_singles");
            $stats['total_singles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Pending boat registrations
            $stmt = $conn->query("SELECT COUNT(*) as count FROM registration_boats WHERE status = 'pending'");
            $stats['pending_boats'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Pending single registrations
            $stmt = $conn->query("SELECT COUNT(*) as count FROM registration_singles WHERE status = 'pending'");
            $stats['pending_singles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Boat types distribution
            $stmt = $conn->query("SELECT boat_type, COUNT(*) as count FROM registration_boats GROUP BY boat_type");
            $stats['boat_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 