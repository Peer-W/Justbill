<?php
$root = dirname(__DIR__);
$dataPath = $root . '/data';
$marker = $dataPath . '/installed.json';
if (file_exists($marker)) {
    http_response_code(410);
    exit('This installer has already been completed.');
}
if (!is_dir($dataPath)) mkdir($dataPath, 0755, true);
$message = '';
$error = '';
$values = [
    'site_name' => 'JustBill',
    'language_mode' => 'auto',
    'admin_path' => 'admin',
    'custom_css' => ''
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['site_name'] = trim($_POST['site_name'] ?? '');
    $values['language_mode'] = $_POST['language_mode'] ?? 'auto';
    $values['admin_path'] = trim($_POST['admin_path'] ?? 'admin');
    $values['custom_css'] = trim($_POST['custom_css'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $allowedLanguages = ['auto', 'nl', 'en'];
    $adminPath = trim($values['admin_path'], '/');
    if ($values['site_name'] === '' || strlen($values['site_name']) > 80) $error = 'Please enter a site name up to 80 characters.';
    elseif (!in_array($values['language_mode'], $allowedLanguages, true)) $error = 'Please select a valid language mode.';
    elseif (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $adminPath)) $error = 'The admin URL may contain only letters, numbers, hyphens, and underscores.';
    elseif (in_array($adminPath, ['install', 'api', 'assets', 'includes', 'client', 'kb', 'forum'], true)) $error = 'That admin URL is reserved by the application.';
    elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) $error = 'Please choose a valid admin username.';
    elseif (strlen($password) < 10) $error = 'Choose an admin password with at least 10 characters.';
    elseif ($password !== $confirm) $error = 'The passwords do not match.';
    else {
        $logoPath = 'logo.png';
        if (!empty($_FILES['logo']['tmp_name'])) {
            $imageInfo = @getimagesize($_FILES['logo']['tmp_name']);
            $extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!$imageInfo || !in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) $error = 'Upload a valid PNG, JPG, JPEG, or WebP logo.';
            else {
                $logoPath = 'uploads/logo.' . ($extension === 'jpeg' ? 'jpg' : $extension);
                if (!is_dir($root . '/uploads')) mkdir($root . '/uploads', 0755, true);
                move_uploaded_file($_FILES['logo']['tmp_name'], $root . '/' . $logoPath);
            }
        }
        if ($error === '') {
            $write = function ($file, $data) use ($dataPath) { return file_put_contents($dataPath . '/' . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false; };
            $settings = [
                'site_name' => $values['site_name'],
                'site_description' => 'Hosting, VPS and web development',
                'company_email' => '',
                'company_phone' => '',
                'currency' => 'EUR',
                'currency_symbol' => '€',
                'ipv4_price' => 1.50,
                'language_mode' => $values['language_mode'],
                'admin_path' => $adminPath
            ];
            $theme = ['primary_color' => '#2563eb', 'secondary_color' => '#0ea5e9', 'background_color' => '#0f172a', 'card_color' => '#1e293b', 'text_color' => '#f8fafc', 'muted_color' => '#94a3b8', 'logo' => $logoPath, 'favicon' => 'favicon.ico', 'custom_css' => $values['custom_css']];
            $auth = ['username' => $username, 'password' => password_hash($password, PASSWORD_DEFAULT)];
            $write('settings.json', $settings);
            $write('theme.json', $theme);
            $write('auth.json', $auth);
            $write('whitelabel.json', ['enabled' => true, 'footer_text' => '', 'hide_powered_by' => true, 'custom_css' => $values['custom_css']]);
            $write('installed.json', ['installed_at' => date('c'), 'admin_path' => $adminPath]);
            $rewrite = "RewriteEngine On\nRewriteRule ^" . preg_quote($adminPath, '/') . "/?$ admin/index.php [L]\n";
            file_put_contents($root . '/.htaccess', $rewrite);
            $message = 'Installation complete. Confirm below to remove the installer.';
        }
    }
}
if (isset($_POST['confirm_cleanup']) && file_exists($marker)) {
    $cleanup = __DIR__;
    $message = 'The installer is disabled. You can now sign in at /' . json_decode(file_get_contents($marker), true)['admin_path'] . '.';
    @unlink(__FILE__);
    @rmdir($cleanup);
}
$language = $_POST['language_mode'] ?? 'auto';
$copy = $language === 'nl' ? ['title' => 'JustBill instellen', 'intro' => 'Configureer uw platform voordat u begint.'] : ['title' => 'Set up JustBill', 'intro' => 'Configure your platform before you begin.'];
?><!doctype html><html lang="<?php echo $language === 'nl' ? 'nl' : 'en'; ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo htmlspecialchars($copy['title']); ?></title><style><?php echo htmlspecialchars($values['custom_css']); ?>:root{font-family:Inter,Arial,sans-serif;color:#e5eefb;background:#08111f}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}.card{width:min(720px,100%);background:#101d30;border:1px solid #243b59;border-radius:18px;padding:32px;box-sizing:border-box;box-shadow:0 24px 80px #0007}h1{margin-top:0}p{color:#9fb1c9;line-height:1.6}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media(max-width:620px){.grid{grid-template-columns:1fr}}label{display:block;font-weight:700;margin:14px 0 7px}input,select,textarea{width:100%;box-sizing:border-box;padding:12px;border-radius:9px;border:1px solid #38516e;background:#0a1524;color:#fff}textarea{min-height:120px}.full{grid-column:1/-1}button{margin-top:22px;background:#2563eb;color:white;border:0;border-radius:9px;padding:13px 18px;font-weight:700;cursor:pointer}.alert{padding:13px;border-radius:9px;background:#173c2b;margin:16px 0}.error{background:#55242b}.note{font-size:13px}</style></head><body><main class="card"><h1><?php echo htmlspecialchars($copy['title']); ?></h1><p><?php echo htmlspecialchars($copy['intro']); ?></p><?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?><?php if ($message): ?><div class="alert"><?php echo htmlspecialchars($message); ?></div><?php if (file_exists($marker)): ?><form method="post"><input type="hidden" name="confirm_cleanup" value="1"><p class="note">The installer should be disabled after this confirmation. If your host does not allow automatic removal, delete the install folder manually.</p><button type="submit">Confirm and disable installer</button></form><?php endif; ?><?php else: ?><form method="post" enctype="multipart/form-data"><div class="grid"><div><label>Site name</label><input name="site_name" value="<?php echo htmlspecialchars($values['site_name']); ?>" required></div><div><label>Language</label><select name="language_mode"><option value="auto" <?php echo $values['language_mode'] === 'auto' ? 'selected' : ''; ?>>Automatic: Belgium Dutch, other countries English</option><option value="nl" <?php echo $values['language_mode'] === 'nl' ? 'selected' : ''; ?>>Dutch</option><option value="en" <?php echo $values['language_mode'] === 'en' ? 'selected' : ''; ?>>English</option></select></div><div><label>Admin URL</label><input name="admin_path" value="<?php echo htmlspecialchars($values['admin_path']); ?>" required><p class="note">Example: /admin or /worker</p></div><div><label>Logo</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div><div><label>Admin username</label><input name="username" required></div><div><label>Admin password</label><input type="password" name="password" minlength="10" required></div><div><label>Confirm password</label><input type="password" name="confirm_password" minlength="10" required></div><div class="full"><label>Custom CSS (optional)</label><textarea name="custom_css" placeholder="Add your own CSS rules here"></textarea></div></div><button type="submit">Install MoreBilling</button></form><?php endif; ?></main></body></html>
