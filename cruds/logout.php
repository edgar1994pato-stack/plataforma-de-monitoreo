<?php
/**
 * ARCHIVO: /cruds/logout.php
 * =========================================================
 * OBJETIVO:
 *  - Cerrar sesión de forma segura (destruir cookie + variables)
 *  - Redirigir al login
 *
 * SE UTILIZA EN:
 *  - Botón "Cerrar sesión" del menú:
 *      /vistas_pantallas/menu.php
 */

ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

$BASE_URL = "/plataforma_de_monitoreo";

/**
 * 1) Vaciar variables de sesión
 */
$_SESSION = [];

/**
 * 2) Eliminar cookie de sesión (muy importante)
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        true // httponly
    );
}

/**
 * 3) Destruir sesión
 */
session_destroy();

/**
 * 4) Redirigir al login
 */
header("Location: {$BASE_URL}/vistas_pantallas/login.php");
exit;
