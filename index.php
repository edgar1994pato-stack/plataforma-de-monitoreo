<?php
declare(strict_types=1);

/*
 |=================================================
 | index.php – FRONT CONTROLLER FINAL (PRODUCCIÓN)
 | Azure App Service · Linux · nginx · PHP 8.2
 |=================================================
*/

ini_set('session.save_path', sys_get_temp_dir());
session_start();

define('BASE_PATH', __DIR__);

// Configuración global (no imprime nada)
require_once BASE_PATH . '/config_ajustes/app.php';

/* =================================================
 * POST SIEMPRE PRIMERO
 * ================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // LOGIN
    if (($_POST['accion'] ?? '') === 'login') {
        require_once BASE_PATH . '/cruds/proceso_login.php';
        exit;
    }

    // POST no reconocido → login seguro
    require_once BASE_PATH . '/vistas_pantallas/login.php';
    exit;
}

/* =================================================
 * FLUJO GET
 * ================================================= */
if (!empty($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . '/vistas_pantallas/menu.php');
    exit;
}

// GET sin sesión → login
require_once BASE_PATH . '/vistas_pantallas/login.php';
