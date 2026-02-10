<?php
/**
 * ARCHIVO: /cruds/logout.php
 * =========================================================
 * CIERRE DE SESIÓN – PRODUCCIÓN AZURE
 */

ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

/* =========================================================
 * 1) Vaciar variables de sesión
 * ========================================================= */
$_SESSION = [];

/* =========================================================
 * 2) Eliminar cookie de sesión
 * ========================================================= */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        true // httponly
    );
}

/* =========================================================
 * 3) Destruir sesión
 * ========================================================= */
session_destroy();

/* =========================================================
 * 4) Redirigir al login (Front Controller)
 * ========================================================= */
header('Location: ' . BASE_URL . '/');
exit;
