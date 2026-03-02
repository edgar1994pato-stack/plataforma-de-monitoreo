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
$PAGE_TITLE = "Administración de Roles";
$PAGE_SUBTITLE = "Gestión de permisos del sistema";

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

/* =========================
   MENSAJES FLASH
========================= */
if (!empty($_SESSION['flash_ok'])) {
    echo '<div class="alert alert-success shadow-sm">'
        . htmlspecialchars($_SESSION['flash_ok']) .
        '</div>';
    unset($_SESSION['flash_ok']);
}

if (!empty($_SESSION['flash_err'])) {
    echo '<div class="alert alert-danger shadow-sm">'
        . htmlspecialchars($_SESSION['flash_err']) .
        '</div>';
    unset($_SESSION['flash_err']);
}

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
   PERMISOS ASIGNADOS
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
   PERMISOS ACTIVOS
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
<div class="card shadow-sm mb-4">
    <div class="card-body">

        <form method="GET">
            <label class="form-label fw-bold">Seleccionar Rol</label>

            <select class="form-select"
                    name="rol"
                    onchange="this.form.submit()">

                <?php if (empty($roles)): ?>
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
        </form>

        <small class="text-muted">
            Selecciona un rol para administrar sus permisos.
        </small>

    </div>
</div>

<!-- =========================
   FORMULARIO PERMISOS
========================= -->
<form method="POST" action="<?= BASE_URL ?>/cruds/proceso_guardar_roles.php">
<input type="hidden" name="id_rol" value="<?= (int)$idRolSeleccionado ?>">

<?php if (empty($permisos)): ?>

    <div class="alert alert-warning">
        No hay permisos activos configurados.
    </div>

<?php else: ?>

    <?php foreach ($permisos as $modulo => $listaPermisos): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold bg-light">
                Módulo: <?= htmlspecialchars($modulo) ?>
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
        <button type="submit" class="btn btn-dark">
            Guardar cambios
        </button>
    </div>

<?php endif; ?>

</form>

</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';