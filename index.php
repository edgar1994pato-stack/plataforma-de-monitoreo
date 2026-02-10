<?php
declare(strict_types=1);

/*
 |=================================================
 | index.php – FRONT CONTROLLER FINAL (PRODUCCIÓN)
 |=================================================
*/

ini_set('session.save_path', sys_get_temp_dir());
session_start();

define('BASE_PATH', __DIR__);

// CONFIGURACIÓN (NO IMPRIME NADA)
require_once BASE_PATH . '/config_ajustes/app.php';

/* =================================================
 * POST SIEMPRE PRIMERO (FRONT CONTROLLER)
 * ================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // LOGIN
    if (($_POST['accion'] ?? '') === 'login') {
        require_once BASE_PATH . '/cruds/proceso_login.php';
        exit;
    }

    /*
     * 🔒 POST no reconocido
     * En Azure, devolver 400/exit provoca 404 visual.
     * Por eso se retorna al login de forma segura.
     */
    require_once BASE_PATH . '/vistas_pantallas/login.php';
    exit;
}

/* =================================================
 * FLUJO GET
 * ================================================= */
if (!empty($_SESSION['id_usuario'])) {
    header("Location: {$BASE_URL}/vistas_pantallas/menu.php");
    exit;
}

// GET sin sesión → login
require_once BASE_PATH . '/vistas_pantallas/login.php';
