<?php
require_once 'config.php';

// Configuration de la page
$pageTitle = 'Suivi Allaitement';
$additionalJS = ['js/form.js'];

$message = '';
$error = '';
$warning = '';

// Récupérer la dernière séance
$pdo = getDB();
$derniereSeance = $pdo->query("
    SELECT *, 
           DATE_FORMAT(date_fin, '%d/%m à %H:%i') as fin_format,
           TIMESTAMPDIFF(MINUTE, date_fin, NOW()) as minutes_ecoulees
    FROM seances 
    ORDER BY date_fin DESC 
    LIMIT 1
")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dateDebut = $_POST['date_debut'] ?? '';
    $dateFin = $_POST['date_fin'] ?? '';
    $sein = $_POST['sein'] ?? '';
    $confirme = $_POST['confirme'] ?? '';
    
    if (empty($dateDebut) || empty($dateFin) || empty($sein)) {
        $error = "Tous les champs sont requis";
    } elseif (strtotime($dateFin) <= strtotime($dateDebut)) {
        $error = "La date de fin doit être après la date de début";
    } else {
        // Calculer la durée
        $dureeMinutes = (strtotime($dateFin) - strtotime($dateDebut)) / 60;
        
        // Vérifier les doublons (même sein dans les 5 dernières minutes)
        $checkDoublon = $pdo->prepare("
            SELECT COUNT(*) as nb 
            FROM seances 
            WHERE sein = ? 
            AND ABS(TIMESTAMPDIFF(MINUTE, date_debut, ?)) < 5
        ");
        $checkDoublon->execute([$sein, $dateDebut]);
        $doublon = $checkDoublon->fetch();
        
        if ($doublon['nb'] > 0) {
            $error = "⚠️ Une séance similaire existe déjà (même sein dans les 5 dernières minutes)";
        } elseif ($dureeMinutes > 45 && $confirme !== 'oui') {
            // Alerte durée anormale
            $warning = "⚠️ Cette séance dure " . round($dureeMinutes) . " minutes, ce qui est inhabituel. Confirmez-vous ?";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO seances (date_debut, date_fin, sein) VALUES (?, ?, ?)");
                $stmt->execute([$dateDebut, $dateFin, $sein]);
                $message = "Séance enregistrée avec succès !";
                
                // Recharger la dernière séance
                $derniereSeance = $pdo->query("
                    SELECT *, 
                           DATE_FORMAT(date_fin, '%d/%m à %H:%i') as fin_format,
                           TIMESTAMPDIFF(MINUTE, date_fin, NOW()) as minutes_ecoulees
                    FROM seances 
                    ORDER BY date_fin DESC 
                    LIMIT 1
                ")->fetch();
                
                // Réinitialiser le formulaire
                $_POST = [];
            } catch (PDOException $e) {
                $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }
}

// Fonction pour formater le temps écoulé
function formatTempsEcoule($minutes) {
    if ($minutes < 60) {
        return round($minutes) . " min";
    } elseif ($minutes < 1440) {
        $heures = floor($minutes / 60);
        $mins = $minutes % 60;
        return $heures . "h" . str_pad($mins, 2, '0', STR_PAD_LEFT);
    } else {
        $jours = floor($minutes / 1440);
        $heures = floor(($minutes % 1440) / 60);
        return $jours . "j " . $heures . "h";
    }
}

// Inclure le header
include 'includes/header.php';
?>

<div class="container">
    <?php if ($derniereSeance): ?>
    <div class="card derniere-tetee">
        <div class="derniere-tetee-titre">🍼 Dernière tétée</div>
        <div class="derniere-tetee-info">
            Il y a <?= formatTempsEcoule($derniereSeance['minutes_ecoulees']) ?>
        </div>
        <div class="derniere-tetee-details">
            <?= $derniereSeance['fin_format'] ?>
            <span class="sein-badge <?= $derniereSeance['sein'] ?>">
                <?= $derniereSeance['sein'] === 'gauche' ? '👈' : '👉' ?> 
                Sein <?= $derniereSeance['sein'] ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h1>🤱 Suivi Allaitement</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($warning): ?>
            <div class="confirmation-box">
                <p><?= htmlspecialchars($warning) ?></p>
                <div class="confirmation-actions">
                    <form method="POST" action="">
                        <input type="hidden" name="date_debut" value="<?= htmlspecialchars($_POST['date_debut']) ?>">
                        <input type="hidden" name="date_fin" value="<?= htmlspecialchars($_POST['date_fin']) ?>">
                        <input type="hidden" name="sein" value="<?= htmlspecialchars($_POST['sein']) ?>">
                        <input type="hidden" name="confirme" value="oui">
                        <button type="submit" class="btn btn-warning">✓ Confirmer</button>
                    </form>
                    <button type="button" class="btn btn-cancel" onclick="location.reload()">✗ Annuler</button>
                </div>
            </div>
        <?php else: ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="date_debut">Date et heure de début</label>
                <input type="datetime-local" id="date_debut" name="date_debut" required>
                <div class="quick-actions">
                    <button type="button" class="quick-btn" onclick="setNow('date_debut')">Maintenant</button>
                    <button type="button" class="quick-btn" onclick="setMinus('date_debut', 15)">-15min</button>
                    <button type="button" class="quick-btn" onclick="setMinus('date_debut', 30)">-30min</button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="date_fin">Date et heure de fin</label>
                <input type="datetime-local" id="date_fin" name="date_fin" required>
                <div class="quick-actions">
                    <button type="button" class="quick-btn" onclick="setNow('date_fin')">Maintenant</button>
                </div>
                <div id="duration_display" style="margin-top: 10px; font-weight: 600; text-align: center;"></div>
            </div>
            
            <div class="form-group">
                <label>Sein <?php if ($derniereSeance): ?>(suggéré: <?= $derniereSeance['sein'] === 'gauche' ? 'droit' : 'gauche' ?>)<?php endif; ?></label>
                <div class="sein-selector">
                    <div class="sein-option">
                        <input type="radio" id="gauche" name="sein" value="gauche" required>
                        <label for="gauche" <?= ($derniereSeance && $derniereSeance['sein'] === 'droit') ? 'class="suggestion"' : '' ?>>
                            👈 Gauche
                        </label>
                    </div>
                    <div class="sein-option">
                        <input type="radio" id="droit" name="sein" value="droit" required>
                        <label for="droit" <?= ($derniereSeance && $derniereSeance['sein'] === 'gauche') ? 'class="suggestion"' : '' ?>>
                            👉 Droit
                        </label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn">Enregistrer</button>
            <a href="stats.php" class="btn btn-secondary">Voir les statistiques</a>
        </form>
        
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>