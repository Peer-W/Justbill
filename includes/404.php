<?php
$settings = getSettings();
$theme = getTheme();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Pagina niet gevonden - <?php echo htmlspecialchars($settings['siteName']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="/<?php echo htmlspecialchars($theme['favicon'] ?? 'favicon.ico'); ?>">
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <h1 class="error-code">404</h1>
            <h2 class="error-title"><?php echo htmlspecialchars($theme['error404Title'] ?? 'Pagina niet gevonden'); ?></h2>
            <p class="error-message"><?php echo htmlspecialchars($theme['error404Text'] ?? 'De pagina die je zoekt bestaat niet of is verplaatst.'); ?></p>
            <a href="/" class="btn btn-primary">Terug naar Home</a>
        </div>
    </div>
</body>
</html>
