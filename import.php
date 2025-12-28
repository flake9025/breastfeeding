<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$message = '';
$error = '';
$stats = [];
$debug = [];

function logDebug($msg) {
    global $debug;
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[$timestamp] $msg\n";
    file_put_contents('/tmp/import_debug.log', $logMsg, FILE_APPEND);
    $debug[] = $msg;
}

logDebug("===== DÉMARRAGE IMPORT =====");

// Import CSV - Format strict: Date;Heure de début;Heure de fin;Durée;Sein
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    logDebug("POST reçu avec import_csv");
    
    try {
        if (!isset($_FILES['csv_file'])) {
            throw new Exception('Aucun fichier fourni.');
        }
        
        logDebug("Fichier détecté: " . $_FILES['csv_file']['name']);
        logDebug("Erreur upload: " . $_FILES['csv_file']['error']);

        if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (php.ini)',
                UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (formulaire)',
                UPLOAD_ERR_PARTIAL => 'Fichier partiellement uploadé',
                UPLOAD_ERR_NO_FILE => 'Pas de fichier',
                UPLOAD_ERR_NO_TMP_DIR => 'Pas de répertoire temporaire',
                UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier',
                UPLOAD_ERR_EXTENSION => 'Extension bloquée'
            ];
            $errCode = $_FILES['csv_file']['error'];
            throw new Exception('Erreur upload: ' . ($uploadErrors[$errCode] ?? 'Erreur inconnue'));
        }

        $tmpFile = $_FILES['csv_file']['tmp_name'];
        logDebug("Fichier temporaire: $tmpFile");
        
        if (!file_exists($tmpFile)) {
            throw new Exception('Le fichier temporaire n\'existe pas.');
        }

        // Vérifier l'extension
        $filename = $_FILES['csv_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        logDebug("Extension détectée: $ext");
        
        if ($ext !== 'csv') {
            throw new Exception('Le fichier doit être un CSV (.csv).');
        }

        // Ouvrir en binaire et lire le contenu pour détecter l'encodage
        $fileContent = file_get_contents($tmpFile);
        if ($fileContent === false) {
            throw new Exception('Impossible de lire le fichier.');
        }
        
        logDebug("Taille du fichier: " . strlen($fileContent) . " bytes");

        // Détecter l'encodage
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'CP1252'], true);
        if ($encoding === false) {
            $encoding = 'UTF-8';
        }
        logDebug("Encodage détecté: $encoding");

        // Convertir en UTF-8 si nécessaire
        if ($encoding !== 'UTF-8') {
            logDebug("Conversion de $encoding vers UTF-8");
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
        }

        // Diviser en lignes
        $lines = explode("\n", $fileContent);
        if (empty($lines)) {
            throw new Exception('Fichier vide.');
        }

        // Traiter la première ligne (en-tête)
        $headerLine = trim($lines[0], "\r\n");
        logDebug("En-tête brut: " . htmlspecialchars($headerLine));

        // En-têtes possibles (gérer les variantes d'accents)
        $possibleHeaders = [
            'Date;Heure de début;Heure de fin;Durée;Sein',
            'Date;Heure de debut;Heure de fin;Duree;Sein'
        ];
        
        $headerValid = false;
        foreach ($possibleHeaders as $expected) {
            if ($headerLine === $expected) {
                logDebug("✓ En-tête valide: $expected");
                $headerValid = true;
                break;
            }
        }

        if (!$headerValid) {
            throw new Exception(
                "Format CSV invalide.\n" .
                "En-tête attendu: Date;Heure de début;Heure de fin;Durée;Sein\n" .
                "En-tête reçu: " . htmlspecialchars($headerLine)
            );
        }

        // Connexion BD
        try {
            $pdo = getDB();
            logDebug("Connexion BD établie ✓");
        } catch (Exception $e) {
            throw new Exception("Erreur connexion BDD: " . $e->getMessage());
        }

        $imported = 0;
        $errors = 0;
        $errorDetails = [];
        $separator = ';';

        // Traiter les lignes de données (à partir de l'index 1)
        for ($lineNumber = 2; $lineNumber < count($lines); $lineNumber++) {
            $line = trim($lines[$lineNumber], "\r\n");
            
            // Sauter les lignes vides
            if (empty($line)) {
                continue;
            }

            // Parser la ligne CSV
            $data = str_getcsv($line, $separator);
            
            // Nettoyer les espaces
            $data = array_map('trim', $data);

            // Vérifier qu'on a exactement 5 colonnes
            if (count($data) !== 5) {
                $errors++;
                $errorDetails[] = "Ligne $lineNumber: Nombre de colonnes incorrect (attendu 5, reçu " . count($data) . ")";
                logDebug("Ligne $lineNumber: Colonnes incorrectes (" . count($data) . ")");
                continue;
            }

            list($date, $heureDebut, $heureFin, $duree, $sein) = $data;

            // Vérifier que les champs essentiels ne sont pas vides
            if (empty($date) || empty($heureDebut) || empty($heureFin) || empty($sein)) {
                $errors++;
                $errorDetails[] = "Ligne $lineNumber: Des champs essentiels sont vides";
                continue;
            }

            // Normaliser le sein (accepter les deux formats)
            $seinLower = mb_strtolower($sein, 'UTF-8');
            if ($seinLower === 'gauche') {
                $sein = 'gauche';
            } elseif ($seinLower === 'droit') {
                $sein = 'droit';
            } else {
                $errors++;
                $errorDetails[] = "Ligne $lineNumber: Sein invalide ('$sein'). Doit être 'Gauche' ou 'Droit'";
                continue;
            }

            try {
                // Parser la date (format français: JJ/MM/YYYY)
                $dateObj = \DateTime::createFromFormat('d/m/Y', $date);
                if (!$dateObj) {
                    throw new Exception("Date invalide: '$date'");
                }
                $dateFormatted = $dateObj->format('Y-m-d');

                // Parser les heures (format HH:MM)
                $heureDebutObj = \DateTime::createFromFormat('H:i', $heureDebut);
                if (!$heureDebutObj) {
                    throw new Exception("Heure de début invalide: '$heureDebut'");
                }
                $heureDebut = $heureDebutObj->format('H:i:s');

                $heureFinObj = \DateTime::createFromFormat('H:i', $heureFin);
                if (!$heureFinObj) {
                    throw new Exception("Heure de fin invalide: '$heureFin'");
                }
                $heureFin = $heureFinObj->format('H:i:s');

                // Construire les datetime complets
                $datetimeDebut = "$dateFormatted $heureDebut";
                $datetimeFin = "$dateFormatted $heureFin";

                // Vérifier que la fin est après le début
                $timestampDebut = strtotime($datetimeDebut);
                $timestampFin = strtotime($datetimeFin);

                if ($timestampFin <= $timestampDebut) {
                    throw new Exception("Heure de fin ($heureFin) avant ou égale à l'heure de début ($heureDebut)");
                }

                // Insertion en base de données
                try {
                    $stmt = $pdo->prepare("INSERT INTO seances (date_debut, date_fin, sein) VALUES (?, ?, ?)");
                    $stmt->execute([$datetimeDebut, $datetimeFin, $sein]);
                    $imported++;
                    logDebug("Ligne $lineNumber: Importée ✓ ($sein)");
                } catch (PDOException $pdoEx) {
                    throw new Exception("Erreur BDD: " . $pdoEx->getMessage());
                }

            } catch (Exception $e) {
                $errors++;
                $errorDetails[] = "Ligne $lineNumber: " . $e->getMessage();
                logDebug("Ligne $lineNumber: ERREUR - " . $e->getMessage());
            }
        }

        $message = "✅ Import terminé ! $imported séances importées, $errors erreur(s).";
        $stats = [
            'imported' => $imported,
            'errors' => $errors,
            'details' => array_slice($errorDetails, 0, 10)
        ];
        
        logDebug("Import réussi: $imported importées, $errors erreurs");

    } catch (Exception $e) {
        $error = "❌ Erreur lors de l'import : " . $e->getMessage();
        logDebug("EXCEPTION: " . $e->getMessage());
    }
}

