<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/keyhelp.php';

$settings = getSettings();
$theme = getTheme();

// Handle actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$page = $_GET['page'] ?? 'dashboard';
$error = '';
$success = '';

// Handle logout
if ($action === 'logout') {
    unset($_SESSION['user_id']);
    header('Location: ?page=login');
    exit;
}

// Handle registration
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = loadJSON('users.json');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if (empty($email) || empty($password) || empty($name)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        foreach ($users as $user) {
            if ($user['email'] === $email) {
                $error = 'Email address is already in use.';
                break;
            }
        }

        if (!$error) {
            $newUser = [
                'id' => generateId(),
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => null,
                'last_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'email_verified' => false
            ];
            $users[] = $newUser;
            saveJSON('users.json', $users);
            logAction('user_registered', ['user_id' => $newUser['id'], 'email' => $email]);
            $_SESSION['user_id'] = $newUser['id'];
            header('Location: ?page=dashboard');
            exit;
        }
    }
    $page = 'register';
}

// Handle login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = loadJSON('users.json');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $foundUser = null;
    foreach ($users as &$user) {
        if ($user['email'] === $email && password_verify($password, $user['password'])) {
            $foundUser = $user;
            $user['last_login'] = date('Y-m-d H:i:s');
            $user['last_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            break;
        }
    }
    unset($user);

    if ($foundUser) {
        saveJSON('users.json', $users);
        $_SESSION['user_id'] = $foundUser['id'];
        logAction('user_login', ['user_id' => $foundUser['id']], $foundUser['id']);
        header('Location: ?page=dashboard');
        exit;
    } else {
        $error = 'Invalid login credentials.';
    }
    $page = 'login';
}

// Require login for protected pages
$publicPages = ['login', 'register', 'forgot-password'];
if (!in_array($page, $publicPages) && !isLoggedIn()) {
    header('Location: ?page=login');
    exit;
}

$currentUser = getCurrentUser();

