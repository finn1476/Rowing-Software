<?php
// Include database connection
require_once 'config/database.php';

// Start session for message handling
session_start();

// Initialisiere die Datenbankverbindung
$conn = getDbConnection();

// Check if table and id parameters are present
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

// Define field specifications for each table
$fieldSpecs = [
    'years' => [
        'year' => ['label' => 'Year', 'type' => 'number', 'required' => true],
        'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false]
    ],
    'events' => [
        'year_id' => ['label' => 'Year', 'type' => 'select', 'required' => true, 'table' => 'years', 'value_field' => 'id', 'display_field' => 'year'],
        'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
        'event_date' => ['label' => 'Date', 'type' => 'date', 'required' => true],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false]
    ],
    'races' => [
        'event_id' => ['label' => 'Event', 'type' => 'select', 'required' => true, 'table' => 'events', 'value_field' => 'id', 'display_field' => 'name'],
        'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
        'start_time' => ['label' => 'Start Time', 'type' => 'time', 'required' => true],
        'distance' => ['label' => 'Distance (m)', 'type' => 'number', 'required' => true],
        'distance_markers' => ['label' => 'Distance Markers (comma separated)', 'type' => 'text', 'required' => false],
        'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['upcoming' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled']]
    ],
    'teams' => [
        'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => false]
    ],
    'participants' => [
        'team_id' => ['label' => 'Team', 'type' => 'select', 'required' => true, 'table' => 'teams', 'value_field' => 'id', 'display_field' => 'name'],
        'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
        'birth_year' => ['label' => 'Birth Year', 'type' => 'number', 'required' => false]
    ],
    'race_participants' => [
        'race_id' => ['label' => 'Race', 'type' => 'select', 'required' => true, 'table' => 'races', 'value_field' => 'id', 'display_field' => 'name'],
        'team_id' => ['label' => 'Team', 'type' => 'select', 'required' => true, 'table' => 'teams', 'value_field' => 'id', 'display_field' => 'name'],
        'participant_id' => ['label' => 'Participant', 'type' => 'select', 'required' => true, 'table' => 'participants', 'value_field' => 'id', 'display_field' => 'name'],
        'lane' => ['label' => 'Lane', 'type' => 'lane_select', 'required' => true],
        'finish_time' => ['label' => 'Finish Time (MM:SS.ms)', 'type' => 'text', 'required' => false],
        'finish_seconds' => ['label' => 'Finish Seconds', 'type' => 'number', 'required' => false, 'step' => '0.01'],
        'position' => ['label' => 'Position', 'type' => 'number', 'required' => false]
    ],
    'distance_times' => [
        'race_participant_id' => ['label' => 'Race Participant', 'type' => 'select', 'required' => true, 'table' => 'race_participants', 'value_field' => 'id', 'display_field' => 'id', 'custom_query' => true],
        'distance' => ['label' => 'Distance (m)', 'type' => 'number', 'required' => true],
        'time' => ['label' => 'Time (seconds)', 'type' => 'number', 'required' => true, 'step' => '0.01']
    ]
];

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate table name again
    if (!isset($_POST['table']) || !in_array($_POST['table'], $validTables)) {
        $_SESSION['message'] = 'Invalid table specified in form: ' . ($_POST['table'] ?? 'not set');
        $_SESSION['message_type'] = 'danger';
        header('Location: index.php?page=admin');
        exit;
    }
    
    $updateTable = $_POST['table'];
    $updateId = (int) $_POST['id'];
    
    // Debug: Zeige die erhaltenen POST-Daten
    $_SESSION['debug_post'] = $_POST;
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Build the SQL update statement
        $sql = "UPDATE {$updateTable} SET ";
        $params = [];
        $setClauses = [];
        
        // Get the field specs for this table
        $specs = $fieldSpecs[$updateTable];
        
        // Build the SET clauses for the SQL statement
        foreach ($specs as $field => $spec) {
            if (isset($_POST[$field])) {
                // Konvertiere leere Strings zu NULL für die Datenbank
                $value = $_POST[$field];
                
                // Wenn der Wert ein leerer String ist, in NULL umwandeln
                if ($value === '') {
                    $setClauses[] = "{$field} = NULL";
                } else {
                    $setClauses[] = "{$field} = ?";
                    $params[] = $value;
                }
            }
        }
        
        // Debug: Falls keine Felder gefunden wurden
        if (empty($setClauses)) {
            $_SESSION['message'] = 'No fields found to update. Available fields: ' . implode(', ', array_keys($specs));
            $_SESSION['message_type'] = 'warning';
            header('Location: edit_item.php?table=' . $updateTable . '&id=' . $updateId);
            exit;
        }
        
        // Add the SET clauses to the SQL statement
        $sql .= implode(', ', $setClauses);
        $sql .= " WHERE id = ?";
        $params[] = $updateId;
        
        // Debug: SQL-Statement speichern
        $_SESSION['debug_sql'] = $sql;
        $_SESSION['debug_params'] = $params;
        
        // Prepare and execute the statement
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            $conn->commit();
            $_SESSION['message'] = 'Item updated successfully';
            $_SESSION['message_type'] = 'success';
        } else {
            $conn->rollBack();
            $_SESSION['message'] = 'No changes were made. Data might be identical to existing values.';
            $_SESSION['message_type'] = 'warning';
        }
        
        // Redirect back to admin page
        header('Location: index.php?page=admin');
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['message'] = 'Error updating item: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
        header('Location: edit_item.php?table=' . $updateTable . '&id=' . $updateId);
        exit;
    }
}

