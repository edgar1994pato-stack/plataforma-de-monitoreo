<?php
/**
 * /vistas_pantallas/menu.php
 * ---------------------------------------
 * MENÚ PRINCIPAL del sistema
 * Se accede SOLO después de login correcto
 */

$BASE_URL = "/plataforma_de_monitoreo";

// Seguridad
require_once '../includes_partes_fijas/seguridad.php';
require_login();
force_password_change();

// Variables de diseño
$PAGE_TITLE = "📊 Menú Principal";
$PAGE_SUBTITLE = "";

// Acción superior
$PAGE_ACTION_HTML = '
  <a class="btn btn-outline-danger btn-sm shadow-sm" href="'.$BASE_URL.'/cruds/logout.php">
    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
  </a>
';

// Header
require_once '../includes_partes_fijas/diseno_arriba.php';

// Datos de sesión
$nombreCompleto = (string)($_SESSION['nombre_completo'] ?? '');
$idArea         = (int)($_SESSION['id_area'] ?? 0);
$idRol          = (int)($_SESSION['id_rol'] ?? 0);

$textoScope = can_see_all_areas()
    ? "Acceso: todas las áreas"
    : "Acceso: solo tu área (ID: {$idArea})";
?>

<!-- ================= CONTENIDO ================= -->

<div class="row g-4">

    <?php if (can_create()): ?>
    <!-- TARJETA: Nuevo Monitoreo -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-clipboard-check fs-1 text-primary"></i>
                <h5 class="mt-3 fw-bold">Nuevo Monitoreo</h5>
                <p class="text-muted small">
                    Registrar un nuevo monitoreo.
                </p>
                <a href="<?= $BASE_URL ?>/vistas_pantallas/formulario.php"
                   class="btn btn-primary btn-sm fw-bold">
                    Ingresar
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TARJETA: Listado de Monitoreos -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-list-check fs-1 text-success"></i>
                <h5 class="mt-3 fw-bold">Listado de Monitoreos</h5>
                <p class="text-muted small">
                    Consultar y revisar monitoreos registrados.
                </p>
                <a href="<?= $BASE_URL ?>/vistas_pantallas/listado_monitoreos.php"
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
                    Crear, listar preguntas .
                </p>
                <a href="<?= $BASE_URL ?>/vistas_pantallas/listado_preguntas.php"
                   class="btn btn-warning btn-sm fw-bold">
                    Administrar
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TARJETA: Perfil -->
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

                <a href="<?= $BASE_URL ?>/vistas_pantallas/cambiar_password.php"
                   class="btn btn-outline-secondary btn-sm fw-bold">
                    Cambiar contraseña
                </a>
            </div>
        </div>
    </div>

</div>

<!-- =============== FIN CONTENIDO =============== -->

<?php
require_once '../includes_partes_fijas/diseno_abajo.php';
?>
