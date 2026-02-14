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
$PAGE_TITLE    = "📊 Menú Principal";
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


/* ================= FLASH MONITOREO ================= */
$flashMonitoreo = $_SESSION['flash_monitoreo_id'] ?? null;

if ($flashMonitoreo):
    unset($_SESSION['flash_monitoreo_id']);
?>
    <div id="overlayMonitoreo" class="overlay-monitoreo">
        <div class="card-monitoreo">
            <div class="circle-check">
                <div class="checkmark"></div>
            </div>

            <div class="titulo-monitoreo">
                MONITOREO REGISTRADO
            </div>

            <div class="numero-monitoreo">
                #<?= (int)$flashMonitoreo ?>
            </div>
        </div>
    </div>

    <script>
    setTimeout(() => {
        const overlay = document.getElementById('overlayMonitoreo');
        if (overlay) {
            overlay.classList.add('fade-out');
            setTimeout(() => overlay.remove(), 400);
        }
    }, 3000);
    </script>
<?php
endif;
/* =================================================== */


/* =========================================================
 * 4) DATOS DE SESIÓN
 * ========================================================= */
$nombreCompleto = (string)($_SESSION['nombre_completo'] ?? '');
$idArea         = (int)($_SESSION['id_area'] ?? 0);

$textoScope = can_see_all_areas()
    ? "Acceso: todas las áreas"
    : "Acceso: solo tu área (ID: {$idArea})";
?>

<!-- ================= CONTENIDO ================= -->

<div class="row g-4">

    <?php if (can_create()): ?>
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
    <?php endif; ?>

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

    <?php if (can_create()): ?>
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

    <!-- TARJETA: PERFIL -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-person-circle fs-1 text-secondary"></i>
                <h5 class="mt-3 fw-bold">Mi perfil</h5>
                <p class="text-muted small mb-2">
                    Usuario: <?= htmlspecialchars($nombreCompleto) ?><br>
                    <?= htmlspecialchars($textoScope) ?><br>
                    <?php if (is_readonly()): ?>
                        <span class="badge bg-secondary mt-1">Modo lectura</span>
                    <?php endif; ?>
                </p>

                <a href="<?= BASE_URL ?>/vistas_pantallas/cambiar_password.php"
                   class="btn btn-outline-secondary btn-sm fw-bold">
                    Cambiar contraseña
                </a>
            </div>
        </div>
    </div>

</div>

<!-- =============== FIN CONTENIDO =============== -->

<?php
/* =========================================================
 * 5) FOOTER
 * ========================================================= */
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