// Fetch the item data
try {
    $stmt = $conn->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        $_SESSION['message'] = 'Item not found';
        $_SESSION['message_type'] = 'danger';
        header('Location: index.php?page=admin');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['message'] = 'Error fetching item: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php?page=admin');
    exit;
}

// Title case function for table names
function titleCase($string) {
    return ucwords(str_replace('_', ' ', $string));
}

// Get options for select fields
function getSelectOptions($conn, $spec, $currentValue = null) {
    if (isset($spec['options'])) {
        // Static options
        $options = [];
        foreach ($spec['options'] as $value => $text) {
            $selected = ($value == $currentValue) ? 'selected' : '';
            $options[] = "<option value=\"{$value}\" {$selected}>{$text}</option>";
        }
        return implode("\n", $options);
    } else if (isset($spec['table'])) {
        // Dynamic options from table
        $table = $spec['table'];
        $valueField = $spec['value_field'];
        $displayField = $spec['display_field'];
        
        // Custom query for race_participants
        if (isset($spec['custom_query']) && $spec['custom_query'] && $table === 'race_participants') {
            $sql = "SELECT rp.id, CONCAT(r.name, ' - ', t.name, ' (Lane ', rp.lane, ')') as display_text 
                    FROM race_participants rp 
                    JOIN races r ON rp.race_id = r.id 
                    JOIN teams t ON rp.team_id = t.id 
                    ORDER BY r.name, rp.lane";
            $stmt = $conn->query($sql);
            $options = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $selected = ($row['id'] == $currentValue) ? 'selected' : '';
                $options[] = "<option value=\"{$row['id']}\" {$selected}>{$row['display_text']}</option>";
            }
            return implode("\n", $options);
        }
        
        // Standard query
        $sql = "SELECT {$valueField}, {$displayField} FROM {$table} ORDER BY {$displayField}";
        $stmt = $conn->query($sql);
        $options = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $selected = ($row[$valueField] == $currentValue) ? 'selected' : '';
            $options[] = "<option value=\"{$row[$valueField]}\" {$selected}>{$row[$displayField]}</option>";
        }
        return implode("\n", $options);
    }
    return '';
}

