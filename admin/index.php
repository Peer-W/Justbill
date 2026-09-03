<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/keyhelp.php';

$settings = getSettings();
$theme = getTheme();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$page = $_GET['page'] ?? 'dashboard';
$error = '';
$success = '';

function staffCan($perm) {
    if (isAdmin()) return true;
    $perms = $_SESSION['staff_permissions'] ?? [];
    return in_array($perm, $perms);
}

// Logout
if ($action === 'logout') {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['staff_id']);
    unset($_SESSION['staff_permissions']);
    header('Location: ?page=login');
    exit;
}

// Login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = loadJSON('auth.json');
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $auth['username'] && password_verify($password, $auth['password'])) {
        $_SESSION['admin_logged_in'] = true;
        logAction('admin_login', ['username' => $username]);
        header('Location: ?page=dashboard');
        exit;
    } else {
        $staff = loadJSON('staff.json');
        foreach ($staff as $s) {
            if ($s['username'] === $username && password_verify($password, $s['password']) && $s['enabled']) {
                $_SESSION['staff_id'] = $s['id'];
                $_SESSION['staff_permissions'] = $s['permissions'];
                logAction('staff_login', ['staff_id' => $s['id']]);
                header('Location: ?page=dashboard');
                exit;
            }
        }
        $error = 'Invalid login credentials.';
    }
    $page = 'login';
}

// Require login
if ($page !== 'login' && !isAdmin() && !isStaff()) {
    header('Location: ?page=login');
    exit;
}

// ---------- PRODUCTS ----------
if ($action === 'save_product' && $_SERVER['REQUEST_METHOD'] === 'POST' && staffCan('products')) {
    $products = loadJSON('products.json');
    $productId = $_POST['id'] ?? '';
    $productData = [
        'id' => $productId ?: generateId(),
        'category' => $_POST['category'] ?? 'webhosting',
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price' => floatval($_POST['price'] ?? 0),
        'billing_cycle' => $_POST['billing_cycle'] ?? 'monthly',
        'features' => array_values(array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')))),
        'stripe_price_id' => trim($_POST['stripe_price_id'] ?? ''),
        'keyhelp_package_id' => trim($_POST['keyhelp_package_id'] ?? ''),
        'is_vps' => isset($_POST['is_vps']),
        'is_quote' => isset($_POST['is_quote']),
        'included_ipv4' => intval($_POST['included_ipv4'] ?? 0),
        'enabled' => isset($_POST['enabled'])
    ];
    if ($productId) {
        foreach ($products as $i => $p) { if ($p['id'] === $productId) { $products[$i] = $productData; break; } }
    } else {
        $products[] = $productData;
    }
    saveJSON('products.json', $products);
    logAction('product_saved', ['product_id' => $productData['id']]);
    header('Location: ?page=products');
    exit;
}

if ($action === 'delete_product' && staffCan('products')) {
    $products = loadJSON('products.json');
    $productId = $_GET['id'] ?? '';
    $products = array_values(array_filter($products, fn($p) => $p['id'] !== $productId));
    saveJSON('products.json', $products);
    logAction('product_deleted', ['product_id' => $productId]);
    header('Location: ?page=products');
    exit;
}

// ---------- CATEGORIES ----------
if ($action === 'save_category' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $categories = loadJSON('categories.json');
    $catId = trim($_POST['cat_id'] ?? '');
    $name = trim($_POST['cat_name'] ?? '');
    if ($catId && $name) {
        $found = false;
        foreach ($categories as &$c) {
            if ($c['id'] === $catId) { $c['name'] = $name; $c['icon'] = $_POST['cat_icon'] ?? $c['icon']; $found = true; break; }
        }
        unset($c);
        if (!$found) {
            $categories[] = ['id' => $catId, 'name' => $name, 'icon' => $_POST['cat_icon'] ?? 'box', 'order' => count($categories) + 1];
        }
        saveJSON('categories.json', $categories);
    }
    header('Location: ?page=categories');
    exit;
}
if ($action === 'delete_category' && isAdmin()) {
    $categories = loadJSON('categories.json');
    $catId = $_GET['id'] ?? '';
    $categories = array_values(array_filter($categories, fn($c) => $c['id'] !== $catId));
    saveJSON('categories.json', $categories);
    header('Location: ?page=categories');
    exit;
}

// ---------- SETTINGS ----------
if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $settings = loadJSON('settings.json');
    $settings['site_name'] = trim($_POST['site_name'] ?? $settings['site_name']);
    $settings['site_description'] = trim($_POST['site_description'] ?? $settings['site_description']);
    $settings['company_email'] = trim($_POST['company_email'] ?? $settings['company_email']);
    $settings['company_phone'] = trim($_POST['company_phone'] ?? $settings['company_phone']);
    $settings['ipv4_price'] = floatval($_POST['ipv4_price'] ?? $settings['ipv4_price'] ?? 1.50);

    $stats = [];
    $statValues = $_POST['stat_value'] ?? [];
    $statLabels = $_POST['stat_label'] ?? [];
    for ($i = 0; $i < count($statValues); $i++) {
        if (!empty($statValues[$i]) && !empty($statLabels[$i])) {
            $stats[] = ['value' => $statValues[$i], 'label' => $statLabels[$i]];
        }
    }
    $settings['stats'] = $stats;

    $partners = [];
    $pNames = $_POST['partner_name'] ?? [];
    $pDescs = $_POST['partner_desc'] ?? [];
    $pUrls = $_POST['partner_url'] ?? [];
    for ($i = 0; $i < count($pNames); $i++) {
        if (!empty($pNames[$i])) {
            $partners[] = ['name' => $pNames[$i], 'description' => $pDescs[$i] ?? '', 'url' => $pUrls[$i] ?? ''];
        }
    }
    $settings['partners'] = $partners;

    saveJSON('settings.json', $settings);
    logAction('settings_saved');
    $success = 'Settings saved.';
}

