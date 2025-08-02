<?php
require_once '../config/database.php';

$conn = getDbConnection();

// Get active events
$stmt = $conn->query("SELECT * FROM registration_events WHERE is_active = 1 ORDER BY event_date ASC");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get registration statistics
$stmt = $conn->query("SELECT COUNT(*) as total_boats FROM registration_boats");
$total_boats = $stmt->fetch(PDO::FETCH_ASSOC)['total_boats'];

$stmt = $conn->query("SELECT COUNT(*) as total_singles FROM registration_singles");
$total_singles = $stmt->fetch(PDO::FETCH_ASSOC)['total_singles'];
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3><i class="bi bi-clipboard-check"></i> Meldeportal</h3>
                </div>
                <div class="card-body">
                    <p class="lead">Melden Sie sich für die anstehenden Regatten an!</p>
                    
                    <?php if (empty($events)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Derzeit sind keine Rennen zur Anmeldung verfügbar.
                        </div>
                    <?php else: ?>
                        <h4>Verfügbare Rennen:</h4>
                        <div class="row">
                            <?php foreach ($events as $event): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($event['name']) ?></h5>
                                        <p class="card-text">
                                            <strong>Datum:</strong> <?= date('d.m.Y', strtotime($event['event_date'])) ?>
                                        </p>
                                        <?php if ($event['description']): ?>
                                            <p class="card-text"><?= htmlspecialchars($event['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4">
                            <a href="pages/registration.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-clipboard-plus"></i> Jetzt anmelden
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4><i class="bi bi-graph-up"></i> Anmeldestatistik</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-primary"><?= $total_boats ?></h3>
                                <p class="text-muted">Bootmeldungen</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success"><?= $total_singles ?></h3>
                            <p class="text-muted">Einzelmeldungen</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5>Anmeldemöglichkeiten:</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success"></i> Vollständige Bootmeldung</li>
                        <li><i class="bi bi-check-circle text-success"></i> Einzelmeldung für Bootvermittlung</li>
                        <li><i class="bi bi-check-circle text-success"></i> Verschiedene Boottypen (1x, 2x, 3x+, 4x)</li>
                        <li><i class="bi bi-check-circle text-success"></i> Einfache Online-Anmeldung</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <h4><i class="bi bi-question-circle"></i> Wie funktioniert's?</h4>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Wählen Sie ein Rennen aus</li>
                        <li>Entscheiden Sie sich für eine Anmeldung:
                            <ul>
                                <li><strong>Bootmeldung:</strong> Sie haben ein vollständiges Boot</li>
                                <li><strong>Einzelmeldung:</strong> Sie suchen noch Mitruderer</li>
                            </ul>
                        </li>
                        <li>Füllen Sie das Formular aus</li>
                        <li>Ihre Anmeldung wird geprüft und bestätigt</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div> 