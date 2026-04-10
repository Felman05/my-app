<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    require_once __DIR__ . '/env.php';
    $googleMapsKey = env('GOOGLE_MAPS_API_KEY', '');
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? escape($pageTitle) . ' - Doon' : 'Doon - Tourism Platform'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,700;1,9..144,700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="/doon-app/assets/css/main.css">
    <?php if (isset($additionalCSS)) echo $additionalCSS; ?>
    <?php if (!empty($loadGoogleMapsApi) && !empty($googleMapsKey)): ?>
    <script async src="https://maps.googleapis.com/maps/api/js?key=<?php echo escape($googleMapsKey); ?><?php echo !empty($googleMapsCallback) ? '&callback=' . escape($googleMapsCallback) : ''; ?>"></script>
    <?php endif; ?>
</head>
<body>
