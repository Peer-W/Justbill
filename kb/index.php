<?php
require_once __DIR__ . '/../includes/config.php';

$page = $_GET['page'] ?? 'home';
$category = $_GET['category'] ?? null;
$article = $_GET['article'] ?? null;
$search = $_GET['search'] ?? null;

$settings = getSettings();
$theme = getTheme();
$kbData = getKB();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Base - <?php echo htmlspecialchars($settings['siteName']); ?></title>
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
                <a href="/kb" class="active">Knowledge Base</a>
                <a href="/forum">Forum</a>
                <a href="/client">Klantenportaal</a>
            </div>
        </div>
    </nav>

    <main class="kb-page">
        <div class="container">
            <?php if ($article && isset($kbData['articles'][$article])): ?>
                <?php $art = $kbData['articles'][$article]; ?>
                <div class="kb-breadcrumb">
                    <a href="/kb">Knowledge Base</a>
                    <span>/</span>
                    <a href="/kb?category=<?php echo urlencode($art['category']); ?>"><?php echo htmlspecialchars($kbData['categories'][$art['category']]['name'] ?? $art['category']); ?></a>
                    <span>/</span>
                    <span><?php echo htmlspecialchars($art['title']); ?></span>
                </div>
                
                <article class="kb-article">
                    <h1><?php echo htmlspecialchars($art['title']); ?></h1>
                    <div class="kb-meta">
                        <span>Laatst bijgewerkt: <?php echo date('d-m-Y', strtotime($art['updated'] ?? $art['created'])); ?></span>
                    </div>
                    <div class="kb-content">
                        <?php echo $art['content']; ?>
                    </div>
                </article>
                
            <?php elseif ($category && isset($kbData['categories'][$category])): ?>
                <?php $cat = $kbData['categories'][$category]; ?>
                <div class="kb-breadcrumb">
                    <a href="/kb">Knowledge Base</a>
                    <span>/</span>
                    <span><?php echo htmlspecialchars($cat['name']); ?></span>
                </div>
                
                <div class="kb-category-header">
                    <h1><?php echo htmlspecialchars($cat['name']); ?></h1>
                    <p><?php echo htmlspecialchars($cat['description'] ?? ''); ?></p>
                </div>
                
                <div class="kb-articles-list">
                    <?php 
                    $catArticles = array_filter($kbData['articles'], fn($a) => $a['category'] === $category);
                    foreach ($catArticles as $id => $art): 
                    ?>
                    <a href="/kb?article=<?php echo urlencode($id); ?>" class="kb-article-item">
                        <h3><?php echo htmlspecialchars($art['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr(strip_tags($art['content']), 0, 150)); ?>...</p>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if (empty($catArticles)): ?>
                    <div class="empty-state">
                        <p>Geen artikelen gevonden in deze categorie.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($search): ?>
                <div class="kb-breadcrumb">
                    <a href="/kb">Knowledge Base</a>
                    <span>/</span>
                    <span>Zoekresultaten voor "<?php echo htmlspecialchars($search); ?>"</span>
                </div>
                
                <h1>Zoekresultaten</h1>
                
                <div class="kb-articles-list">
                    <?php 
                    $results = array_filter($kbData['articles'], function($a) use ($search) {
                        return stripos($a['title'], $search) !== false || stripos($a['content'], $search) !== false;
                    });
                    foreach ($results as $id => $art): 
                    ?>
                    <a href="/kb?article=<?php echo urlencode($id); ?>" class="kb-article-item">
                        <h3><?php echo htmlspecialchars($art['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr(strip_tags($art['content']), 0, 150)); ?>...</p>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if (empty($results)): ?>
                    <div class="empty-state">
                        <p>Geen resultaten gevonden voor "<?php echo htmlspecialchars($search); ?>".</p>
                    </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <div class="kb-header">
                    <h1>Knowledge Base</h1>
                    <p>Vind antwoorden op veelgestelde vragen en handleidingen</p>
                    
                    <form class="kb-search" action="/kb" method="GET">
                        <input type="text" name="search" placeholder="Zoek in de knowledge base..." class="form-input">
                        <button type="submit" class="btn btn-primary">Zoeken</button>
                    </form>
                </div>
                
                <div class="kb-categories">
                    <?php foreach ($kbData['categories'] as $id => $cat): ?>
                    <a href="/kb?category=<?php echo urlencode($id); ?>" class="kb-category-card">
                        <div class="kb-category-icon">
                            <?php echo $cat['icon'] ?? '📚'; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p><?php echo htmlspecialchars($cat['description'] ?? ''); ?></p>
                        <span class="kb-article-count">
                            <?php echo count(array_filter($kbData['articles'], fn($a) => $a['category'] === $id)); ?> artikelen
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="kb-popular">
                    <h2>Populaire Artikelen</h2>
                    <div class="kb-articles-list">
                        <?php 
                        $popular = array_slice($kbData['articles'], 0, 5, true);
                        foreach ($popular as $id => $art): 
                        ?>
                        <a href="/kb?article=<?php echo urlencode($id); ?>" class="kb-article-item">
                            <h3><?php echo htmlspecialchars($art['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr(strip_tags($art['content']), 0, 150)); ?>...</p>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['siteName']); ?>. Alle rechten voorbehouden.</p>
        </div>
    </footer>
</body>
</html>
