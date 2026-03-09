<?php
/**
 * /vistas_pantallas/roles.php
 * Administración visual de roles, permisos y áreas por usuario
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

/* =========================================================
   BLOQUE 1
   OBTENER ROLES
========================================================= */

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

/* =========================================================
   BLOQUE 2
   ROL SELECCIONADO
========================================================= */

$idRolSeleccionado = 0;

if (!empty($roles)) {

$idRolSeleccionado = isset($_GET['rol'])
? (int)$_GET['rol']
: (int)$roles[0]['id_rol'];

}

/* =========================================================
   BLOQUE 3
   PERMISOS DEL ROL
========================================================= */

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

/* =========================================================
   BLOQUE 4
   TODOS LOS PERMISOS
========================================================= */

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

/* =========================================================
   BLOQUE 5
   OBTENER USUARIOS
========================================================= */

$usuarios = [];

try {

$stmt = $conexion->query("
SELECT id_usuario,nombre_completo
FROM USUARIOS
WHERE activo = 1
ORDER BY nombre_completo
");

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

$usuarios = [];

}

/* =========================================================
   BLOQUE 6
   USUARIO SELECCIONADO
========================================================= */

$idUsuarioSeleccionado = 0;

if (!empty($usuarios)) {

$idUsuarioSeleccionado = isset($_GET['usuario'])
? (int)$_GET['usuario']
: (int)$usuarios[0]['id_usuario'];

}

/* =========================================================
   BLOQUE 7
   OBTENER ÁREAS ACTIVAS
========================================================= */

$areas = [];

try {

$stmt = $conexion->query("
SELECT id_area,nombre_area
FROM AREAS
WHERE estado = 1
AND fecha_fin IS NULL
ORDER BY nombre_area
");

$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

$areas = [];

}

/* =========================================================
   BLOQUE 8
   ÁREAS DEL USUARIO
========================================================= */

$areasAsignadas = [];

if ($idUsuarioSeleccionado > 0) {

try {

$stmt = $conexion->prepare("
SELECT id_area
FROM USUARIO_AREA
WHERE id_usuario = ?
");

$stmt->execute([$idUsuarioSeleccionado]);

$areasAsignadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Throwable $e) {

$areasAsignadas = [];

}

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

<?php foreach ($roles as $r): ?>

<option value="<?= (int)$r['id_rol'] ?>"
<?= $idRolSeleccionado == $r['id_rol'] ? 'selected' : '' ?>>

<?= htmlspecialchars($r['nombre_rol']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</form>

</div>

</div>


<!-- =========================
     SELECTOR DE USUARIO
========================= -->

<div class="card card-soft shadow-sm mb-4 border-0">

<div class="card-header card-header-dark py-2 small fw-bold">
<i class="bi bi-person me-2"></i>
Seleccionar Usuario
</div>

<div class="card-body">

<form method="GET">

<div class="col-md-6">

<select class="form-select form-select-sm"
name="usuario"
onchange="this.form.submit()">

<?php foreach ($usuarios as $u): ?>

<option value="<?= (int)$u['id_usuario'] ?>"
<?= $idUsuarioSeleccionado == $u['id_usuario'] ? 'selected' : '' ?>>

<?= htmlspecialchars($u['nombre_completo']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</form>

</div>

</div>


<!-- =========================
     ÁREAS DEL USUARIO
========================= -->

<form method="POST"
action="<?= BASE_URL ?>/cruds/proceso_guardar_areas_usuario.php">

<input type="hidden" name="id_usuario"
value="<?= (int)$idUsuarioSeleccionado ?>">

<div class="card card-soft shadow-sm mb-4 border-0">

<div class="card-header card-header-dark py-2 small fw-bold">
<i class="bi bi-diagram-3 me-2"></i>
Áreas permitidas
</div>

<div class="card-body">

<div class="row">

<?php foreach ($areas as $a): ?>

<div class="col-md-4 mb-2">

<div class="form-check">

<input class="form-check-input"
type="checkbox"
name="areas[]"
value="<?= (int)$a['id_area'] ?>"
<?= in_array($a['id_area'],$areasAsignadas) ? 'checked' : '' ?>>

<label class="form-check-label small">

<?= htmlspecialchars($a['nombre_area']) ?>

</label>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</div>

<div class="text-end">

<button type="submit"
class="btn btn-primary fw-bold px-4 shadow-sm">

<i class="bi bi-save me-1"></i>
Guardar Áreas

</button>

</div>

</form>


</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>