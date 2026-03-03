<?php
/**
 * /vistas_pantallas/roles.php
 * Administración visual de roles y permisos
 */

require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   SEGURIDAD
========================= */
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';
require_login();
require_permission('ver_modulo_roles');
force_password_change();

/* =========================
   CONEXIÓN BD
========================= */
require_once BASE_PATH . '/config_ajustes/conectar_db.php';

/* =========================
   HEADER
========================= */
$PAGE_TITLE = "Gestión de Roles y Permisos";
$PAGE_SUBTITLE = "Administración de accesos del sistema";

$PAGE_ACTION_HTML = '
  <a class="btn btn-outline-primary btn-sm shadow-sm"
     href="'.BASE_URL.'/vistas_pantallas/menu.php">
    <i class="bi bi-house-door"></i> Volver al menú
  </a>
';

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

/* =========================
   OBTENER ROLES ACTIVOS
========================= */
$roles = [];
try {
    $stmt = $conexion->query("
        SELECT id_rol, nombre_rol
        FROM ROLES
        WHERE fecha_fin IS NULL
        ORDER BY nombre_rol
    ");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $roles = [];
}

/* =========================
   ROL SELECCIONADO
========================= */
$idRolSeleccionado = isset($_GET['rol'])
    ? (int)$_GET['rol']
    : ($roles[0]['id_rol'] ?? 0);

/* =========================
   OBTENER PERMISOS ASIGNADOS
========================= */
$permisosAsignados = [];

if ($idRolSeleccionado > 0) {
    try {
        $stmt = $conexion->prepare("
            SELECT id_permiso
            FROM ROL_PERMISO
            WHERE id_rol = ?
        ");
        $stmt->execute([$idRolSeleccionado]);
        $permisosAsignados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $permisosAsignados = [];
    }
}

/* =========================
   OBTENER PERMISOS ACTIVOS
========================= */
$permisos = [];
try {
    $stmt = $conexion->query("
        SELECT id_permiso, codigo, descripcion, modulo
        FROM PERMISOS
        WHERE activo = 1
        ORDER BY modulo, codigo
    ");
    $permisosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($permisosRaw as $p) {
        $permisos[$p['modulo']][] = $p;
    }
} catch (Throwable $e) {
    $permisos = [];
}
?>

<div class="container mt-4">

<!-- =========================
   SELECTOR DE ROL
========================= -->
<div class="card card-soft shadow-sm mb-4 border-0">
    <div class="card-header card-header-dark py-2 small fw-bold">
        <i class="bi bi-person-badge me-2"></i>
        Seleccionar Rol
    </div>
    <div class="card-body">

        <form method="GET">
            <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">
                    Rol del sistema
                </label>

                <select class="form-select form-select-sm"
                        name="rol"
                        onchange="this.form.submit()">

                    <?php if (count($roles) === 0): ?>
                        <option>No hay roles registrados</option>
                    <?php else: ?>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id_rol'] ?>"
                                <?= $idRolSeleccionado == $r['id_rol'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['nombre_rol']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </select>
            </div>
        </form>

    </div>
</div>

<!-- =========================
   FORMULARIO DE PERMISOS
========================= -->
<form method="POST" action="<?= BASE_URL ?>/cruds/proceso_guardar_roles.php">
<input type="hidden" name="id_rol" value="<?= (int)$idRolSeleccionado ?>">

<?php if (count($permisos) === 0): ?>
    <div class="alert alert-warning shadow-sm border-0">
        No hay permisos activos configurados.
    </div>
<?php else: ?>

    <?php foreach ($permisos as $modulo => $listaPermisos): ?>
        <div class="card card-soft shadow-sm mb-3 border-0">
            <div class="card-header card-header-dark py-2 small fw-bold">
                <i class="bi bi-shield-lock me-2"></i>
                <?= htmlspecialchars($modulo) ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($listaPermisos as $perm): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="permisos[]"
                                       value="<?= (int)$perm['id_permiso'] ?>"
                                       <?= in_array($perm['id_permiso'], $permisosAsignados) ? 'checked' : '' ?>>
                                <label class="form-check-label small">
                                    <?= htmlspecialchars($perm['codigo']) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="mt-4 text-end">
        <button type="submit"
                class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="bi bi-save me-1"></i>
            Guardar cambios
        </button>
    </div>

<?php endif; ?>

</form>

</div>

<!-- =========================
   TOAST PROFESIONAL
========================= -->
<?php if (!empty($_SESSION['flash_ok'])): ?>

<style>
.toast-profesional {
  background: #ffffff;
  border-left: 4px solid #198754;
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.08);
  min-width: 320px;
}
.toast-profesional .toast-title {
  font-weight: 600;
  font-size: 13px;
  color: #198754;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.toast-profesional .toast-body {
  font-size: 14px;
  color: #2c2c2c;
}
</style>

<div class="toast-container position-fixed top-0 end-0 p-4" style="z-index: 9999;">
  <div id="toastSuccess" class="toast toast-profesional border-0">
    <div class="toast-body">
      <div class="toast-title mb-1">
        <i class="bi bi-check-circle me-1"></i>
        Actualización Exitosa
      </div>
      <div>
        <?= htmlspecialchars($_SESSION['flash_ok']) ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const toastEl = document.getElementById('toastSuccess');
  const toast = new bootstrap.Toast(toastEl, {
    delay: 4000
  });
  toast.show();
});
</script>

<?php unset($_SESSION['flash_ok']); ?>
<?php endif; ?>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';