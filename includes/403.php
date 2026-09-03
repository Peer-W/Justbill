<?php
require_once __DIR__ . '/config.php';
$settings = getSettings();
$theme = getTheme();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Toegang geweigerd - <?php echo htmlspecialchars($settings['siteName']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="/<?php echo htmlspecialchars($theme['favicon'] ?? 'favicon.ico'); ?>">
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <h1 class="error-code">403</h1>
            <h2 class="error-title"><?php echo htmlspecialchars($theme['error403Title'] ?? 'Toegang geweigerd'); ?></h2>
            <p class="error-message"><?php echo htmlspecialchars($theme['error403Text'] ?? 'Je hebt geen toegang tot deze pagina.'); ?></p>
            <a href="/" class="btn btn-primary">Terug naar Home</a>
        </div>
    </div>
</body>
</html>
