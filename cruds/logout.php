<?php
/**
 * ARCHIVO: /cruds/logout.php
 * =========================================================
 * CIERRE DE SESIÓN – PRODUCCIÓN AZURE
 * Compatible con:
 * - Login tradicional
 * - Login Microsoft Entra ID (OAuth)
 */

// 🔴 CONTEXTO GLOBAL (OBLIGATORIO)
require_once __DIR__ . '/../config_ajustes/app.php';

ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

/* =========================================================
   1) Vaciar variables de sesión
   ========================================================= */
$_SESSION = [];

/* =========================================================
   2) Eliminar cookie de sesión
   ========================================================= */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        true
    );
}

/* =========================================================
   3) Destruir sesión local
   ========================================================= */
session_destroy();

/* =========================================================
   4) Cerrar sesión también en Microsoft (SSO)
   =========================================================
   Esto evita que Microsoft reutilice la sesión anterior
   y permite seleccionar otra cuenta al volver a iniciar sesión.
*/

$logoutMicrosoft = "https://login.microsoftonline.com/common/oauth2/v2.0/logout"
    . "?post_logout_redirect_uri=" . urlencode(BASE_URL);

/* =========================================================
   5) Redirigir al logout de Microsoft
   ========================================================= */
header("Location: " . $logoutMicrosoft);
exit;