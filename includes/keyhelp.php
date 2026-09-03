<?php
// KeyHelp API integration
// Docs: https://<your-keyhelp-server>/api/v2/docs
require_once __DIR__ . '/config.php';

function keyhelpConfig() {
    return loadJSON('keyhelp.json');
}

function keyhelpEnabled() {
    $cfg = keyhelpConfig();
    return !empty($cfg['enabled']) && !empty($cfg['api_url']) && !empty($cfg['api_key']);
}

/**
 * Low-level request to the KeyHelp API v2.
 * @return array{ok:bool, status:int, data:mixed, error:?string}
 */
function keyhelpRequest($method, $endpoint, $body = null) {
    $cfg = keyhelpConfig();
    if (empty($cfg['api_url']) || empty($cfg['api_key'])) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'KeyHelp API is not configured.'];
    }

    $base = rtrim($cfg['api_url'], '/');
    // Ensure we target the v2 API path
    if (strpos($base, '/api/v2') === false) {
        $base .= '/api/v2';
    }
    $url = $base . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);
    $headers = [
        'X-API-Key: ' . $cfg['api_key'],
        'Accept: application/json',
        'Content-Type: application/json'
    ];

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $curlErr ?: 'Request failed'];
    }

    $data = json_decode($response, true);
    $ok = $status >= 200 && $status < 300;
    $error = null;
    if (!$ok) {
        $error = is_array($data) && isset($data['message']) ? $data['message'] : ('HTTP ' . $status);
    }
    return ['ok' => $ok, 'status' => $status, 'data' => $data, 'error' => $error];
}

/** Test the connection by listing clients. */
function keyhelpTestConnection() {
    return keyhelpRequest('GET', 'clients');
}

/** List all KeyHelp clients. */
function keyhelpListClients() {
    $res = keyhelpRequest('GET', 'clients');
    return $res['ok'] && is_array($res['data']) ? $res['data'] : [];
}

/** Get a single KeyHelp client by id. */
function keyhelpGetClient($id) {
    return keyhelpRequest('GET', 'clients/' . urlencode($id));
}

/**
 * Create a KeyHelp client / hosting account.
 * @param array $data ['username','password','email','name','hosting_plan_id']
 */
function keyhelpCreateClient($data) {
    $cfg = keyhelpConfig();
    $payload = [
        'username' => $data['username'],
        'password' => $data['password'],
        'email' => $data['email'],
        'contact_data' => [
            'full_name' => $data['name'] ?? $data['username']
        ]
    ];
    if (!empty($data['hosting_plan_id']) || !empty($cfg['default_package_id'])) {
        $payload['hosting_plan_id'] = $data['hosting_plan_id'] ?: $cfg['default_package_id'];
    }
    return keyhelpRequest('POST', 'clients', $payload);
}

/** Suspend a KeyHelp client. */
function keyhelpSuspendClient($id) {
    return keyhelpRequest('PUT', 'clients/' . urlencode($id), ['is_suspended' => true]);
}

/** Unsuspend a KeyHelp client. */
function keyhelpUnsuspendClient($id) {
    return keyhelpRequest('PUT', 'clients/' . urlencode($id), ['is_suspended' => false]);
}

/** Delete a KeyHelp client. */
function keyhelpDeleteClient($id) {
    return keyhelpRequest('DELETE', 'clients/' . urlencode($id));
}
