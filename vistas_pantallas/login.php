<?php
require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . '/vistas_pantallas/menu.php');
    exit;
}

$PAGE_TITLE = "";
$PAGE_SUBTITLE = "";
$PAGE_ACTION_HTML = "";

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="login-wrapper">

<div class="login-card">

<div class="login-icon">
<i class="bi bi-person"></i>
</div>

<div class="login-title">
Iniciar sesión
</div>

<div class="login-text">
Usa tu cuenta corporativa de Microsoft
para acceder a la plataforma.
</div>

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

<div class="login-text mt-4">
Autenticación segura mediante Microsoft Entra ID
</div>

</div>

</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>