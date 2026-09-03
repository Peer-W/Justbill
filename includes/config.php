<?php
 JustBill Configuration
session_start();

 Base paths
define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('DATA_DIR', DATA_PATH);  alias for backwards compatibility
define('INCLUDES_PATH', ROOT_PATH . '/includes');

 Ensure data directory exists
if (!file_exists(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}

 JSON Data Functions
function loadJSON($file) {
    $path = DATA_PATH . '/' . $file;
    if (file_exists($path)) {
        return json_decode(file_get_contents($path), true) ?: [];
    }
    return [];
}

function saveJSON($file, $data) {
    $path = DATA_PATH . '/' . $file;
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

 Initialize default data files
function initializeData() {
     Settings
    if (!file_exists(DATA_PATH . '/settings.json')) {
        saveJSON('settings.json', [
            'site_name' => 'JustBill',
            'site_description' => 'Reliable hosting, VPS and web development for everyone',
            'company_email' => 'info@profie-it.nl',
            'company_phone' => '+31 6 12345678',
            'currency' => 'EUR',
            'currency_symbol' => '€',
            'ipv4_price' => 1.50,
            'stats' => [
                ['value' => '99.9%', 'label' => 'Uptime'],
                ['value' => '24/7', 'label' => 'Support'],
                ['value' => '100+', 'label' => 'Customers'],
                ['value' => 'NL', 'label' => 'Datacenters']
            ],
            'partners' => [
                ['name' => 'AS205838', 'description' => 'Network / AS partner', 'url' => ''],
                ['name' => 'IVB-Netwerk.nl', 'description' => 'Network partner', 'url' => 'https://ivb-netwerk.nl']
            ]
        ]);
    }

     Theme
    if (!file_exists(DATA_PATH . '/theme.json')) {
        saveJSON('theme.json', [
            'primary_color' => '#2563eb',
            'secondary_color' => '#0ea5e9',
            'background_color' => '#0f172a',
            'card_color' => '#1e293b',
            'text_color' => '#f8fafc',
            'muted_color' => '#94a3b8',
            'logo' => 'logo.png',
            'favicon' => 'favicon.ico',
            'dark_mode' => true,
            'font_family' => 'Inter',
            'error_404' => 'Page not found',
            'error_403' => 'Access denied',
            'error_500' => 'Server error'
        ]);
    }

     Products
    if (!file_exists(DATA_PATH . '/products.json')) {
        saveJSON('products.json', [
            [
                'id' => 'webhosting-starter',
                'category' => 'webhosting',
                'name' => 'Webhosting Starter',
                'description' => 'Perfect for small websites',
                'price' => 4.99,
                'billing_cycle' => 'monthly',
                'features' => ['5GB SSD Storage', '1 Website', 'Free SSL', 'Email accounts'],
                'stripe_price_id' => '',
                'keyhelp_package_id' => '',
                'enabled' => true
            ],
            [
                'id' => 'webhosting-pro',
                'category' => 'webhosting',
                'name' => 'Webhosting Pro',
                'description' => 'For growing businesses',
                'price' => 9.99,
                'billing_cycle' => 'monthly',
                'features' => ['25GB SSD Storage', '5 Websites', 'Free SSL', 'Unlimited Email'],
                'stripe_price_id' => '',
                'keyhelp_package_id' => '',
                'enabled' => true
            ],
            [
                'id' => 'vps-basic',
                'category' => 'vps',
                'name' => 'VPS Basic',
                'description' => 'Powerful virtual server',
                'price' => 14.99,
                'billing_cycle' => 'monthly',
                'features' => ['2 vCPU', '4GB RAM', '50GB SSD', 'Root Access', '1 IPv4 included'],
                'stripe_price_id' => '',
                'keyhelp_package_id' => '',
                'is_vps' => true,
                'included_ipv4' => 1,
                'enabled' => true
            ],
            [
                'id' => 'webdev-custom',
                'category' => 'webdevelopment',
                'name' => 'Custom Website / Node.js Project',
                'description' => 'Tailor-made website or Node.js application built by our team',
                'price' => 0,
                'billing_cycle' => 'once',
                'features' => ['Custom design', 'Node.js / full-stack development', 'Personal project manager', 'Quote on request'],
                'stripe_price_id' => '',
                'keyhelp_package_id' => '',
                'is_quote' => true,
                'enabled' => true
            ]
        ]);
    }

     Categories
    if (!file_exists(DATA_PATH . '/categories.json')) {
        saveJSON('categories.json', [
            ['id' => 'webhosting', 'name' => 'Webhosting', 'icon' => 'globe', 'order' => 1],
            ['id' => 'vps', 'name' => 'VPS Servers', 'icon' => 'server', 'order' => 2],
            ['id' => 'webdevelopment', 'name' => 'Web Development', 'icon' => 'code', 'order' => 3],
            ['id' => 'tools', 'name' => 'Tools', 'icon' => 'tool', 'order' => 4]
        ]);
    }

     Users
    if (!file_exists(DATA_PATH . '/users.json')) {
        saveJSON('users.json', []);
    }

     Admin auth
    if (!file_exists(DATA_PATH . '/auth.json')) {
        saveJSON('auth.json', [
            'username' => 'admin',
            'password' => password_hash('admin', PASSWORD_DEFAULT)
        ]);
    }

     Staff
    if (!file_exists(DATA_PATH . '/staff.json')) {
        saveJSON('staff.json', []);
    }

     Tickets
    if (!file_exists(DATA_PATH . '/tickets.json')) {
        saveJSON('tickets.json', []);
    }

     Orders (pending / processing orders that may need follow-up)
    if (!file_exists(DATA_PATH . '/orders.json')) {
        saveJSON('orders.json', []);
    }

     Knowledge Base
    if (!file_exists(DATA_PATH . '/kb.json')) {
        saveJSON('kb.json', [
            'categories' => [
                ['id' => 'getting-started', 'name' => 'Getting Started', 'icon' => 'book'],
                ['id' => 'billing', 'name' => 'Billing', 'icon' => 'credit-card'],
                ['id' => 'hosting', 'name' => 'Hosting', 'icon' => 'server'],
                ['id' => 'support', 'name' => 'Support', 'icon' => 'help-circle']
            ],
            'articles' => [
                [
                    'id' => 'welcome',
                    'category' => 'getting-started',
                    'title' => 'Welcome to JustBill',
                    'content' => 'Welcome to JustBill! Here you will find all the information to get started.',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]
        ]);
    }

     Forum
    if (!file_exists(DATA_PATH . '/forum.json')) {
        saveJSON('forum.json', [
            'categories' => [
                ['id' => 'general', 'name' => 'General', 'description' => 'General discussions'],
                ['id' => 'support', 'name' => 'Support', 'description' => 'Ask for help'],
                ['id' => 'announcements', 'name' => 'Announcements', 'description' => 'News and updates']
            ],
            'topics' => [],
            'posts' => []
        ]);
    }

     Custom Pages
    if (!file_exists(DATA_PATH . '/pages.json')) {
        saveJSON('pages.json', [
            [
                'id' => 'tos',
                'slug' => 'tos',
                'title' => 'Terms of Service',
                'content' => '<h2>Terms of Service</h2><p>Your terms of service go here.</p>',
                'seo_title' => 'Terms of Service - JustBill',
                'seo_description' => 'Read our terms of service.',
                'enabled' => true
            ],
            [
                'id' => 'privacy',
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'content' => '<h2>Privacy Policy</h2><p>Your privacy policy goes here.</p>',
                'seo_title' => 'Privacy Policy - JustBill',
                'seo_description' => 'Read our privacy policy.',
                'enabled' => true
            ]
        ]);
    }

     Invoices
    if (!file_exists(DATA_PATH . '/invoices.json')) {
        saveJSON('invoices.json', []);
    }

     Services (user subscriptions)
    if (!file_exists(DATA_PATH . '/services.json')) {
        saveJSON('services.json', []);
    }

     Logs
    if (!file_exists(DATA_PATH . '/logs.json')) {
        saveJSON('logs.json', []);
    }

     Mail config
    if (!file_exists(DATA_PATH . '/mailserverconfig.json')) {
        saveJSON('mailserverconfig.json', [
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_secure' => 'tls',
            'from_email' => 'noreply@profie-it.nl',
            'from_name' => 'JustBill',
            'followup_subject' => 'Follow-up on your order at JustBill',
            'followup_body' => "Hi {name},\n\nThank you for your recent order ({product}). We wanted to follow up to make sure everything is set up correctly and to answer any questions you may have.\n\nIf you need anything, simply reply to this email or open a ticket in your dashboard.\n\nKind regards,\nThe JustBill Team"
        ]);
    }

     Stripe config
    if (!file_exists(DATA_PATH . '/stripe.json')) {
        saveJSON('stripe.json', [
            'public_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
            'enabled' => false
        ]);
    }

     KeyHelp config
    if (!file_exists(DATA_PATH . '/keyhelp.json')) {
        saveJSON('keyhelp.json', [
            'enabled' => false,
            'api_url' => '',
            'api_key' => '',
            'default_package_id' => '',
            'auto_create' => true
        ]);
    }

     Coupons
    if (!file_exists(DATA_PATH . '/coupons.json')) {
        saveJSON('coupons.json', []);
    }

     White-label / branding settings
    if (!file_exists(DATA_PATH . '/whitelabel.json')) {
        saveJSON('whitelabel.json', [
            'enabled' => false,
            'footer_text' => '',
            'hide_powered_by' => false,
            'custom_css' => ''
        ]);
    }
}

initializeData();

 Helper functions
function generateId() {
    return bin2hex(random_bytes(8));
}

function formatPrice($price) {
    $settings = loadJSON('settings.json');
    return $settings['currency_symbol'] . number_format($price, 2, ',', '.');
}

function logAction($action, $details = [], $user_id = null) {
    $logs = loadJSON('logs.json');
    $logs[] = [
        'id' => generateId(),
        'action' => $action,
        'details' => $details,
        'user_id' => $user_id,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }
    saveJSON('logs.json', $logs);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function isStaff() {
    return isset($_SESSION['staff_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $users = loadJSON('users.json');
    foreach ($users as $user) {
        if ($user['id'] === $_SESSION['user_id']) {
            return $user;
        }
    }
    return null;
}

function getTheme() {
    return loadJSON('theme.json');
}

function getSettings() {
    return loadJSON('settings.json');
}

 Simple mail helper using configured SMTP or PHP mail()
function sendMail($to, $subject, $body) {
    $mail = loadJSON('mailserverconfig.json');
    $fromEmail = $mail['from_email'] ?: 'noreply@profie-it.nl';
    $fromName = $mail['from_name'] ?: 'JustBill';
    $headers = 'From: ' . $fromName . ' <' . $fromEmail . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
     Note: for real SMTP auth use PHPMailer. This uses the server mail() function.
    return @mail($to, $subject, $body, $headers);
}