// Generate the appropriate form field HTML
function generateFormField($conn, $fieldName, $spec, $value) {
    $required = $spec['required'] ? 'required' : '';
    $label = $spec['label'];
    $html = "<div class=\"mb-3\">\n";
    $html .= "  <label for=\"{$fieldName}\" class=\"form-label\">{$label}</label>\n";
    
    switch ($spec['type']) {
        case 'text':
            $html .= "  <input type=\"text\" class=\"form-control\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"" . htmlspecialchars($value ?? '') . "\" {$required}>\n";
            break;
        case 'textarea':
            $html .= "  <textarea class=\"form-control\" id=\"{$fieldName}\" name=\"{$fieldName}\" rows=\"3\" {$required}>" . htmlspecialchars($value ?? '') . "</textarea>\n";
            break;
        case 'number':
            $step = isset($spec['step']) ? "step=\"{$spec['step']}\"" : '';
            $html .= "  <input type=\"number\" class=\"form-control\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"" . htmlspecialchars($value ?? '') . "\" {$step} {$required}>\n";
            break;
        case 'date':
            // Format date value for HTML date input
            $dateValue = $value ? date('Y-m-d', strtotime($value)) : '';
            $html .= "  <input type=\"date\" class=\"form-control\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{$dateValue}\" {$required}>\n";
            break;
        case 'time':
            // Format time value for HTML time input
            $timeValue = $value ? date('H:i', strtotime($value)) : '';
            $html .= "  <input type=\"time\" class=\"form-control\" id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{$timeValue}\" {$required}>\n";
            break;
        case 'select':
            $html .= "  <select class=\"form-select\" id=\"{$fieldName}\" name=\"{$fieldName}\" {$required}>\n";
            $html .= "    <option value=\"\">-- Select --</option>\n";
            $html .= getSelectOptions($conn, $spec, $value);
            $html .= "  </select>\n";
            break;
        case 'lane_select':
            $html .= "  <select class=\"form-select\" id=\"{$fieldName}\" name=\"{$fieldName}\" {$required}>\n";
            $html .= "    <option value=\"\">Select Lane</option>\n";
            for ($i = 1; $i <= 9; $i++) {
                $selected = ($value == $i) ? 'selected' : '';
                $html .= "    <option value=\"{$i}\" {$selected}>{$i}</option>\n";
            }
            $html .= "  </select>\n";
            $html .= "  <div class=\"invalid-feedback\"></div>\n";
            break;
    }
    
    $html .= "</div>\n";
    return $html;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo titleCase($table); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h2>Edit <?php echo titleCase($table); ?></h2>
                <a href="index.php?page=admin" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left"></i> Back to Admin
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['debug_post']) || isset($_SESSION['debug_sql'])): ?>
                    <div class="alert alert-info mb-3">
                        <h5>Debug Information</h5>
                        <?php if (isset($_SESSION['debug_post'])): ?>
                            <div class="mb-2">
                                <strong>POST Data:</strong>
                                <pre><?php print_r($_SESSION['debug_post']); ?></pre>
                            </div>
                            <?php unset($_SESSION['debug_post']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['debug_sql'])): ?>
                            <div class="mb-2">
                                <strong>SQL Query:</strong>
                                <pre><?php echo $_SESSION['debug_sql']; ?></pre>
                                <strong>Parameters:</strong>
                                <pre><?php print_r($_SESSION['debug_params']); ?></pre>
                            </div>
                            <?php unset($_SESSION['debug_sql'], $_SESSION['debug_params']); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="edit_item.php?table=<?php echo $table; ?>&id=<?php echo $id; ?>">
                    <input type="hidden" name="table" value="<?php echo $table; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    
                    <?php
                    // Generate form fields based on field specifications
                    foreach ($fieldSpecs[$table] as $fieldName => $spec) {
                        echo generateFormField($conn, $fieldName, $spec, $item[$fieldName] ?? null);
                    }
                    ?>
                    
                    <div class="mt-4 d-flex justify-content-between">
                        <a href="index.php?page=admin" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php
    // Add JavaScript for lane validation
    if ($table === 'race_participants') {
        ?>
        <script>
            // Debug: Test API endpoints
            (function testApiEndpoints() {
                console.log("Testing API endpoints...");
                const testEndpoints = [
                    './check_lane.php',
                    '../check_lane.php',
                    '/check_lane.php',
                    'check_lane.php',
                    '/var/www/html/check_lane.php',
                    window.location.origin + '/check_lane.php'
                ];
                
                testEndpoints.forEach(url => {
                    fetch(url + '?race_id=1&lane=1&test=1')
                        .then(response => {
                            console.log(`Test endpoint ${url}: Status ${response.status}`);
                            return response.text();
                        })
                        .then(text => {
                            console.log(`Test endpoint ${url}: Response`, text.substring(0, 100) + (text.length > 100 ? '...' : ''));
                        })
                        .catch(error => {
                            console.error(`Test endpoint ${url}: Error`, error.message);
                        });
                });
            })();
            
            // Debugging-Informationen
            console.log("Lane validation script loaded for race_participants");
            
            document.addEventListener('DOMContentLoaded', function() {
                console.log("DOM fully loaded");
                
                const laneSelect = document.getElementById('lane');
                const raceIdField = document.getElementById('race_id');
                const itemIdField = document.getElementById('id');
                
                console.log("Lane select:", laneSelect);
                console.log("Race ID field:", raceIdField);
                console.log("Item ID:", itemIdField?.value);
                
                // Basis-URL für API-Aufrufe
                const baseUrl = window.location.href.split('?')[0].split('/').slice(0, -1).join('/');
                console.log("Base URL for API calls:", baseUrl);
                
                if (laneSelect && raceIdField) {
                    console.log("Found all required fields, setting up lane validation");
                    
                    // Initial und bei Änderung der Race ID verfügbare Lanes laden
                    setTimeout(loadAvailableLanes, 1000); // Längere Verzögerung
                    raceIdField.addEventListener('change', loadAvailableLanes);
                    
                    // Vor dem Absenden des Formulars prüfen, ob Lane verfügbar ist
                    document.querySelector('form').addEventListener('submit', function(event) {
                        const raceId = raceIdField.value;
                        const lane = laneSelect.value;
                        const itemId = itemIdField.value;
                        
                        if (!raceId || !lane) return;
                        
                        event.preventDefault(); // Form zunächst anhalten
                        
                        console.log("Checking lane availability before submit:", raceId, lane, itemId);
                        
                        // URLs für API-Tests
                        const apiUrls = [
                            window.location.origin + "/check_lane.php",
                            "/check_lane.php",
                            "./check_lane.php",
                            "../check_lane.php"
                        ];
                        
                        // Prüfen ob die Lane bereits belegt ist - erster Versuch mit Origin
                        console.log("Trying first API URL:", apiUrls[0]);
                        fetch(`${apiUrls[0]}?race_id=${raceId}&lane=${lane}&exclude_id=${itemId}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.statusText);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log("Lane check response:", data);
                                if (data.is_occupied) {
                                    laneSelect.classList.add('is-invalid');
                                    const feedback = laneSelect.nextElementSibling;
                                    feedback.textContent = `Lane ${lane} is already occupied by ${data.participant_name}`;
                                    
                                    // Zum Fehler scrollen
                                    laneSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                } else {
                                    // Formular absenden, wenn Lane frei ist
                                    console.log("Lane is available, submitting form");
                                    document.querySelector('form').submit();
                                }
                            })
                            .catch(error => {
                                console.error("Error checking lane with first API URL:", error);
                                
                                // Zweiter Versuch mit relativem Pfad
                                console.log("Trying second API URL:", apiUrls[1]);
                                fetch(`${apiUrls[1]}?race_id=${raceId}&lane=${lane}&exclude_id=${itemId}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        console.log("Lane check response (2nd attempt):", data);
                                        if (data.is_occupied) {
                                            laneSelect.classList.add('is-invalid');
                                            const feedback = laneSelect.nextElementSibling;
                                            feedback.textContent = `Lane ${lane} is already occupied by ${data.participant_name}`;
                                        } else {
                                            document.querySelector('form').submit();
                                        }
                                    })
                                    .catch(error2 => {
                                        console.error("Error checking lane with second API URL:", error2);
                                        alert("Lane check failed after multiple attempts. Form will be submitted anyway.");
                                        document.querySelector('form').submit();
                                    });
                            });
                    });
                } else {
                    console.error("Required elements not found for lane validation");
                    console.log("All form elements:", document.querySelectorAll('form select, form input'));
                }
                
                function loadAvailableLanes() {
                    const raceId = raceIdField.value;
                    const itemId = itemIdField.value;
                    
                    if (!raceId || !laneSelect) {
                        console.log("Cannot load lanes: missing race ID or lane select");
                        return;
                    }
                    
                    console.log("Loading available lanes for race:", raceId, "excluding:", itemId);
                    
                    // Status anzeigen
                    const statusDiv = document.createElement('div');
                    statusDiv.className = 'text-muted small mb-2';
                    statusDiv.id = 'lane-loading-status';
                    statusDiv.textContent = 'Loading available lanes...';
                    
                    const existingStatus = document.getElementById('lane-loading-status');
                    if (existingStatus) {
                        existingStatus.replaceWith(statusDiv);
                    } else {
                        laneSelect.parentNode.insertBefore(statusDiv, laneSelect.nextSibling.nextSibling);
                    }
                    
                    // API URLs
                    const apiUrls = [
                        window.location.origin + "/get_occupied_lanes.php",
                        "/get_occupied_lanes.php",
                        "./get_occupied_lanes.php",
                        "../get_occupied_lanes.php"
                    ];
                    
                    // AJAX-Anfrage, um belegte Lanes abzurufen - erster Versuch
                    console.log("Trying first API URL for lanes:", apiUrls[0]);
                    fetch(`${apiUrls[0]}?race_id=${raceId}&exclude_id=${itemId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok: ' + response.statusText);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log("Occupied lanes response:", data);
                            
                            // Aktueller ausgewählter Wert speichern
                            const currentValue = laneSelect.value;
                            
                            // Zurücksetzen aller Optionen
                            for (let i = 1; i <= 9; i++) {
                                const option = laneSelect.querySelector(`option[value="${i}"]`);
                                if (option) {
                                    option.disabled = false;
                                    option.textContent = i.toString();
                                }
                            }
                            
                            // Belegte Lanes deaktivieren
                            if (data.occupied_lanes && data.occupied_lanes.length > 0) {
                                data.occupied_lanes.forEach(lane => {
                                    const option = laneSelect.querySelector(`option[value="${lane.lane}"]`);
                                    if (option) {
                                        option.disabled = true;
                                        option.textContent = `${lane.lane} (${lane.participant_name})`;
                                    }
                                });
                                
                                statusDiv.textContent = `${data.occupied_lanes.length} lane(s) already occupied.`;
                            } else {
                                statusDiv.textContent = 'All lanes available.';
                            }
                            
                            // Ausgewählten Wert wiederherstellen, wenn möglich
                            if (currentValue && !laneSelect.querySelector(`option[value="${currentValue}"][disabled]`)) {
                                laneSelect.value = currentValue;
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching occupied lanes with first API URL:", error);
                            statusDiv.textContent = 'Trying alternative API URL...';
                            
                            // Zweiter Versuch mit relativem Pfad
                            console.log("Trying second API URL for lanes:", apiUrls[1]);
                            fetch(`${apiUrls[1]}?race_id=${raceId}&exclude_id=${itemId}`)
                                .then(response => response.json())
                                .then(data => {
                                    console.log("Occupied lanes response (2nd attempt):", data);
                                    
                                    // Aktueller ausgewählter Wert speichern
                                    const currentValue = laneSelect.value;
                                    
                                    // Zurücksetzen aller Optionen
                                    for (let i = 1; i <= 9; i++) {
                                        const option = laneSelect.querySelector(`option[value="${i}"]`);
                                        if (option) {
                                            option.disabled = false;
                                            option.textContent = i.toString();
                                        }
                                    }
                                    
                                    // Belegte Lanes deaktivieren
                                    if (data.occupied_lanes && data.occupied_lanes.length > 0) {
                                        data.occupied_lanes.forEach(lane => {
                                            const option = laneSelect.querySelector(`option[value="${lane.lane}"]`);
                                            if (option) {
                                                option.disabled = true;
                                                option.textContent = `${lane.lane} (${lane.participant_name})`;
                                            }
                                        });
                                        
                                        statusDiv.textContent = `${data.occupied_lanes.length} lane(s) already occupied.`;
                                    } else {
                                        statusDiv.textContent = 'All lanes available.';
                                    }
                                    
                                    // Ausgewählten Wert wiederherstellen, wenn möglich
                                    if (currentValue && !laneSelect.querySelector(`option[value="${currentValue}"][disabled]`)) {
                                        laneSelect.value = currentValue;
                                    }
                                })
                                .catch(error2 => {
                                    console.error("Error fetching occupied lanes with second API URL:", error2);
                                    statusDiv.textContent = 'Error loading lane information: Lane validation not available.';
                                });
                        });
                }
            });
        </script>
        <?php
    }
    ?>
</body>
</html> 