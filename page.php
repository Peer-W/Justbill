<?php
require_once __DIR__ . '/includes/config.php';

$slug = $_GET['slug'] ?? '';
$settings = getSettings();
$theme = getTheme();
$pages = getPages();

$page = null;
foreach ($pages as $p) {
    if ($p['slug'] === $slug) {
        $page = $p;
        break;
    }
}

if (!$page) {
    http_response_code(404);
    include __DIR__ . '/includes/404.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['title']); ?> - <?php echo htmlspecialchars($settings['siteName']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page['metaDescription'] ?? ''); ?>">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="/<?php echo htmlspecialchars($theme['favicon'] ?? 'favicon.ico'); ?>">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-content">
            <a href="/" class="logo">
                <img src="/logo.png" alt="<?php echo htmlspecialchars($settings['siteName']); ?>" class="logo-img">
            </a>
            <div class="nav-links">
                <a href="/">Home</a>
                <a href="/kb">Knowledge Base</a>
                <a href="/forum">Forum</a>
                <a href="/client">Klantenportaal</a>
            </div>
        </div>
    </nav>

    <main class="custom-page">
        <div class="container">
            <article class="page-content">
                <h1><?php echo htmlspecialchars($page['title']); ?></h1>
                <div class="page-body">
                    <?php echo $page['content']; ?>
                </div>
            </article>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-links">
                <?php foreach ($pages as $p): ?>
                <a href="/page.php?slug=<?php echo urlencode($p['slug']); ?>"><?php echo htmlspecialchars($p['title']); ?></a>
                <?php endforeach; ?>
            </div>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['siteName']); ?>. Alle rechten voorbehouden.</p>
        </div>
    </footer>
    
</body>
</html>
