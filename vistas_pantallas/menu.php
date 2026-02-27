<?php
/**
 * /vistas_pantallas/menu.php
 * ---------------------------------------
 * MENÚ PRINCIPAL del sistema
 * Acceso SOLO después de login correcto
 * Compatible Azure App Service (Linux + nginx)
 */

/* =========================================================
 * 0) CARGAR CONTEXTO GLOBAL (OBLIGATORIO)
 * =========================================================
 * Esto asegura que existan:
 * - BASE_PATH
 * - BASE_URL
 * y evita 404 cuando se accede directo al archivo
 */
require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
 * 1) SEGURIDAD
 * ========================================================= */
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';
require_login();
force_password_change();

/* =========================================================
 * 2) VARIABLES DE DISEÑO
 * ========================================================= */
$PAGE_TITLE = "";
$PAGE_SUBTITLE = "";

/* Acción superior */
$PAGE_ACTION_HTML = '
<a class="btn btn-outline-danger btn-sm shadow-sm" href="'.BASE_URL.'/cruds/logout.php">
    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
</a>
';

/* =========================================================
 * 3) HEADER
 * ========================================================= */
require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

if (!empty($_SESSION['flash_err'])) {
    echo '<div class="alert alert-danger shadow-sm">'.htmlspecialchars($_SESSION['flash_err']).'</div>';
    unset($_SESSION['flash_err']);
}

/* ================= FLASH MONITOREO ================= */
$flashMonitoreo = $_SESSION['flash_monitoreo_id'] ?? null;

if (!empty($flashMonitoreo)) {
    $numeroMonitoreo = (int)$flashMonitoreo;
    unset($_SESSION['flash_monitoreo_id']);
?>
<style>
#overlayMonitoreo {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(0,0,0,0.85) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 999999 !important;
}
#overlayMonitoreo .card-monitoreo {
    background: #E0E621;
    padding: 70px 60px;
    border-radius: 30px;
    text-align: center;
    box-shadow: 0 30px 70px rgba(0,0,0,.4);
    animation: zoomIn .35s ease;
}
#overlayMonitoreo .circle-check {
    width: 130px;
    height: 130px;
    border: 8px solid #000;
    border-radius: 50%;
    margin: 0 auto 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}
#overlayMonitoreo .checkmark {
    width: 50px;
    height: 90px;
    border-right: 8px solid #2E9BFF;
    border-bottom: 8px solid #2E9BFF;
    transform: rotate(45deg) scale(0);
    animation: drawCheck .4s ease forwards;
}
#overlayMonitoreo .titulo-monitoreo {
    font-weight: 700;
    font-size: 18px;
    color: #000;
}
#overlayMonitoreo .numero-monitoreo {
    font-size: 60px;
    font-weight: 900;
    color: #000;
    margin-top: 10px;
}
@keyframes drawCheck {
    to { transform: rotate(45deg) scale(1); }
}
@keyframes zoomIn {
    from { transform: scale(.6); opacity:0; }
    to { transform: scale(1); opacity:1; }
}
</style>

<div id="overlayMonitoreo">
    <div class="card-monitoreo">
        <div class="circle-check">
            <div class="checkmark"></div>
        </div>
        <div class="titulo-monitoreo">MONITOREO REGISTRADO</div>
        <div class="numero-monitoreo">#<?= $numeroMonitoreo ?></div>
    </div>
</div>

<script>
setTimeout(function(){
    const el = document.getElementById('overlayMonitoreo');
    if(el){
        el.style.transition = "opacity .4s";
        el.style.opacity = "0";
        setTimeout(()=>el.remove(),400);
    }
},4000);
</script>
<?php } ?>

<?php
/* =========================================================
 * 4) DATOS DE SESIÓN
 * ========================================================= */
$nombreCompleto = (string)($_SESSION['nombre_completo'] ?? '');
$idArea = (int)($_SESSION['id_area'] ?? 0);
$textoScope = can_see_all_areas()
    ? "Acceso: todas las áreas"
    : "Acceso: solo tu área (ID: {$idArea})";
?>

<!-- ================= CONTENIDO ================= -->
<div class="row g-4">

<?php if (has_permission('ver_modulo_monitoreos')): ?>
<!-- TARJETA: NUEVO MONITOREO -->
<div class="col-md-4">
    <div class="card shadow-sm h-100">
        <div class="card-body text-center">
            <i class="bi bi-clipboard-check fs-1 text-primary"></i>
            <h5 class="mt-3 fw-bold">Nuevo Monitoreo</h5>
            <p class="text-muted small">
                Registrar un nuevo monitoreo.
            </p>
            <a href="<?= BASE_URL ?>/vistas_pantallas/formulario.php"
               class="btn btn-primary btn-sm fw-bold">
                Ingresar
            </a>
        </div>
    </div>
</div>

<!-- TARJETA: LISTADO DE MONITOREOS -->
<div class="col-md-4">
    <div class="card shadow-sm h-100">
        <div class="card-body text-center">
            <i class="bi bi-list-check fs-1 text-success"></i>
            <h5 class="mt-3 fw-bold">Listado de Monitoreos</h5>
            <p class="text-muted small">
                Consultar y revisar monitoreos registrados.
            </p>
            <a href="<?= BASE_URL ?>/vistas_pantallas/listado_monitoreos.php"
               class="btn btn-success btn-sm fw-bold">
                Ver listado
            </a>
        </div>
    </div>
</div>

<?php endif; ?>



<?php if (has_permission('ver_modulo_preguntas')): ?>
<!-- TARJETA: MÓDULO DE PREGUNTAS -->
<div class="col-md-4">
    <div class="card shadow-sm h-100 border-warning">
        <div class="card-body text-center">
            <i class="bi bi-question-circle fs-1 text-warning"></i>
            <h5 class="mt-3 fw-bold">Módulo de Preguntas</h5>
            <p class="text-muted small">
                Crear y administrar preguntas.
            </p>
            <a href="<?= BASE_URL ?>/vistas_pantallas/listado_preguntas.php"
               class="btn btn-warning btn-sm fw-bold">
                Administrar
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (has_permission('ver_modulo_agentes')): ?>
<!-- TARJETA: AGENTES -->
<div class="col-md-4">
    <div class="card card-soft h-100">
        <div class="card-body text-center">
            <i class="bi bi-people-fill fs-1" style="color: var(--primary);"></i>
            <h5 class="mt-3 fw-bold">Gestión de Agentes</h5>
            <p class="text-muted small">
                Crear, activar o desactivar agentes del sistema.
            </p>
            <a href="<?= BASE_URL ?>/vistas_pantallas/listado_agentes.php"
               class="btn btn-primary btn-sm fw-bold">
                Administrar
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- =============== FIN CONTENIDO =============== -->

<?php
/* =========================================================
 * 5) FOOTER
 * ========================================================= */
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>