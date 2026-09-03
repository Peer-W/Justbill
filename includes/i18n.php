<?php
function supportedLanguages() {
    return ['nl', 'en'];
}

function visitorLanguage() {
    $settings = getSettings();
    $mode = $settings['language_mode'] ?? 'auto';
    if (in_array($mode, supportedLanguages(), true)) return $mode;
    if (!empty($_GET['lang']) && in_array($_GET['lang'], supportedLanguages(), true)) {
        setcookie('morebilling_lang', $_GET['lang'], time() + 31536000, '/');
        return $_GET['lang'];
    }
    if (!empty($_COOKIE['morebilling_lang']) && in_array($_COOKIE['morebilling_lang'], supportedLanguages(), true)) return $_COOKIE['morebilling_lang'];
    $country = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
    if ($country === '') $country = strtoupper($_SERVER['GEOIP_COUNTRY_CODE'] ?? '');
    return in_array($country, ['BE', 'NL'], true) ? 'nl' : 'en';
}

function t($key, $language = null) {
    $language = $language ?: visitorLanguage();
    $translations = [
        'en' => ['home' => 'Home', 'dashboard' => 'Dashboard', 'login' => 'Log in', 'support' => 'Support', 'products' => 'Products', 'choose_language' => 'Language'],
        'nl' => ['home' => 'Home', 'dashboard' => 'Dashboard', 'login' => 'Inloggen', 'support' => 'Support', 'products' => 'Producten', 'choose_language' => 'Taal']
    ];
    return $translations[$language][$key] ?? $key;
}

function languageSwitcher() {
    $current = visitorLanguage();
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return '<nav aria-label="' . htmlspecialchars(t('choose_language')) . '"><a href="' . htmlspecialchars($path) . '?lang=nl"' . ($current === 'nl' ? ' aria-current="page"' : '') . '>NL</a> <a href="' . htmlspecialchars($path) . '?lang=en"' . ($current === 'en' ? ' aria-current="page"' : '') . '>EN</a></nav>';
}
