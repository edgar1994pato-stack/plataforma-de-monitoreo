<?php
require_once __DIR__ . '/../config_ajustes/app.php';

/* 🔐 CONFIGURACIÓN SEGURA DE COOKIE PARA OAUTH */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,      // obligatorio en HTTPS
        'httponly' => true,
        'samesite' => 'None'     // CLAVE para OAuth
    ]);
    session_start();
}

/* Variables Entra ID */
$clientId = getenv('MS_CLIENT_ID');
$tenantId = getenv('MS_TENANT_ID'); // usar tenant fijo

if (!$clientId || !$tenantId) {
    http_response_code(500);
    exit('❌ Faltan variables MS_CLIENT_ID o MS_TENANT_ID.');
}

/* Callback exacto */
$redirectUri = BASE_URL . '/vistas_pantallas/callback_microsoft.php';

/* Anti-CSRF */
$state = bin2hex(random_bytes(16));
$_SESSION['ms_state'] = $state;
$_SESSION['ms_state_time'] = time(); // opcional (expiración)

/* Construcción URL autorización */
$authUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?" . http_build_query([
    'client_id'     => $clientId,
    'response_type' => 'code',
    'redirect_uri'  => $redirectUri,
    'response_mode' => 'query',
    'scope'         => 'openid profile email',
    'state'         => $state,
]);

header("Location: {$authUrl}");
exit;