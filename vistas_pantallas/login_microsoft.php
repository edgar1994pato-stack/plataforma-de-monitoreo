<?php
require_once __DIR__ . '/../config_ajustes/app.php';

/* CONFIGURACIÓN SEGURA DE COOKIE PARA OAUTH */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
}

/* Variables de entorno */
$clientId = getenv('MS_CLIENT_ID');

/* MODO PRUEBA: permite usuarios de cualquier tenant empresarial */
$tenantId = "organizations";

if (!$clientId) {
    http_response_code(500);
    exit('❌ Falta MS_CLIENT_ID en variables de entorno.');
}

/* URL de retorno */
$redirectUri = BASE_URL . '/vistas_pantallas/callback_microsoft.php';

/* Protección CSRF */
$state = bin2hex(random_bytes(16));
$_SESSION['ms_state'] = $state;
$_SESSION['ms_state_time'] = time();

/* URL de autorización */
$authUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?" . http_build_query([
    'client_id'     => $clientId,
    'response_type' => 'code',
    'redirect_uri'  => $redirectUri,
    'response_mode' => 'query',
    'scope'         => 'openid profile email',
    'state'         => $state
]);

header("Location: {$authUrl}");
exit;