// Handle ticket creation
if ($action === 'create_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $tickets = loadJSON('tickets.json');
    $newTicket = [
        'id' => generateId(),
        'user_id' => $currentUser['id'],
        'subject' => trim($_POST['subject'] ?? ''),
        'department' => $_POST['department'] ?? 'support',
        'priority' => $_POST['priority'] ?? 'medium',
        'status' => 'open',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'messages' => [
            [
                'id' => generateId(),
                'user_id' => $currentUser['id'],
                'message' => trim($_POST['message'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'is_staff' => false
            ]
        ]
    ];
    $tickets[] = $newTicket;
    saveJSON('tickets.json', $tickets);
    logAction('ticket_created', ['ticket_id' => $newTicket['id']], $currentUser['id']);
    header('Location: ?page=ticket&id=' . $newTicket['id']);
    exit;
}

// Handle ticket reply
if ($action === 'reply_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $tickets = loadJSON('tickets.json');
    $ticketId = $_POST['ticket_id'] ?? '';
    $message = trim($_POST['message'] ?? '');

    foreach ($tickets as &$ticket) {
        if ($ticket['id'] === $ticketId && $ticket['user_id'] === $currentUser['id']) {
            $ticket['messages'][] = [
                'id' => generateId(),
                'user_id' => $currentUser['id'],
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
                'is_staff' => false
            ];
            $ticket['updated_at'] = date('Y-m-d H:i:s');
            $ticket['status'] = 'open';
            break;
        }
    }
    unset($ticket);
    saveJSON('tickets.json', $tickets);
    header('Location: ?page=ticket&id=' . $ticketId);
    exit;
}

// Handle account update
if ($action === 'update_account' && $_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $users = loadJSON('users.json');
    $newName = trim($_POST['name'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';

    foreach ($users as &$user) {
        if ($user['id'] === $currentUser['id']) {
            if (!empty($newName)) {
                $user['name'] = $newName;
            }
            if (!empty($newPassword)) {
                if (password_verify($currentPassword, $user['password'])) {
                    $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    $success = 'Password changed successfully.';
                } else {
                    $error = 'Current password is incorrect.';
                }
            } else {
                $success = 'Account updated.';
            }
            break;
        }
    }
    unset($user);
    saveJSON('users.json', $users);
    $currentUser = getCurrentUser();
    $page = 'settings';
}

// Handle order placement
if ($action === 'place_order' && $_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $products = loadJSON('products.json');
    $productId = $_POST['product_id'] ?? '';
    $product = null;
    foreach ($products as $p) {
        if ($p['id'] === $productId && $p['enabled']) {
            $product = $p;
            break;
        }
    }

    if (!$product) {
        $error = 'Product not found.';
        $page = 'products-error';
    } else {
        // Base price
        $lineItems = [];
        $subtotal = floatval($product['price']);
        $lineItems[] = ['description' => $product['name'], 'amount' => floatval($product['price'])];

        // Extra IPv4 for VPS
        $extraIpv4 = 0;
        if (!empty($product['is_vps'])) {
            $extraIpv4 = max(0, intval($_POST['extra_ipv4'] ?? 0));
            $extraIpv4 = min($extraIpv4, 16); // sane cap
            if ($extraIpv4 > 0) {
                $ipv4Price = floatval($settings['ipv4_price'] ?? 1.50);
                $ipv4Total = $extraIpv4 * $ipv4Price;
                $subtotal += $ipv4Total;
                $lineItems[] = ['description' => $extraIpv4 . ' x Extra IPv4', 'amount' => $ipv4Total];
            }
        }

        // Coupon
        $discount = 0;
        $couponCode = strtoupper(trim($_POST['coupon'] ?? ''));
        $appliedCoupon = null;
        if ($couponCode) {
            $coupons = loadJSON('coupons.json');
            foreach ($coupons as $c) {
                if (strtoupper($c['code']) === $couponCode && !empty($c['enabled'])) {
                    if ((!empty($c['expires']) && strtotime($c['expires']) < time())) continue;
                    if (isset($c['max_uses']) && $c['max_uses'] > 0 && ($c['uses'] ?? 0) >= $c['max_uses']) continue;
                    $appliedCoupon = $c;
                    if ($c['type'] === 'percent') {
                        $discount = round($subtotal * (floatval($c['value']) / 100), 2);
                    } else {
                        $discount = min($subtotal, floatval($c['value']));
                    }
                    break;
                }
            }
            if (!$appliedCoupon) {
                $error = 'Invalid or expired coupon code.';
            }
        }

        if (!$error) {
            $total = max(0, $subtotal - $discount);

            // Increment coupon usage
            if ($appliedCoupon) {
                $coupons = loadJSON('coupons.json');
                foreach ($coupons as &$c) {
                    if ($c['id'] === $appliedCoupon['id']) {
                        $c['uses'] = ($c['uses'] ?? 0) + 1;
                        break;
                    }
                }
                unset($c);
                saveJSON('coupons.json', $coupons);
            }

            // Create service
            $services = loadJSON('services.json');
            $serviceId = generateId();
            $nextDue = date('Y-m-d', strtotime($product['billing_cycle'] === 'yearly' ? '+1 year' : '+1 month'));
            $newService = [
                'id' => $serviceId,
                'user_id' => $currentUser['id'],
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'category' => $product['category'],
                'price' => $total,
                'billing_cycle' => $product['billing_cycle'],
                'status' => 'pending',
                'extra_ipv4' => $extraIpv4,
                'created_at' => date('Y-m-d H:i:s'),
                'next_due_date' => $nextDue,
                'keyhelp_client_id' => null
            ];
            $services[] = $newService;
            saveJSON('services.json', $services);

            // Create invoice
            $invoices = loadJSON('invoices.json');
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad(count($invoices) + 1, 5, '0', STR_PAD_LEFT);
            $invoiceId = generateId();
            $invoices[] = [
                'id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'user_id' => $currentUser['id'],
                'service_id' => $serviceId,
                'items' => $lineItems,
                'discount' => $discount,
                'coupon' => $appliedCoupon['code'] ?? null,
                'subtotal' => $subtotal,
                'tax' => 0,
                'total' => $total,
                'status' => $total > 0 ? 'unpaid' : 'paid',
                'due_date' => date('Y-m-d', strtotime('+14 days')),
                'created_at' => date('Y-m-d H:i:s'),
                'paid_at' => $total > 0 ? null : date('Y-m-d H:i:s')
            ];
            saveJSON('invoices.json', $invoices);

            // Create order record (used for the 24 work-hour follow-up)
            $orders = loadJSON('orders.json');
            $orders[] = [
                'id' => generateId(),
                'user_id' => $currentUser['id'],
                'service_id' => $serviceId,
                'invoice_id' => $invoiceId,
                'product_name' => $product['name'],
                'total' => $total,
                'status' => 'new',
                'followup_sent' => false,
                'followup_due' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            saveJSON('orders.json', $orders);

            logAction('order_placed', ['service_id' => $serviceId, 'total' => $total], $currentUser['id']);

            // Optional: auto-create KeyHelp account for webhosting
            $khConfig = keyhelpConfig();
            if ($product['category'] === 'webhosting' && keyhelpEnabled() && !empty($khConfig['auto_create'])) {
                $tempPass = bin2hex(random_bytes(6));
                $username = 'u' . substr($currentUser['id'], 0, 8);
                $res = keyhelpCreateClient([
                    'username' => $username,
                    'password' => $tempPass,
                    'email' => $currentUser['email'],
                    'name' => $currentUser['name'],
                    'hosting_plan_id' => $product['keyhelp_package_id'] ?? ''
                ]);
                if ($res['ok']) {
                    $services = loadJSON('services.json');
                    foreach ($services as &$s) {
                        if ($s['id'] === $serviceId) {
                            $s['status'] = 'active';
                            $s['keyhelp_client_id'] = $res['data']['id'] ?? ($res['data']['username'] ?? $username);
                            $s['keyhelp_username'] = $username;
                            break;
                        }
                    }
                    unset($s);
                    saveJSON('services.json', $services);
                    logAction('keyhelp_account_created', ['service_id' => $serviceId], $currentUser['id']);
                }
            }

            header('Location: ?page=order-success&service=' . $serviceId);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="icon" href="../<?php echo htmlspecialchars($theme['favicon'] ?? 'favicon.ico'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php if (in_array($page, $publicPages)): ?>
    <!-- Auth Pages -->
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="../">
                    <img src="../<?php echo htmlspecialchars($theme['logo'] ?? 'logo.png'); ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>">
                </a>
                <?php if ($page === 'login'): ?>
                <h1>Log in</h1>
                <p>Log in to your account</p>
                <?php elseif ($page === 'register'): ?>
                <h1>Sign up</h1>
                <p>Create a new account</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($page === 'login'): ?>
            <form method="POST" action="?action=login">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Log in</button>
            </form>
            <div class="auth-footer">
                <p>No account yet? <a href="?page=register">Sign up</a></p>
            </div>

            <?php elseif ($page === 'register'): ?>
            <form method="POST" action="?action=register">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Sign up</button>
            </form>
            <div class="auth-footer">
                <p>Already have an account? <a href="?page=login">Log in</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- Dashboard -->
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <a href="../">
                    <img src="../<?php echo htmlspecialchars($theme['logo'] ?? 'logo.png'); ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>">
                </a>
            </div>

            <ul class="sidebar-nav">
                <li class="sidebar-nav-item">
                    <a href="?page=dashboard" class="sidebar-nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="?page=services" class="sidebar-nav-link <?php echo $page === 'services' ? 'active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                        Services
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="?page=invoices" class="sidebar-nav-link <?php echo $page === 'invoices' ? 'active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Invoices
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="?page=tickets" class="sidebar-nav-link <?php echo in_array($page, ['tickets', 'ticket', 'new-ticket']) ? 'active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        Tickets
                    </a>
                </li>
            </ul>

            <div class="sidebar-section">
                <div class="sidebar-section-title">Account</div>
                <ul class="sidebar-nav">
                    <li class="sidebar-nav-item">
                        <a href="?page=settings" class="sidebar-nav-link <?php echo $page === 'settings' ? 'active' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Settings
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="?action=logout" class="sidebar-nav-link">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Log out
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <main class="main-content">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($page === 'dashboard'): ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title">Welcome, <?php echo htmlspecialchars($currentUser['name']); ?></h1>
                    <p class="page-description">Manage your services and view your invoices</p>
                </div>
            </div>

            <?php
            $services = loadJSON('services.json');
            $userServices = array_filter($services, fn($s) => $s['user_id'] === $currentUser['id']);
            $activeServices = array_filter($userServices, fn($s) => $s['status'] === 'active');

            $invoices = loadJSON('invoices.json');
            $userInvoices = array_filter($invoices, fn($i) => $i['user_id'] === $currentUser['id']);
            $unpaidInvoices = array_filter($userInvoices, fn($i) => $i['status'] === 'unpaid');

            $tickets = loadJSON('tickets.json');
            $userTickets = array_filter($tickets, fn($t) => $t['user_id'] === $currentUser['id']);
            $openTickets = array_filter($userTickets, fn($t) => $t['status'] === 'open');
            ?>

            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <div class="dashboard-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                    </div>
                    <div class="dashboard-card-value"><?php echo count($activeServices); ?></div>
                    <div class="dashboard-card-label">Active Services</div>
                </div>
                <div class="dashboard-card">
                    <div class="dashboard-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="dashboard-card-value"><?php echo count($unpaidInvoices); ?></div>
                    <div class="dashboard-card-label">Open Invoices</div>
                </div>
                <div class="dashboard-card">
                    <div class="dashboard-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    </div>
                    <div class="dashboard-card-value"><?php echo count($openTickets); ?></div>
                    <div class="dashboard-card-label">Open Tickets</div>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">Recent Services</h3>
                <?php if (empty($userServices)): ?>
                <p class="text-muted mt-3">You don't have any services yet. <a href="../?page=products">Browse our products</a></p>
                <?php else: ?>
                <div class="table-container mt-3">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Next Invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($userServices, 0, 5) as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['product_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $service['status'] === 'active' ? 'success' : ($service['status'] === 'suspended' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($service['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($service['next_due_date'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($page === 'services'): ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title">My Services</h1>
                    <p class="page-description">Manage your active services</p>
                </div>
                <a href="../?page=products" class="btn btn-primary">New Service</a>
            </div>

            <?php
            $services = loadJSON('services.json');
            $userServices = array_filter($services, fn($s) => $s['user_id'] === $currentUser['id']);
            ?>

            <?php if (empty($userServices)): ?>
            <div class="card">
                <p class="text-muted">You don't have any services yet. <a href="../?page=products">Browse our products</a></p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Next Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userServices as $service): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($service['product_name']); ?>
                                <?php if (!empty($service['extra_ipv4'])): ?>
                                <span class="badge badge-secondary"><?php echo (int)$service['extra_ipv4']; ?> extra IPv4</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatPrice($service['price']); ?>/<?php echo $service['billing_cycle'] === 'monthly' ? 'mo' : 'yr'; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $service['status'] === 'active' ? 'success' : ($service['status'] === 'suspended' ? 'warning' : 'danger'); ?>">
                                    <?php echo ucfirst($service['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($service['next_due_date'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php elseif ($page === 'invoices'): ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title">Invoices</h1>
                    <p class="page-description">View and pay your invoices</p>
                </div>
            </div>

            <?php
            $invoices = loadJSON('invoices.json');
            $userInvoices = array_filter($invoices, fn($i) => $i['user_id'] === $currentUser['id']);
            $stripe = loadJSON('stripe.json');
            ?>

            <?php if (empty($userInvoices)): ?>
            <div class="card">
                <p class="text-muted">You don't have any invoices yet.</p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userInvoices as $invoice): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['created_at']); ?></td>
                            <td><?php echo formatPrice($invoice['total']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'unpaid' ? 'warning' : 'danger'); ?>">
                                    <?php echo $invoice['status'] === 'paid' ? 'Paid' : ($invoice['status'] === 'unpaid' ? 'Unpaid' : 'Overdue'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($invoice['status'] !== 'paid'): ?>
                                    <?php if (!empty($stripe['enabled'])): ?>
                                    <a href="?page=pay&invoice=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-primary">Pay with card</a>
                                    <?php endif; ?>
                                    <a href="?page=invoice&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline">Bank transfer</a>
                                <?php else: ?>
                                    <a href="?page=invoice&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php elseif ($page === 'invoice'): ?>
            <?php
            $invoiceId = $_GET['id'] ?? '';
            $invoices = loadJSON('invoices.json');
            $invoice = null;
            foreach ($invoices as $inv) {
                if ($inv['id'] === $invoiceId && $inv['user_id'] === $currentUser['id']) { $invoice = $inv; break; }
            }
            ?>
            <?php if (!$invoice): ?>
            <div class="alert alert-danger">Invoice not found.</div>
            <?php else: ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title">Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
                    <p class="page-description">Created on <?php echo htmlspecialchars($invoice['created_at']); ?></p>
                </div>
                <a href="?page=invoices" class="btn btn-outline">&larr; Back</a>
            </div>
            <div class="card" style="max-width: 700px;">
                <table class="table">
                    <thead><tr><th>Description</th><th style="text-align:right;">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($invoice['items'] as $item): ?>
                        <tr><td><?php echo htmlspecialchars($item['description']); ?></td><td style="text-align:right;"><?php echo formatPrice($item['amount']); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!empty($invoice['discount'])): ?>
                        <tr><td>Discount<?php echo !empty($invoice['coupon']) ? ' (' . htmlspecialchars($invoice['coupon']) . ')' : ''; ?></td><td style="text-align:right;">-<?php echo formatPrice($invoice['discount']); ?></td></tr>
                        <?php endif; ?>
                        <tr><td><strong>Total</strong></td><td style="text-align:right;"><strong><?php echo formatPrice($invoice['total']); ?></strong></td></tr>
                    </tbody>
                </table>
                <?php if ($invoice['status'] !== 'paid'): ?>
                <div class="alert alert-warning mt-3">
                    <strong>Bank transfer:</strong> please transfer <?php echo formatPrice($invoice['total']); ?> stating invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?>.
                    Your service is activated after we receive the payment.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php elseif ($page === 'pay'): ?>
            <?php
            $invoiceId = $_GET['invoice'] ?? '';
            $stripe = loadJSON('stripe.json');
            ?>
            <div class="page-header"><div><h1 class="page-title">Pay Invoice</h1></div></div>
            <div class="card" style="max-width:600px;">
                <?php if (empty($stripe['enabled'])): ?>
                <div class="alert alert-warning">Online card payments are currently disabled. Please use bank transfer instead.</div>
                <a href="?page=invoice&id=<?php echo urlencode($invoiceId); ?>" class="btn btn-primary">View bank transfer details</a>
                <?php else: ?>
                <p>You will be redirected to our secure Stripe checkout.</p>
                <form method="POST" action="../api/stripe.php">
                    <input type="hidden" name="action" value="create_checkout">
                    <input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($invoiceId); ?>">
                    <button type="submit" class="btn btn-primary btn-lg">Continue to payment</button>
                </form>
                <?php endif; ?>
            </div>

            <?php elseif ($page === 'tickets'): ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title">Support Tickets</h1>
                    <p class="page-description">View and manage your support tickets</p>
                </div>
                <a href="?page=new-ticket" class="btn btn-primary">New Ticket</a>
            </div>

            <?php
            $tickets = loadJSON('tickets.json');
            $userTickets = array_filter($tickets, fn($t) => $t['user_id'] === $currentUser['id']);
            usort($userTickets, fn($a, $b) => strtotime($b['updated_at']) - strtotime($a['updated_at']));
            ?>

            <?php if (empty($userTickets)): ?>
            <div class="card">
                <p class="text-muted">You don't have any tickets yet. <a href="?page=new-ticket">Create a new ticket</a></p>
            </div>
            <?php else: ?>
            <div class="ticket-list">
                <?php foreach ($userTickets as $ticket): ?>
                <a href="?page=ticket&id=<?php echo $ticket['id']; ?>" class="ticket-item">
                    <div class="ticket-info">
                        <h4><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                        <div class="ticket-meta">
                            <span>#<?php echo substr($ticket['id'], 0, 8); ?></span>
                            <span><?php echo htmlspecialchars($ticket['created_at']); ?></span>
                        </div>
                    </div>
                    <span class="badge badge-<?php echo $ticket['status'] === 'open' ? 'success' : ($ticket['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                        <?php echo $ticket['status'] === 'open' ? 'Open' : ($ticket['status'] === 'in_progress' ? 'In progress' : 'Closed'); ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php elseif ($page === 'new-ticket'): ?>
            <?php
            $ticketType = $_GET['type'] ?? '';
            $isWebdev = $ticketType === 'webdev';
            ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title"><?php echo $isWebdev ? 'Request a Custom Build' : 'New Ticket'; ?></h1>
                    <p class="page-description"><?php echo $isWebdev ? 'Tell us about your custom website or Node.js project' : 'Open a new support ticket'; ?></p>
                </div>
            </div>

            <div class="card" style="max-width: 600px;">
                <form method="POST" action="?action=create_ticket">
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-input" value="<?php echo $isWebdev ? 'Custom build request' : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <?php if ($isWebdev): ?>
                            <option value="webdevelopment" selected>Web Development</option>
                            <?php endif; ?>
                            <option value="support">Technical Support</option>
                            <option value="billing">Billing</option>
                            <option value="sales">Sales</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Normal</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $isWebdev ? 'Describe your project (type of site, Node.js features, deadline, budget)' : 'Message'; ?></label>
                        <textarea name="message" class="form-textarea" rows="6" required><?php echo $isWebdev ? "Project type (website / Node.js program):\nDesired features:\nDeadline:\nBudget:" : ''; ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                </form>
            </div>

            <?php elseif ($page === 'ticket'): ?>
            <?php
            $ticketId = $_GET['id'] ?? '';
            $tickets = loadJSON('tickets.json');
            $ticket = null;
            foreach ($tickets as $t) {
                if ($t['id'] === $ticketId && $t['user_id'] === $currentUser['id']) {
                    $ticket = $t;
                    break;
                }
            }
            ?>

            <?php if (!$ticket): ?>
            <div class="alert alert-danger">Ticket not found.</div>
            <?php else: ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
                    <p class="page-description">Ticket #<?php echo substr($ticket['id'], 0, 8); ?> - <?php echo $ticket['status'] === 'open' ? 'Open' : ($ticket['status'] === 'in_progress' ? 'In progress' : 'Closed'); ?></p>
                </div>
                <a href="?page=tickets" class="btn btn-outline">&larr; Back</a>
            </div>

            <div class="card">
                <div class="ticket-chat">
                    <div class="ticket-messages">
                        <?php foreach ($ticket['messages'] as $msg): ?>
                        <div class="message <?php echo $msg['is_staff'] ? 'message-staff' : 'message-user'; ?>">
                            <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                            <div class="message-meta"><?php echo htmlspecialchars($msg['created_at']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($ticket['status'] !== 'closed'): ?>
                <form method="POST" action="?action=reply_ticket" class="mt-4">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                    <div class="form-group">
                        <label class="form-label">Reply</label>
                        <textarea name="message" class="form-textarea" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php elseif ($page === 'settings'): ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title">Account Settings</h1>
                    <p class="page-description">Manage your account details</p>
                </div>
            </div>

            <div class="card" style="max-width: 600px;">
                <form method="POST" action="?action=update_account">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($currentUser['name']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" value="<?php echo htmlspecialchars($currentUser['email']); ?>" disabled>
                    </div>

                    <h3 class="mt-4 mb-3">Change Password</h3>
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </div>

            <?php elseif ($page === 'order'): ?>
            <?php
            $productId = $_GET['product'] ?? '';
            $products = loadJSON('products.json');
            $product = null;
            foreach ($products as $p) {
                if ($p['id'] === $productId && $p['enabled']) {
                    $product = $p;
                    break;
                }
            }
            $ipv4Price = floatval($settings['ipv4_price'] ?? 1.50);
            ?>

            <?php if (!$product): ?>
            <div class="alert alert-danger">Product not found.</div>
            <?php elseif (!empty($product['is_quote'])): ?>
            <div class="alert alert-warning">This product is quote-based. <a href="?page=new-ticket&type=webdev">Request a quote</a>.</div>
            <?php else: ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title">Order: <?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="page-description">Complete your order</p>
                </div>
            </div>

            <div class="card" style="max-width: 600px;">
                <div class="mb-4">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="text-muted"><?php echo htmlspecialchars($product['description']); ?></p>
                    <div class="product-price mt-3">
                        <?php echo formatPrice($product['price']); ?>
                        <span>/<?php echo $product['billing_cycle'] === 'monthly' ? 'mo' : 'yr'; ?></span>
                    </div>
                    <ul class="product-features mt-3">
                        <?php foreach ($product['features'] as $feature): ?>
                        <li><?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <form method="POST" action="?action=place_order" id="orderForm">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                    <?php if (!empty($product['is_vps'])): ?>
                    <div class="form-group">
                        <label class="form-label">Extra IPv4 addresses (<?php echo formatPrice($ipv4Price); ?> each / <?php echo $product['billing_cycle'] === 'monthly' ? 'mo' : 'yr'; ?>)</label>
                        <input type="number" name="extra_ipv4" id="extraIpv4" class="form-input" min="0" max="16" value="0" data-ipv4price="<?php echo $ipv4Price; ?>" data-base="<?php echo $product['price']; ?>">
                        <small class="text-muted"><?php echo (int)($product['included_ipv4'] ?? 1); ?> IPv4 already included.</small>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Coupon code (optional)</label>
                        <input type="text" name="coupon" class="form-input" placeholder="e.g. WELCOME10">
                    </div>

                    <div class="alert" style="background:var(--muted);">
                        Estimated total: <strong id="orderTotal"><?php echo formatPrice($product['price']); ?></strong> / <?php echo $product['billing_cycle'] === 'monthly' ? 'mo' : 'yr'; ?>
                        <div class="text-muted" style="font-size:0.85rem;margin-top:4px;">Coupon discounts are applied after you place the order.</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Place Order</button>
                </form>
            </div>

            <script>
            (function(){
                var ipv4 = document.getElementById('extraIpv4');
                if (!ipv4) return;
                var base = parseFloat(ipv4.dataset.base);
                var price = parseFloat(ipv4.dataset.ipv4price);
                var totalEl = document.getElementById('orderTotal');
                var symbol = '<?php echo $settings['currency_symbol']; ?>';
                function update(){
                    var qty = Math.max(0, parseInt(ipv4.value || '0', 10));
                    var total = base + qty * price;
                    totalEl.textContent = symbol + total.toFixed(2).replace('.', ',');
                }
                ipv4.addEventListener('input', update);
            })();
            </script>
            <?php endif; ?>

            <?php elseif ($page === 'order-success'): ?>
            <?php
            $serviceId = $_GET['service'] ?? '';
            $services = loadJSON('services.json');
            $svc = null;
            foreach ($services as $s) { if ($s['id'] === $serviceId && $s['user_id'] === $currentUser['id']) { $svc = $s; break; } }
            ?>
            <div class="card" style="max-width:600px;text-align:center;">
                <h1 class="page-title">Thank you for your order!</h1>
                <?php if ($svc): ?>
                <p class="mt-3"><?php echo htmlspecialchars($svc['product_name']); ?> has been ordered.</p>
                <?php endif; ?>
                <p class="text-muted mt-2">You'll find your invoice under Invoices. Our team will follow up within 24 working hours to make sure everything is set up correctly.</p>
                <div class="mt-4">
                    <a href="?page=services" class="btn btn-primary">View my services</a>
                    <a href="?page=invoices" class="btn btn-outline">View invoices</a>
                </div>
            </div>

            <?php endif; ?>
        </main>
    </div>
    <?php endif; ?>
</body>
</html>
