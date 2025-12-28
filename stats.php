<?php
require_once 'config.php';

// Configuration de la page
$pageTitle = 'Statistiques - Allaitement';
$additionalCSS = ['css/stats.css'];
$useCharts = true;
$additionalJS = ['js/charts.js'];

$pdo = getDB();

// Récupérer la période sélectionnée
$periode = $_GET['periode'] ?? '30';
$wherePeriode = $periode !== 'all' ? "WHERE date_debut >= DATE_SUB(NOW(), INTERVAL $periode DAY)" : "";

// Récupérer toutes les séances
$seances = $pdo->query("
    SELECT *, 
           DATE_FORMAT(date_debut, '%d/%m à %H:%i') as debut_format,
           DATE_FORMAT(date_fin, '%H:%i') as fin_format,
           HOUR(date_debut) as heure_debut
    FROM seances 
    $wherePeriode
    ORDER BY date_debut DESC
")->fetchAll();

// Statistiques globales
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_seances,
        SUM(CASE WHEN sein = 'gauche' THEN 1 ELSE 0 END) as total_gauche,
        SUM(CASE WHEN sein = 'droit' THEN 1 ELSE 0 END) as total_droit,
        AVG(duree_minutes) as duree_moyenne,
        SUM(duree_minutes) as duree_totale,
        MIN(duree_minutes) as duree_min,
        MAX(duree_minutes) as duree_max
    FROM seances
    $wherePeriode
")->fetch();

// Statistiques par jour
$parJour = $pdo->query("
    SELECT 
        DATE(date_debut) as jour,
        COUNT(*) as nb_seances,
        SUM(duree_minutes) as duree_totale,
        AVG(duree_minutes) as duree_moyenne
    FROM seances
    $wherePeriode
    GROUP BY DATE(date_debut)
    ORDER BY jour DESC
    LIMIT 14
")->fetchAll();

// Dernière séance par sein
$derniereGauche = $pdo->query("
    SELECT DATE_FORMAT(date_debut, '%d/%m à %H:%i') as derniere
    FROM seances 
    WHERE sein = 'gauche' 
    ORDER BY date_debut DESC 
    LIMIT 1
")->fetch();

$derniereDroit = $pdo->query("
    SELECT DATE_FORMAT(date_debut, '%d/%m à %H:%i') as derniere
    FROM seances 
    WHERE sein = 'droit' 
    ORDER BY date_debut DESC 
    LIMIT 1
")->fetch();

// Calcul de l'équilibre gauche/droit
$ratio_gauche = $stats['total_seances'] > 0 ? ($stats['total_gauche'] / $stats['total_seances']) * 100 : 0;
$ratio_droit = $stats['total_seances'] > 0 ? ($stats['total_droit'] / $stats['total_seances']) * 100 : 0;
$desequilibre = abs($ratio_gauche - $ratio_droit);

// Espacement entre séances
$espacements = [];
if (count($seances) > 1) {
    for ($i = 0; $i < count($seances) - 1; $i++) {
        $current = strtotime($seances[$i]['date_debut']);
        $next = strtotime($seances[$i + 1]['date_debut']);
        $diff = ($current - $next) / 60; // en minutes
        if ($diff > 0) {
            $espacements[] = $diff;
        }
    }
}

$espacement_moyen = count($espacements) > 0 ? round(array_sum($espacements) / count($espacements)) : 0;
$espacement_max = count($espacements) > 0 ? max($espacements) : 0;
$espacement_min = count($espacements) > 0 ? min($espacements) : 0;

// Distribution horaire
$heures = [];
foreach ($seances as $s) {
    $heure = $s['heure_debut'];
    if (!isset($heures[$heure])) {
        $heures[$heure] = 0;
    }
    $heures[$heure]++;
}
ksort($heures);

// Tendance (derniers 7 jours)
$tendance = $pdo->query("
    SELECT 
        DATE(date_debut) as jour,
        AVG(duree_minutes) as duree_moyenne
    FROM seances
    WHERE date_debut >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(date_debut)
    ORDER BY jour ASC
")->fetchAll();

// Détection d'alertes
$alertes = [];

// Alerte repos prolongé
$derniereSeance = $pdo->query("
    SELECT MAX(date_fin) as derniere_fin FROM seances
")->fetch();

if ($derniereSeance && $derniereSeance['derniere_fin']) {
    $timeDiff = (time() - strtotime($derniereSeance['derniere_fin'])) / 3600;
    if ($timeDiff > 5) {
        $alertes[] = [
            'type' => 'warning',
            'icon' => '⏱️',
            'title' => 'Repos prolongé',
            'message' => 'Dernière tétée il y a ' . round($timeDiff, 1) . 'h'
        ];
    }
}

// Alerte déséquilibre
if ($stats['total_seances'] > 10 && $desequilibre > 10) {
    $dominant = $ratio_gauche > 50 ? 'gauche' : 'droit';
    $alertes[] = [
        'type' => 'info',
        'icon' => '⚖️',
        'title' => 'Déséquilibre détecté',
        'message' => "Préférence pour le sein $dominant (" . round($desequilibre) . "% d'écart)"
    ];
}

// Alerte tendance positive
if (count($tendance) >= 3) {
    $tendanceCopy = $tendance;
    $dernier = array_pop($tendanceCopy);
    $avant = array_shift($tendanceCopy);
    if ($dernier['duree_moyenne'] > $avant['duree_moyenne']) {
        $diff = round($dernier['duree_moyenne'] - $avant['duree_moyenne']);
        $alertes[] = [
            'type' => 'success',
            'icon' => '📈',
            'title' => 'Tendance positive',
            'message' => "Durée moyenne en augmentation de $diff min"
        ];
    }
}

// Inclure le header
include 'includes/header.php';
?>

<div class="container large">
    <div class="card compact">
        <h1>📊 Statistiques d'Allaitement</h1>
        
        <div class="periode-selector">
            <a href="?periode=7" class="periode-btn <?= $periode == '7' ? 'active' : '' ?>">7 jours</a>
            <a href="?periode=30" class="periode-btn <?= $periode == '30' ? 'active' : '' ?>">30 jours</a>
            <a href="?periode=90" class="periode-btn <?= $periode == '90' ? 'active' : '' ?>">90 jours</a>
            <a href="?periode=all" class="periode-btn <?= $periode == 'all' ? 'active' : '' ?>">Tout</a>
        </div>
        
        <?php if ($stats['total_seances'] > 0): ?>
            
            <!-- Alertes -->
            <?php if (!empty($alertes)): ?>
            <div class="alertes">
                <?php foreach ($alertes as $alerte): ?>
                <div class="alerte <?= $alerte['type'] ?>">
                    <div class="alerte-icon"><?= $alerte['icon'] ?></div>
                    <div class="alerte-content">
                        <strong><?= htmlspecialchars($alerte['title']) ?></strong>
                        <?= htmlspecialchars($alerte['message']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- KPIs Principaux -->
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?= $stats['total_seances'] ?></div>
                    <div class="stat-label">Séances</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= round($stats['duree_moyenne']) ?> min</div>
                    <div class="stat-label">Durée moy.</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= round($stats['duree_totale'] / 60, 1) ?>h</div>
                    <div class="stat-label">Temps total</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= $espacement_moyen ?> min</div>
                    <div class="stat-label">Espacement moy.</div>
                </div>
            </div>

            <!-- Dernières Séances par Sein -->
            <div class="derniere-seance">
                <div class="derniere-box gauche">
                    <strong>👈 Dernier gauche</strong>
                    <span><?= $derniereGauche ? htmlspecialchars($derniereGauche['derniere']) : 'N/A' ?></span>
                </div>
                <div class="derniere-box droit">
                    <strong>👉 Dernier droit</strong>
                    <span><?= $derniereDroit ? htmlspecialchars($derniereDroit['derniere']) : 'N/A' ?></span>
                </div>
            </div>

            <!-- Équilibre Gauche/Droit -->
            <div class="insight-box">
                <strong>⚖️ Équilibre Sein Gauche/Droit</strong><br>
                Écart: <?= round($desequilibre, 1) ?>%
                <?php if ($desequilibre <= 5): ?>
                ✅ Excellent équilibre
                <?php elseif ($desequilibre <= 10): ?>
                ✓ Bon équilibre
                <?php else: ?>
                ⚠️ Préférence notable
                <?php endif; ?>
            </div>

            <div class="equilibre-barre">
                <div class="equilibre-gauche" style="width: <?= round($ratio_gauche) ?>%">
                    <?= round($ratio_gauche) ?>%
                </div>
                <div class="equilibre-droit" style="width: <?= round($ratio_droit) ?>%">
                    <?= round($ratio_droit) ?>%
                </div>
            </div>

            <!-- Espacement des Séances -->
            <div class="espacement-stats">
                <strong>🕐 Espacement entre séances</strong>
                <div class="espacement-row">
                    <div class="espacement-item">
                        <strong>Minimum</strong>
                        <span><?= round($espacement_min) ?> min</span>
                    </div>
                    <div class="espacement-item">
                        <strong>Moyenne</strong>
                        <span><?= $espacement_moyen ?> min</span>
                    </div>
                    <div class="espacement-item">
                        <strong>Maximum</strong>
                        <span><?= round($espacement_max) ?> min</span>
                    </div>
                </div>
            </div>

            <!-- Graphique de Tendance (7 derniers jours) -->
            <?php if (count($tendance) > 1): ?>
            <div style="margin-bottom: 20px;">
                <h2>📈 Tendance (7 derniers jours)</h2>
                <div class="chart-container">
                    <canvas id="tendanceChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Graphique Distribution Horaire -->
            <?php if (!empty($heures)): ?>
            <div style="margin-bottom: 20px;">
                <h2>🕐 Distribution horaire</h2>
                <div class="chart-container">
                    <canvas id="horaireChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Répartition par sein -->
            <div style="margin-bottom: 20px;">
                <h2>Répartition par sein</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?= $stats['total_gauche'] ?></div>
                        <div class="stat-label">👈 Gauche</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= $stats['total_droit'] ?></div>
                        <div class="stat-label">👉 Droit</div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-state">
                Aucune séance enregistrée pour cette période
            </div>
        <?php endif; ?>
        
        <a href="index.php" class="btn">← Nouvelle séance</a>
    </div>
    
    <?php if (!empty($parJour)): ?>
    <div class="card compact">
        <h2>📅 Par jour</h2>
        <?php foreach ($parJour as $jour): ?>
            <div class="jour-stats">
                <div>
                    <strong><?= date('d/m/Y', strtotime($jour['jour'])) ?></strong>
                    <span style="color: #666; margin-left: 10px;">
                        <?= $jour['nb_seances'] ?> séance<?= $jour['nb_seances'] > 1 ? 's' : '' ?>
                    </span>
                </div>
                <div>
                    <strong><?= round($jour['duree_totale']) ?> min</strong>
                    <span style="color: #666; margin-left: 10px;">
                        (moy: <?= round($jour['duree_moyenne']) ?> min)
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($seances)): ?>
    <div class="card compact">
        <h2>📋 Historique</h2>
        <?php foreach (array_slice($seances, 0, 20) as $seance): ?>
            <div class="seance">
                <div class="seance-info">
                    <div class="seance-date">
                        <?= htmlspecialchars($seance['debut_format']) ?> → <?= htmlspecialchars($seance['fin_format']) ?>
                    </div>
                    <div class="seance-details">Durée: <?= $seance['duree_minutes'] ?> minutes</div>
                </div>
                <span class="seance-sein sein-<?= $seance['sein'] ?>">
                    <?= $seance['sein'] === 'gauche' ? '👈' : '👉' ?> 
                    <?= ucfirst($seance['sein']) ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Initialisation des graphiques après chargement de Chart.js
document.addEventListener('DOMContentLoaded', function() {
    
    <?php if (count($tendance) > 1): ?>
    // Graphique de Tendance
    const tendanceLabels = [
        <?php foreach ($tendance as $t): ?>
        '<?= date('d/m', strtotime($t['jour'])) ?>',
        <?php endforeach; ?>
    ];
    
    const tendanceData = [
        <?php foreach ($tendance as $t): ?>
        <?= round($t['duree_moyenne']) ?>,
        <?php endforeach; ?>
    ];
    
    createTendanceChart(tendanceLabels, tendanceData);
    <?php endif; ?>

    <?php if (!empty($heures)): ?>
    // Graphique Distribution Horaire
    const horaireData = [
        <?php for ($h = 0; $h < 24; $h++): ?>
        <?= isset($heures[$h]) ? $heures[$h] : 0 ?>,
        <?php endfor; ?>
    ];
    
    createHoraireChart(horaireData);
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>