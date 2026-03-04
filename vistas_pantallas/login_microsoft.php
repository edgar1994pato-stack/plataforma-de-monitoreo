<?php
require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Variables Entra ID (App Service) */
$clientId = getenv('MS_CLIENT_ID');
$tenantId = "common";

if (!$clientId || !$tenantId) {
    http_response_code(500);
    exit('❌ Faltan variables MS_CLIENT_ID o MS_TENANT_ID en App Service.');
}

/* Callback exacto */
$redirectUri = BASE_URL . '/vistas_pantallas/callback_microsoft.php';

/* Anti-CSRF */
$_SESSION['ms_state'] = bin2hex(random_bytes(16));

$authUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?" . http_build_query([
    'client_id'     => $clientId,
    'response_type' => 'code',
    'redirect_uri'  => $redirectUri,
    'response_mode' => 'query',
    'scope'         => 'openid profile email',
    'state'         => $_SESSION['ms_state'],
]);

header("Location: {$authUrl}");
exit;