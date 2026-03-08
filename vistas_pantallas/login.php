<?php
/**
 * LOGIN PRINCIPAL DEL SISTEMA
 * Autenticación Microsoft Entra ID
 */

require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Si ya hay sesión → menú */
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

<!-- PANEL IZQUIERDO -->

<div class="login-left">

<span>SISTEMA DE MONITOREO</span>

<h1 class="mt-3">
ALFA<br>MONITOR
</h1>

<p>
Plataforma centralizada para la gestión, evaluación
y monitoreo de calidad operativa.
</p>

<div class="features">

<div class="feature">
<i class="bi bi-bar-chart"></i>
<div class="small mt-2">Indicadores</div>
</div>

<div class="feature">
<i class="bi bi-lightning"></i>
<div class="small mt-2">Gestión rápida</div>
</div>

<div class="feature">
<i class="bi bi-shield-check"></i>
<div class="small mt-2">Acceso seguro</div>
</div>

</div>

</div>


<!-- PANEL DERECHO -->

<div class="login-right">

<div class="login-card card-soft p-4 text-center">

<i class="bi bi-person-circle fs-1 mb-3 text-secondary"></i>

<h5 class="fw-bold">Iniciar sesión</h5>

<p class="small text-muted mb-4">
Utiliza tu cuenta corporativa de Microsoft
para acceder a la plataforma.
</p>

<a href="<?= BASE_URL ?>/vistas_pantallas/login_microsoft.php"
class="btn btn-microsoft w-100 py-2">

<i class="bi bi-microsoft me-2"></i>
Iniciar sesión con Microsoft

</a>

<p class="small text-muted mt-3">
Autenticación segura mediante Microsoft Entra ID
</p>

</div>

</div>

</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>