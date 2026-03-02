<?php
require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';
require_login();
require_permission('ver_modulo_roles');
force_password_change();

require_once BASE_PATH . '/config_ajustes/conectar_db.php';

$PAGE_TITLE = "";
$PAGE_SUBTITLE = "";

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
   DATOS
========================= */

$roles = $conexion->query("
    SELECT id_rol, nombre_rol
    FROM ROLES
    WHERE fecha_fin IS NULL
    ORDER BY nombre_rol
")->fetchAll(PDO::FETCH_ASSOC);

$idRolSeleccionado = isset($_GET['rol'])
    ? (int)$_GET['rol']
    : ($roles[0]['id_rol'] ?? 0);

$permisosAsignados = [];

if ($idRolSeleccionado > 0) {
    $stmt = $conexion->prepare("
        SELECT id_permiso
        FROM ROL_PERMISO
        WHERE id_rol = ?
    ");
    $stmt->execute([$idRolSeleccionado]);
    $permisosAsignados = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$permisosRaw = $conexion->query("
    SELECT id_permiso, codigo, modulo
    FROM PERMISOS
    WHERE activo = 1
    ORDER BY modulo, codigo
")->fetchAll(PDO::FETCH_ASSOC);

$permisos = [];
foreach ($permisosRaw as $p) {
    $permisos[$p['modulo']][] = $p;
}
?>

<div class="container mt-4">

<!-- BOTÓN MENÚ -->
<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="<?= BASE_URL ?>/vistas_pantallas/menu.php"
       class="btn btn-soft btn-sm shadow-sm">
       <i class="bi bi-house-door"></i> Menú principal
    </a>
</div>

<!-- SELECTOR -->
<div class="card card-soft mb-4">
    <div class="card-body">
        <form method="GET">
            <label class="form-label fw-bold">Seleccionar Rol</label>
            <select class="form-select"
                    name="rol"
                    onchange="this.form.submit()">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id_rol'] ?>"
                        <?= $idRolSeleccionado == $r['id_rol'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['nombre_rol']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- FORMULARIO -->
<form method="POST" action="<?= BASE_URL ?>/cruds/proceso_guardar_roles.php">
<input type="hidden" name="id_rol" value="<?= $idRolSeleccionado ?>">

<?php foreach ($permisos as $modulo => $lista): ?>
    <div class="card card-soft mb-3">
        <div class="card-header card-header-dark py-2 small">
            <?= htmlspecialchars($modulo) ?>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($lista as $perm): ?>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="permisos[]"
                                   value="<?= $perm['id_permiso'] ?>"
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
    <button type="submit" class="btn btn-primary shadow-sm">
        Guardar cambios
    </button>
</div>

</form>

</div>

<?php require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php'; ?>