<?php
$configFile = 'config.inc.php';

if (!file_exists(__DIR__ . '/' . $configFile)) {
    die("Fichier de configuration manquant : $configFile");
}

require_once __DIR__ . '/' . $configFile;

// Connexion à la base de données
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Fuseau horaire
date_default_timezone_set('Europe/Paris');
?>