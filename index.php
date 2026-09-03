<?php
$installDirectory = __DIR__ . '/install';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$installPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/install';
$isInstallerRequest = $requestPath === $installPath || strpos($requestPath, $installPath . '/') === 0;
if (is_dir($installDirectory) && !$isInstallerRequest) {
    $target = $installPath . '/index.php';
    header('Location: ' . ($target === '/install/index.php' ? '/install/index.php' : $target), true, 302);
    exit;
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

$settings = getSettings();
$language = visitorLanguage();
$theme = getTheme();
$whitelabel = loadJSON('whitelabel.json');
$products = loadJSON('products.json');
$categories = loadJSON('categories.json');

// Get page from URL
$page = $_GET['page'] ?? 'home';

// Custom pages
$customPages = loadJSON('pages.json');
foreach ($customPages as $customPage) {
    if ($customPage['slug'] === $page && $customPage['enabled']) {
        $pageData = $customPage;
        $page = 'custom';
        break;
    }
}

function productPriceLabel($product, $settings) {
    if (!empty($product['is_quote'])) {
        return 'Quote on request';
    }
    $cycle = $product['billing_cycle'] === 'monthly' ? '/mo' : ($product['billing_cycle'] === 'yearly' ? '/yr' : '');
    return $settings['currency_symbol'] . number_format($product['price'], 2, ',', '.') . '<span>' . $cycle . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['site_name']); ?> - Reliable Hosting, VPS & Web Development</title>
    <meta name="description" content="<?php echo htmlspecialchars($settings['site_description']); ?>">
    <link rel="icon" href="<?php echo htmlspecialchars($theme['favicon'] ?? 'favicon.ico'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php $customCss = $theme['custom_css'] ?? $whitelabel['custom_css'] ?? ''; if ($customCss !== ''): ?><style><?php echo $customCss; ?></style><?php endif; ?>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <a href="/" class="logo">
                    <img src="<?php echo htmlspecialchars($theme['logo'] ?? 'logo.png'); ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>">
                </a>
                <nav class="nav">
                    <ul class="nav-links">
                        <li><a href="/">Home</a></li>
                        <li><a href="/?page=products">Products</a></li>
                        <li><a href="/?page=kb">Knowledge Base</a></li>
                        <li><a href="/?page=forum">Forum</a></li>
                        <li><a href="/?page=contact">Contact</a></li>
                    </ul>
                    <div class="nav-actions">
                        <?php if (isLoggedIn()): ?>
                            <a href="client/" class="btn btn-ghost">Dashboard</a>
                            <a href="client/?action=logout" class="btn btn-outline">Log out</a>
                        <?php else: ?>
                            <a href="client/?page=login" class="btn btn-ghost">Log in</a>
                            <a href="client/?page=register" class="btn btn-primary">Sign up</a>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <?php if ($page === 'home'): ?>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Reliable Hosting<br>for Everyone</h1>
                    <p><?php echo htmlspecialchars($settings['site_description']); ?></p>
                    <div class="hero-actions">
                        <a href="/?page=products" class="btn btn-primary btn-lg">View Products</a>
                        <a href="/?page=contact" class="btn btn-outline btn-lg">Contact Us</a>
                    </div>
                    <div class="stats">
                        <?php foreach ($settings['stats'] ?? [] as $stat): ?>
                        <div class="stat">
                            <div class="stat-value"><?php echo htmlspecialchars($stat['value']); ?></div>
                            <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Products Section -->
        <section class="products-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Our Services</h2>
                    <p class="section-description">Choose the package that fits you</p>
                </div>

                <div class="categories">
                    <button class="category-btn active" data-category="all">All</button>
                    <?php foreach ($categories as $cat): ?>
                    <button class="category-btn" data-category="<?php echo htmlspecialchars($cat['id']); ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                    <?php if (!$product['enabled']) continue; ?>
                    <div class="product-card" data-category="<?php echo htmlspecialchars($product['category']); ?>">
                        <div class="product-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                            </svg>
                        </div>
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <div class="product-price"><?php echo productPriceLabel($product, $settings); ?></div>
                        <ul class="product-features">
                            <?php foreach ($product['features'] as $feature): ?>
                            <li><?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!empty($product['is_quote'])): ?>
                        <a href="client/?page=new-ticket&type=webdev&product=<?php echo urlencode($product['id']); ?>" class="btn btn-primary">Request Quote</a>
                        <?php else: ?>
                        <a href="client/?page=order&product=<?php echo urlencode($product['id']); ?>" class="btn btn-primary">Order Now</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="products-section" style="background: var(--card);">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Why <?php echo htmlspecialchars($settings['site_name']); ?>?</h2>
                    <p class="section-description">Discover what makes us unique</p>
                </div>
                <div class="products-grid">
                    <div class="card">
                        <div class="product-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="card-title">Blazing Fast Servers</h3>
                        <p class="card-description">NVMe SSD storage and modern hardware for optimal performance.</p>
                    </div>
                    <div class="card">
                        <div class="product-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="card-title">Maximum Security</h3>
                        <p class="card-description">DDoS protection, SSL certificates and daily backups included.</p>
                    </div>
                    <div class="card">
                        <div class="product-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <h3 class="card-title">24/7 Support</h3>
                        <p class="card-description">Our team is always here for you via ticket or email.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Partners Section -->
        <?php if (!empty($settings['partners'])): ?>
        <section class="products-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Our Partners</h2>
                    <p class="section-description">We proudly work together with</p>
                </div>
                <div class="products-grid">
                    <?php foreach ($settings['partners'] as $partner): ?>
                    <div class="card" style="text-align:center;">
                        <h3 class="card-title"><?php echo htmlspecialchars($partner['name']); ?></h3>
                        <p class="card-description"><?php echo htmlspecialchars($partner['description'] ?? ''); ?></p>
                        <?php if (!empty($partner['url'])): ?>
                        <a href="<?php echo htmlspecialchars($partner['url']); ?>" target="_blank" rel="noopener" class="btn btn-outline mt-3">Visit</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php elseif ($page === 'products'): ?>
        <!-- Products Page -->
        <section class="products-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">All Products</h2>
                    <p class="section-description">Browse our complete offering</p>
                </div>

                <div class="categories">
                    <button class="category-btn active" data-category="all">All</button>
                    <?php foreach ($categories as $cat): ?>
                    <button class="category-btn" data-category="<?php echo htmlspecialchars($cat['id']); ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                    <?php if (!$product['enabled']) continue; ?>
                    <div class="product-card" data-category="<?php echo htmlspecialchars($product['category']); ?>">
                        <div class="product-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                            </svg>
                        </div>
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <div class="product-price"><?php echo productPriceLabel($product, $settings); ?></div>
                        <ul class="product-features">
                            <?php foreach ($product['features'] as $feature): ?>
                            <li><?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!empty($product['is_quote'])): ?>
                        <a href="client/?page=new-ticket&type=webdev&product=<?php echo urlencode($product['id']); ?>" class="btn btn-primary">Request Quote</a>
                        <?php else: ?>
                        <a href="client/?page=order&product=<?php echo urlencode($product['id']); ?>" class="btn btn-primary">Order Now</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php elseif ($page === 'kb'): ?>
        <!-- Knowledge Base -->
        <section class="products-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Knowledge Base</h2>
                    <p class="section-description">Find answers to your questions</p>
                </div>

                <?php
                $kb = loadJSON('kb.json');
                $articleSlug = $_GET['article'] ?? '';

                if ($articleSlug):
                    $article = null;
                    foreach ($kb['articles'] as $a) {
                        if ($a['id'] === $articleSlug) {
                            $article = $a;
                            break;
                        }
                    }
                    if ($article):
                ?>
                <div class="kb-article">
                    <a href="/?page=kb" class="btn btn-ghost mb-4">&larr; Back to overview</a>
                    <h1><?php echo htmlspecialchars($article['title']); ?></h1>
                    <div class="kb-article-content">
                        <?php echo $article['content']; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-danger">Article not found.</div>
                <?php endif; ?>

                <?php else: ?>
                <div class="kb-categories">
                    <?php foreach ($kb['categories'] as $cat): ?>
                    <div class="kb-category">
                        <div class="kb-category-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <ul class="footer-links mt-3">
                            <?php foreach ($kb['articles'] as $article): ?>
                            <?php if ($article['category'] === $cat['id']): ?>
                            <li><a href="/?page=kb&article=<?php echo urlencode($article['id']); ?>"><?php echo htmlspecialchars($article['title']); ?></a></li>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php elseif ($page === 'forum'): ?>
        <!-- Forum -->
        <section class="products-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Community Forum</h2>
                    <p class="section-description">Discuss with other users</p>
                </div>

                <?php $forum = loadJSON('forum.json'); ?>

                <div class="kb-categories">
                    <?php foreach ($forum['categories'] as $cat): ?>
                    <a href="/?page=forum&category=<?php echo urlencode($cat['id']); ?>" class="kb-category">
                        <div class="kb-category-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                            </svg>
                        </div>
                        <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p class="text-muted"><?php echo htmlspecialchars($cat['description']); ?></p>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php elseif ($page === 'contact'): ?>
        <!-- Contact -->
        <section class="products-section">
            <div class="container" style="max-width: 600px;">
                <div class="section-header">
                    <h2 class="section-title">Contact</h2>
                    <p class="section-description">Get in touch with us</p>
                </div>

                <div class="card">
                    <form method="POST" action="/?page=contact&action=send">
                        <div class="form-group">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-textarea" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Send</button>
                    </form>
                </div>

                <div class="card mt-4">
                    <h3 class="card-title">Other ways to reach us</h3>
                    <p class="mt-3"><strong>Email:</strong> <?php echo htmlspecialchars($settings['company_email']); ?></p>
                    <p class="mt-2"><strong>Phone:</strong> <?php echo htmlspecialchars($settings['company_phone']); ?></p>
                </div>
            </div>
        </section>

        <?php elseif ($page === 'custom' && isset($pageData)): ?>
        <!-- Custom Page -->
        <section class="products-section">
            <div class="container" style="max-width: 800px;">
                <div class="kb-article">
                    <?php echo $pageData['content']; ?>
                </div>
            </div>
        </section>

        <?php else: ?>
        <!-- 404 -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>404</h1>
                    <p><?php echo htmlspecialchars($theme['error_404'] ?? 'Page not found'); ?></p>
                    <a href="/" class="btn btn-primary">Back to Home</a>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <a href="/" class="logo">
                        <img src="<?php echo htmlspecialchars($theme['logo'] ?? 'logo.png'); ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>" style="height: 40px;">
                    </a>
                    <p class="text-muted mt-3"><?php echo htmlspecialchars($settings['site_description']); ?></p>
                </div>
                <div class="footer-section">
                    <h3>Products</h3>
                    <ul class="footer-links">
                        <?php foreach ($categories as $cat): ?>
                        <li><a href="/?page=products&category=<?php echo urlencode($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul class="footer-links">
                        <li><a href="/?page=kb">Knowledge Base</a></li>
                        <li><a href="/?page=forum">Forum</a></li>
                        <li><a href="client/?page=tickets">Open a Ticket</a></li>
                        <li><a href="/?page=contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Legal</h3>
                    <ul class="footer-links">
                        <li><a href="/?page=tos">Terms of Service</a></li>
                        <li><a href="/?page=privacy">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['site_name']); ?>. All rights reserved.</p>
                <?php if (empty($whitelabel['hide_powered_by'])): ?>
                <p>Powered by JustBill</p>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <script>
    // Category filtering
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const category = btn.dataset.category;
            document.querySelectorAll('.product-card').forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    </script>
</body>
</html>