logDebug("===== FIN IMPORT =====");

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV - Allaitement</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 28px;
        }
        
        .message {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            background: #f8f9ff;
            border-color: #764ba2;
        }
        
        .upload-area input[type="file"] {
            display: none;
        }
        
        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 10px;
        }
        
        .btn:active {
            transform: scale(0.98);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #333;
        }
        
        .info-box li {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .info-box code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .stats-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat {
            background: #667eea;
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .file-name {
            margin-top: 10px;
            color: #667eea;
            font-weight: 600;
        }

        .error-details {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            font-size: 13px;
            color: #856404;
            max-height: 200px;
            overflow-y: auto;
        }

        .error-details ul {
            margin: 0;
            padding-left: 20px;
        }

        .error-details li {
            margin-bottom: 5px;
        }

        .debug-box {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            font-size: 12px;
            font-family: monospace;
            color: #666;
            max-height: 150px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>📋 Import CSV - Allaitement</h1>
            
            <?php if ($message): ?>
                <div class="message success"><?= htmlspecialchars($message) ?></div>
                <?php if (!empty($stats)): ?>
                    <div class="stats-box">
                        <div class="stat">
                            <div class="stat-value"><?= $stats['imported'] ?></div>
                            <div class="stat-label">Importées</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= $stats['errors'] ?></div>
                            <div class="stat-label">Erreurs</div>
                        </div>
                    </div>
                    <?php if (!empty($stats['details'])): ?>
                        <div class="error-details">
                            <strong>Détails des erreurs :</strong>
                            <ul>
                                <?php foreach ($stats['details'] as $detail): ?>
                                    <li><?= htmlspecialchars($detail) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
                <?php if (!empty($debug)): ?>
                    <div class="debug-box">
                        <strong>Logs de débogage :</strong><br>
                        <?php foreach ($debug as $d): ?>
                            → <?= htmlspecialchars($d) ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>📋 Format attendu (strict)</h3>
                <ul>
                    <li><strong>Séparateur :</strong> <code>;</code> (point-virgule)</li>
                    <li><strong>En-tête :</strong> <code>Date;Heure de début;Heure de fin;Durée;Sein</code></li>
                    <li><strong>Date :</strong> JJ/MM/YYYY (ex: <code>24/12/2025</code>)</li>
                    <li><strong>Heures :</strong> HH:MM (ex: <code>17:35</code>)</li>
                    <li><strong>Durée :</strong> HH:MM (colonne conservée, non utilisée)</li>
                    <li><strong>Sein :</strong> <code>Gauche</code> ou <code>Droit</code></li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="csvForm">
                <div class="upload-area" onclick="document.getElementById('csv_file').click()">
                    <div class="upload-icon">📄</div>
                    <p><strong>Cliquez pour sélectionner un fichier CSV</strong></p>
                    <p style="color: #666; font-size: 14px; margin-top: 10px;">ou glissez-déposez ici</p>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" onchange="showFileName(this)">
                    <div id="csv_filename" class="file-name"></div>
                </div>
                <button type="submit" name="import_csv" class="btn" id="csvBtn" disabled>Importer le CSV</button>
                <button type="button" class="btn btn-secondary" onclick="location.href='index.php'">
                    ← Retour
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function showFileName(input) {
            const filenameDiv = document.getElementById('csv_filename');
            const btn = document.getElementById('csvBtn');
            
            if (input.files && input.files[0]) {
                filenameDiv.textContent = '📎 ' + input.files[0].name;
                btn.disabled = false;
            } else {
                filenameDiv.textContent = '';
                btn.disabled = true;
            }
        }
        
        // Drag and drop
        const form = document.getElementById('csvForm');
        const uploadArea = form.querySelector('.upload-area');
        const fileInput = document.getElementById('csv_file');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.style.background = '#f8f9ff';
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.style.background = '';
            }, false);
        });
        
        uploadArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            showFileName(fileInput);
        }, false);
    </script>
</body>
</html>