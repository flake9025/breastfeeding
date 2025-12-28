<?php
$configFile = 'config.inc.php';

if (!file_exists(__DIR__ . '/' . $configFile)) {
    die("Fichier de configuration manquant : $configFile");
}

require_once __DIR__ . '/' . $configFile;