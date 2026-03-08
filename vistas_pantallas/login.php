<?php
/**
 * LOGIN PRINCIPAL DEL SISTEMA
 * Autenticación con Microsoft Entra ID

 */

require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Si ya hay sesión → ir al menú */
if (!empty($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . '/vistas_pantallas/menu.php');
    exit;
}

/* Variables de layout */
$PAGE_TITLE = "";
$PAGE_SUBTITLE = "";
$PAGE_ACTION_HTML = "";

/* Header del sistema */
require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="login-wrapper">

    <div class="login-card">

        <!-- Icono usuario -->
        <div class="login-icon">
            <i class="bi bi-person"></i>
        </div>

        <!-- Título -->
        <div class="login-title">
            Iniciar sesión
        </div>

        <!-- Texto -->
        <div class="login-text">
            Utiliza tu cuenta corporativa de Microsoft
            para acceder a la plataforma.
        </div>

        <!-- Botón Microsoft -->
        <a href="<?= BASE_URL ?>/vistas_pantallas/login_microsoft.php"
           class="btn btn-microsoft w-100">

            <div class="ms-logo">
                <span class="ms-red"></span>
                <span class="ms-green"></span>
                <span class="ms-blue"></span>
                <span class="ms-yellow"></span>
            </div>

            Iniciar sesión con Microsoft

        </a>

        <!-- Seguridad -->
        <p class="login-security">
           Microsoft
        </p>

    </div>

</div>

<?php
/* Footer del sistema */
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>