// ---------- THEME ----------
if ($action === 'save_theme' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $theme = loadJSON('theme.json');
    foreach (['primary_color','secondary_color','background_color','card_color','text_color','muted_color','logo','favicon','font_family','error_404','error_403','error_500'] as $k) {
        if (isset($_POST[$k])) $theme[$k] = $_POST[$k];
    }
    $theme['dark_mode'] = isset($_POST['dark_mode']);
    saveJSON('theme.json', $theme);
    logAction('theme_saved');
    $success = 'Theme saved.';
}

// ---------- PASSWORD ----------
if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $auth = loadJSON('auth.json');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $newUsername = trim($_POST['username'] ?? '');
    if (password_verify($currentPassword, $auth['password'])) {
        if (!empty($newUsername)) $auth['username'] = $newUsername;
        if (!empty($newPassword)) $auth['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        saveJSON('auth.json', $auth);
        logAction('password_changed');
        $success = 'Login credentials changed.';
    } else {
        $error = 'Current password is incorrect.';
    }
}

// ---------- TICKETS ----------
if ($action === 'reply_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST' && staffCan('tickets')) {
    $tickets = loadJSON('tickets.json');
    $ticketId = $_POST['ticket_id'] ?? '';
    $message = trim($_POST['message'] ?? '');
    $newStatus = $_POST['status'] ?? 'in_progress';
    foreach ($tickets as &$ticket) {
        if ($ticket['id'] === $ticketId) {
            $ticket['messages'][] = [
                'id' => generateId(), 'user_id' => 'admin', 'message' => $message,
                'created_at' => date('Y-m-d H:i:s'), 'is_staff' => true
            ];
            $ticket['status'] = $newStatus;
            $ticket['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($ticket);
    saveJSON('tickets.json', $tickets);
    header('Location: ?page=ticket&id=' . $ticketId);
    exit;
}

// ---------- KB ----------
if ($action === 'save_article' && $_SERVER['REQUEST_METHOD'] === 'POST' && staffCan('kb')) {
    $kb = loadJSON('kb.json');
    $articleId = $_POST['id'] ?? '';
    $articleData = [
        'id' => $articleId ?: generateId(),
        'category' => $_POST['category'] ?? '',
        'title' => trim($_POST['title'] ?? ''),
        'content' => $_POST['content'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
    if ($articleId) {
        foreach ($kb['articles'] as $i => $a) { if ($a['id'] === $articleId) { $kb['articles'][$i] = $articleData; break; } }
    } else {
        $kb['articles'][] = $articleData;
    }
    saveJSON('kb.json', $kb);
    header('Location: ?page=kb');
    exit;
}

// ---------- CUSTOM PAGES ----------
if ($action === 'save_page' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $pages = loadJSON('pages.json');
    $pageId = $_POST['id'] ?? '';
    $pageData = [
        'id' => $pageId ?: generateId(),
        'slug' => trim($_POST['slug'] ?? ''),
        'title' => trim($_POST['title'] ?? ''),
        'content' => $_POST['content'] ?? '',
        'seo_title' => trim($_POST['seo_title'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
        'enabled' => isset($_POST['enabled'])
    ];
    if ($pageId) {
        foreach ($pages as $i => $p) { if ($p['id'] === $pageId) { $pages[$i] = $pageData; break; } }
    } else {
        $pages[] = $pageData;
    }
    saveJSON('pages.json', $pages);
    header('Location: ?page=pages');
    exit;
}

// ---------- STRIPE ----------
if ($action === 'save_stripe' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $stripe = loadJSON('stripe.json');
    $stripe['public_key'] = trim($_POST['public_key'] ?? '');
    $stripe['secret_key'] = trim($_POST['secret_key'] ?? '');
    $stripe['webhook_secret'] = trim($_POST['webhook_secret'] ?? '');
    $stripe['enabled'] = isset($_POST['enabled']);
    saveJSON('stripe.json', $stripe);
    logAction('stripe_settings_saved');
    $success = 'Stripe settings saved.';
}

// ---------- MAIL ----------
if ($action === 'save_mail' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $mail = loadJSON('mailserverconfig.json');
    $mail['smtp_host'] = trim($_POST['smtp_host'] ?? '');
    $mail['smtp_port'] = intval($_POST['smtp_port'] ?? 587);
    $mail['smtp_user'] = trim($_POST['smtp_user'] ?? '');
    $mail['smtp_pass'] = trim($_POST['smtp_pass'] ?? '');
    $mail['smtp_secure'] = $_POST['smtp_secure'] ?? 'tls';
    $mail['from_email'] = trim($_POST['from_email'] ?? '');
    $mail['from_name'] = trim($_POST['from_name'] ?? '');
    $mail['followup_subject'] = trim($_POST['followup_subject'] ?? '');
    $mail['followup_body'] = $_POST['followup_body'] ?? '';
    saveJSON('mailserverconfig.json', $mail);
    logAction('mail_settings_saved');
    $success = 'Mail settings saved.';
}

// ---------- KEYHELP ----------
if ($action === 'save_keyhelp' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $kh = loadJSON('keyhelp.json');
    $kh['enabled'] = isset($_POST['enabled']);
    $kh['api_url'] = trim($_POST['api_url'] ?? '');
    $kh['api_key'] = trim($_POST['api_key'] ?? '');
    $kh['default_package_id'] = trim($_POST['default_package_id'] ?? '');
    $kh['auto_create'] = isset($_POST['auto_create']);
    saveJSON('keyhelp.json', $kh);
    logAction('keyhelp_settings_saved');
    $success = 'KeyHelp settings saved.';
}
if ($action === 'test_keyhelp' && isAdmin()) {
    $res = keyhelpTestConnection();
    if ($res['ok']) { $success = 'KeyHelp connection successful.'; }
    else { $error = 'KeyHelp connection failed: ' . ($res['error'] ?? 'unknown error'); }
    $page = 'keyhelp';
}
if ($action === 'keyhelp_suspend' && isAdmin()) {
    $res = keyhelpSuspendClient($_GET['id'] ?? '');
    $success = $res['ok'] ? 'Account suspended in KeyHelp.' : 'Failed: ' . $res['error'];
    $page = 'keyhelp';
}
if ($action === 'keyhelp_unsuspend' && isAdmin()) {
    $res = keyhelpUnsuspendClient($_GET['id'] ?? '');
    $success = $res['ok'] ? 'Account unsuspended in KeyHelp.' : 'Failed: ' . $res['error'];
    $page = 'keyhelp';
}
if ($action === 'keyhelp_delete' && isAdmin()) {
    $res = keyhelpDeleteClient($_GET['id'] ?? '');
    $success = $res['ok'] ? 'Account deleted in KeyHelp.' : 'Failed: ' . $res['error'];
    $page = 'keyhelp';
}

// ---------- COUPONS ----------
if ($action === 'save_coupon' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $coupons = loadJSON('coupons.json');
    $couponId = $_POST['id'] ?? '';
    $data = [
        'id' => $couponId ?: generateId(),
        'code' => strtoupper(trim($_POST['code'] ?? '')),
        'type' => $_POST['type'] ?? 'percent',
        'value' => floatval($_POST['value'] ?? 0),
        'max_uses' => intval($_POST['max_uses'] ?? 0),
        'uses' => 0,
        'expires' => trim($_POST['expires'] ?? ''),
        'enabled' => isset($_POST['enabled'])
    ];
    if ($couponId) {
        foreach ($coupons as $i => $c) { if ($c['id'] === $couponId) { $data['uses'] = $c['uses'] ?? 0; $coupons[$i] = $data; break; } }
    } else {
        $coupons[] = $data;
    }
    saveJSON('coupons.json', $coupons);
    logAction('coupon_saved', ['code' => $data['code']]);
    header('Location: ?page=coupons');
    exit;
}
if ($action === 'delete_coupon' && isAdmin()) {
    $coupons = loadJSON('coupons.json');
    $coupons = array_values(array_filter($coupons, fn($c) => $c['id'] !== ($_GET['id'] ?? '')));
    saveJSON('coupons.json', $coupons);
    header('Location: ?page=coupons');
    exit;
}

// ---------- WHITE-LABEL ----------
if ($action === 'save_whitelabel' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $wl = loadJSON('whitelabel.json');
    $wl['enabled'] = isset($_POST['enabled']);
    $wl['footer_text'] = trim($_POST['footer_text'] ?? '');
    $wl['hide_powered_by'] = isset($_POST['hide_powered_by']);
    $wl['custom_css'] = $_POST['custom_css'] ?? '';
    saveJSON('whitelabel.json', $wl);
    logAction('whitelabel_saved');
    $success = 'White-label settings saved.';
}

// ---------- STAFF ----------
if ($action === 'save_staff' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $staff = loadJSON('staff.json');
    $staffId = $_POST['id'] ?? '';
    $permissions = $_POST['permissions'] ?? [];
    $staffData = [
        'id' => $staffId ?: generateId(),
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'permissions' => $permissions,
        'enabled' => isset($_POST['enabled']),
        'created_at' => date('Y-m-d H:i:s')
    ];
    if (!$staffId && !empty($_POST['password'])) {
        $staffData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }
    if ($staffId) {
        foreach ($staff as $i => $s) {
            if ($s['id'] === $staffId) {
                $staffData['password'] = $s['password'];
                $staffData['created_at'] = $s['created_at'];
                if (!empty($_POST['password'])) $staffData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $staff[$i] = $staffData;
                break;
            }
        }
    } else {
        $staff[] = $staffData;
    }
    saveJSON('staff.json', $staff);
    header('Location: ?page=staff');
    exit;
}
if ($action === 'delete_staff' && isAdmin()) {
    $staff = loadJSON('staff.json');
    $staff = array_values(array_filter($staff, fn($s) => $s['id'] !== ($_GET['id'] ?? '')));
    saveJSON('staff.json', $staff);
    header('Location: ?page=staff');
    exit;
}

// ---------- CUSTOMER / SERVICE MANAGEMENT ----------
if ($action === 'reset_user_password' && $_SERVER['REQUEST_METHOD'] === 'POST' && staffCan('users')) {
    $users = loadJSON('users.json');
    $userId = $_POST['user_id'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    foreach ($users as &$u) {
        if ($u['id'] === $userId && strlen($newPass) >= 6) {
            $u['password'] = password_hash($newPass, PASSWORD_DEFAULT);
            logAction('user_password_reset', ['user_id' => $userId]);
            break;
        }
    }
    unset($u);
    saveJSON('users.json', $users);
    header('Location: ?page=user&id=' . $userId);
    exit;
}

if (in_array($action, ['service_suspend','service_activate','service_terminate','service_renew']) && staffCan('services')) {
    $services = loadJSON('services.json');
    $serviceId = $_GET['id'] ?? '';
    $returnUser = '';
    foreach ($services as &$s) {
        if ($s['id'] === $serviceId) {
            $returnUser = $s['user_id'];
            if ($action === 'service_suspend') {
                $s['status'] = 'suspended';
                if (!empty($s['keyhelp_client_id']) && keyhelpEnabled()) keyhelpSuspendClient($s['keyhelp_client_id']);
            } elseif ($action === 'service_activate') {
                $s['status'] = 'active';
                if (!empty($s['keyhelp_client_id']) && keyhelpEnabled()) keyhelpUnsuspendClient($s['keyhelp_client_id']);
            } elseif ($action === 'service_terminate') {
                $s['status'] = 'terminated';
                if (!empty($s['keyhelp_client_id']) && keyhelpEnabled()) keyhelpSuspendClient($s['keyhelp_client_id']);
            } elseif ($action === 'service_renew') {
                $base = !empty($s['next_due_date']) ? strtotime($s['next_due_date']) : time();
                $s['next_due_date'] = date('Y-m-d', strtotime(($s['billing_cycle'] === 'yearly' ? '+1 year' : '+1 month'), $base));
                $s['status'] = 'active';
            }
            logAction($action, ['service_id' => $serviceId]);
            break;
        }
    }
    unset($s);
    saveJSON('services.json', $services);
    header('Location: ' . ($_GET['return'] ?? ('?page=user&id=' . $returnUser)));
    exit;
}

// Link/attach a service to a customer manually
if ($action === 'link_service' && $_SERVER['REQUEST_METHOD'] === 'POST' && staffCan('services')) {
    $services = loadJSON('services.json');
    $products = loadJSON('products.json');
    $userId = $_POST['user_id'] ?? '';
    $productId = $_POST['product_id'] ?? '';
    $product = null;
    foreach ($products as $p) { if ($p['id'] === $productId) { $product = $p; break; } }
    if ($product) {
        $services[] = [
            'id' => generateId(),
            'user_id' => $userId,
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'category' => $product['category'],
            'price' => floatval($_POST['price'] ?? $product['price']),
            'billing_cycle' => $product['billing_cycle'],
            'status' => 'active',
            'extra_ipv4' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'next_due_date' => date('Y-m-d', strtotime($product['billing_cycle'] === 'yearly' ? '+1 year' : '+1 month')),
            'keyhelp_client_id' => trim($_POST['keyhelp_client_id'] ?? '') ?: null
        ];
        saveJSON('services.json', $services);
        logAction('service_linked', ['user_id' => $userId, 'product_id' => $productId]);
    }
    header('Location: ?page=user&id=' . $userId);
    exit;
}

// Create invoice for a user
if ($action === 'create_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST' && staffCan('invoices')) {
    $invoices = loadJSON('invoices.json');
    $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad(count($invoices) + 1, 5, '0', STR_PAD_LEFT);
    $amount = floatval($_POST['amount'] ?? 0);
    $newInvoice = [
        'id' => generateId(),
        'invoice_number' => $invoiceNumber,
        'user_id' => $_POST['user_id'] ?? '',
        'service_id' => $_POST['service_id'] ?? null,
        'items' => [['description' => trim($_POST['description'] ?? 'Service'), 'amount' => $amount]],
        'discount' => 0,
        'subtotal' => $amount,
        'tax' => 0,
        'total' => $amount,
        'status' => 'unpaid',
        'due_date' => $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days')),
        'created_at' => date('Y-m-d H:i:s'),
        'paid_at' => null
    ];
    $invoices[] = $newInvoice;
    saveJSON('invoices.json', $invoices);
    logAction('invoice_created', ['invoice_id' => $newInvoice['id']]);
    header('Location: ' . ($_POST['return'] ?? '?page=invoices'));
    exit;
}
if ($action === 'toggle_invoice_paid' && staffCan('invoices')) {
    $invoices = loadJSON('invoices.json');
    foreach ($invoices as &$inv) {
        if ($inv['id'] === ($_GET['id'] ?? '')) {
            if ($inv['status'] === 'paid') { $inv['status'] = 'unpaid'; $inv['paid_at'] = null; }
            else { $inv['status'] = 'paid'; $inv['paid_at'] = date('Y-m-d H:i:s'); }
            break;
        }
    }
    unset($inv);
    saveJSON('invoices.json', $invoices);
    header('Location: ' . ($_GET['return'] ?? '?page=invoices'));
    exit;
}

// ---------- FOLLOW-UP EMAIL (manual send) ----------
if ($action === 'send_followup' && staffCan('services')) {
    $orders = loadJSON('orders.json');
    $orderId = $_GET['id'] ?? '';
    $users = loadJSON('users.json');
    $mail = loadJSON('mailserverconfig.json');
    foreach ($orders as &$o) {
        if ($o['id'] === $orderId) {
            $user = null;
            foreach ($users as $u) { if ($u['id'] === $o['user_id']) { $user = $u; break; } }
            if ($user) {
                $subject = $mail['followup_subject'] ?? 'Follow-up on your order';
                $body = str_replace(['{name}', '{product}'], [$user['name'], $o['product_name']], $mail['followup_body'] ?? '');
                sendMail($user['email'], $subject, $body);
                $o['followup_sent'] = true;
                $o['followup_sent_at'] = date('Y-m-d H:i:s');
                logAction('followup_sent', ['order_id' => $orderId]);
            }
            break;
        }
    }
    unset($o);
    saveJSON('orders.json', $orders);
    $success = 'Follow-up email sent.';
    $page = 'orders';
}

// Reload fresh data
$settings = getSettings();
$theme = getTheme();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="icon" href="../<?php echo htmlspecialchars($theme['favicon'] ?? 'favicon.ico'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php if ($page === 'login'): ?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="../<?php echo htmlspecialchars($theme['logo'] ?? 'logo.png'); ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>">
            <h1>Admin Login</h1>
            <p>Sign in to the control panel</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST" action="?action=login">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Log in</button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="dashboard">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <a href="../"><img src="../<?php echo htmlspecialchars($theme['logo'] ?? 'logo.png'); ?>" alt="Logo"></a>
        </div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item"><a href="?page=dashboard" class="sidebar-nav-link <?php echo $page==='dashboard'?'active':''; ?>">Dashboard</a></li>
            <?php if (staffCan('products')): ?><li class="sidebar-nav-item"><a href="?page=products" class="sidebar-nav-link <?php echo in_array($page,['products','product'])?'active':''; ?>">Products</a></li><?php endif; ?>
            <?php if (isAdmin()): ?><li class="sidebar-nav-item"><a href="?page=categories" class="sidebar-nav-link <?php echo $page==='categories'?'active':''; ?>">Categories</a></li><?php endif; ?>
            <?php if (staffCan('users')): ?><li class="sidebar-nav-item"><a href="?page=users" class="sidebar-nav-link <?php echo in_array($page,['users','user'])?'active':''; ?>">Customers</a></li><?php endif; ?>
            <?php if (staffCan('services')): ?><li class="sidebar-nav-item"><a href="?page=services" class="sidebar-nav-link <?php echo $page==='services'?'active':''; ?>">Services</a></li><?php endif; ?>
            <?php if (staffCan('services')): ?><li class="sidebar-nav-item"><a href="?page=orders" class="sidebar-nav-link <?php echo $page==='orders'?'active':''; ?>">Orders &amp; Follow-up</a></li><?php endif; ?>
            <?php if (staffCan('invoices')): ?><li class="sidebar-nav-item"><a href="?page=invoices" class="sidebar-nav-link <?php echo $page==='invoices'?'active':''; ?>">Invoices</a></li><?php endif; ?>
            <?php if (staffCan('tickets')): ?><li class="sidebar-nav-item"><a href="?page=tickets" class="sidebar-nav-link <?php echo in_array($page,['tickets','ticket'])?'active':''; ?>">Tickets</a></li><?php endif; ?>
            <?php if (staffCan('kb')): ?><li class="sidebar-nav-item"><a href="?page=kb" class="sidebar-nav-link <?php echo in_array($page,['kb','article'])?'active':''; ?>">Knowledge Base</a></li><?php endif; ?>
            <?php if (isAdmin()): ?><li class="sidebar-nav-item"><a href="?page=pages" class="sidebar-nav-link <?php echo in_array($page,['pages','edit-page'])?'active':''; ?>">Pages</a></li><?php endif; ?>
        </ul>
        <?php if (isAdmin()): ?>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Configuration</div>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item"><a href="?page=coupons" class="sidebar-nav-link <?php echo $page==='coupons'?'active':''; ?>">Coupons</a></li>
                <li class="sidebar-nav-item"><a href="?page=keyhelp" class="sidebar-nav-link <?php echo $page==='keyhelp'?'active':''; ?>">KeyHelp</a></li>
                <li class="sidebar-nav-item"><a href="?page=whitelabel" class="sidebar-nav-link <?php echo $page==='whitelabel'?'active':''; ?>">White-label</a></li>
                <li class="sidebar-nav-item"><a href="?page=stripe" class="sidebar-nav-link <?php echo $page==='stripe'?'active':''; ?>">Stripe</a></li>
                <li class="sidebar-nav-item"><a href="?page=mail" class="sidebar-nav-link <?php echo $page==='mail'?'active':''; ?>">Mail</a></li>
                <li class="sidebar-nav-item"><a href="?page=staff" class="sidebar-nav-link <?php echo in_array($page,['staff','edit-staff'])?'active':''; ?>">Staff</a></li>
                <li class="sidebar-nav-item"><a href="?page=settings" class="sidebar-nav-link <?php echo $page==='settings'?'active':''; ?>">Settings</a></li>
                <li class="sidebar-nav-item"><a href="?page=theme" class="sidebar-nav-link <?php echo $page==='theme'?'active':''; ?>">Theme</a></li>
                <li class="sidebar-nav-item"><a href="?page=logs" class="sidebar-nav-link <?php echo $page==='logs'?'active':''; ?>">Logs</a></li>
                <li class="sidebar-nav-item"><a href="?page=password" class="sidebar-nav-link <?php echo $page==='password'?'active':''; ?>">Password</a></li>
            </ul>
        </div>
        <?php endif; ?>
        <div class="sidebar-section">
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item"><a href="?action=logout" class="sidebar-nav-link">Log out</a></li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php include __DIR__ . '/pages.php'; ?>
    </main>
</div>
<?php endif; ?>
</body>
</html>
