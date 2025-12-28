<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Suivi Allaitement' ?></title>
    
	<!-- Meta tags pour désactiver le cache (important pour iPhone) -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
	
	<!-- Version pour forcer le rechargement des assets -->
    <?php 
    // Version basée sur la date de modification du fichier OU version manuelle
    $version = defined('ASSET_VERSION') ? ASSET_VERSION : time();
    ?>
	
	<!-- CSS avec version -->
    <link rel="stylesheet" href="css/style.css?v=<?= $version ?>">
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>?v=<?= $version ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Favicon et icônes d'app -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icon-512.png">
    <link rel="apple-touch-icon" href="icon-512.png">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Couleur de thème (barre d'adresse mobile) -->
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Allaitement">
	
    <!-- Chart.js si nécessaire -->
    <?php if (isset($useCharts) && $useCharts): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body>