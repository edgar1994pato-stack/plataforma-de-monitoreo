<?php
/**
 * /vistas_pantallas/roles.php
 * Administración visual de roles, permisos y áreas por usuario
 */

require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
require_permission('ver_modulo_roles');
force_password_change();

require_once BASE_PATH . '/config_ajustes/conectar_db.php';

$PAGE_TITLE = "Gestión de Roles y Permisos";
$PAGE_SUBTITLE = "Administración de accesos del sistema";

$PAGE_ACTION_HTML = '
<a class="btn btn-outline-primary btn-sm shadow-sm"
   href="'.BASE_URL.'/vistas_pantallas/menu.php">
<i class="bi bi-house-door"></i> Volver al menú
</a>
';

$PAGE_ACTION_HTML .= '
<a class="btn btn-success btn-sm shadow-sm ms-2"
   href="'.BASE_URL.'/vistas_pantallas/usuarios_formulario.php">
<i class="bi bi-plus-circle"></i> Agregar usuario
</a>
';

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';


/* =========================================================
   BLOQUE 1 - ROLES
========================================================= */

$stmt = $conexion->query("
SELECT id_rol,nombre_rol
FROM ROLES
WHERE fecha_fin IS NULL
ORDER BY nombre_rol
");

$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   BLOQUE 2 - ROL SELECCIONADO
========================================================= */

$idRolSeleccionado = isset($_GET['rol'])
? (int)$_GET['rol']
: ($roles[0]['id_rol'] ?? 0);


/* =========================================================
   BLOQUE 3 - PERMISOS DEL ROL
========================================================= */

$permisosAsignados = [];

$stmt = $conexion->prepare("
SELECT id_permiso
FROM ROL_PERMISO
WHERE id_rol = ?
");

$stmt->execute([$idRolSeleccionado]);

$permisosAsignados = $stmt->fetchAll(PDO::FETCH_COLUMN);


/* =========================================================
   BLOQUE 4 - LISTA DE PERMISOS
========================================================= */

$permisos = [];

$stmt = $conexion->query("
SELECT id_permiso,codigo,descripcion,modulo
FROM PERMISOS
WHERE activo = 1
ORDER BY modulo,codigo
");

$permisosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($permisosRaw as $p) {
    $permisos[$p['modulo']][] = $p;
}


/* =========================================================
   BLOQUE 5 - USUARIOS DEL ROL (INCLUYE ESTADO ACTIVO)
========================================================= */

$stmt = $conexion->prepare("
SELECT id_usuario, nombre_completo, activo
FROM USUARIOS
WHERE id_rol = ?
ORDER BY nombre_completo
");

$stmt->execute([$idRolSeleccionado]);

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   BLOQUE 6 - USUARIO SELECCIONADO
========================================================= */

$idUsuarioSeleccionado = isset($_GET['usuario'])
? (int)$_GET['usuario']
: ($usuarios[0]['id_usuario'] ?? 0);






/* =========================================================
   BLOQUE 7 - AREAS
========================================================= */

$stmt = $conexion->query("
SELECT id_area,nombre_area
FROM AREAS
WHERE estado = 1
ORDER BY nombre_area
");

$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   BLOQUE 8 - AREAS DEL USUARIO
========================================================= */

$areasAsignadas = [];

if ($idUsuarioSeleccionado > 0) {

$stmt = $conexion->prepare("
SELECT id_area
FROM USUARIO_AREA
WHERE id_usuario = ?
");

$stmt->execute([$idUsuarioSeleccionado]);

$areasAsignadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

}

?>


<div class="container mt-4">

<!-- =========================
     SELECCIONAR ROL
========================= -->

<div class="card mb-4">

<div class="card-header fw-bold">
Seleccionar Rol
</div>

<div class="card-body">

<form method="GET">

<select name="rol"
class="form-select"
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



<!-- =========================
     PERMISOS DEL ROL
========================= -->

<form method="POST" action="<?= BASE_URL ?>/cruds/proceso_guardar_roles.php">

<input type="hidden" name="id_rol" value="<?= $idRolSeleccionado ?>">

<div class="card mb-4">

<div class="card-header fw-bold">
Permisos del Rol
</div>

<div class="card-body">

<?php foreach ($permisos as $modulo => $lista): ?>

<h6 class="mt-3"><?= htmlspecialchars($modulo) ?></h6>

<?php foreach ($lista as $p): ?>

<div class="form-check">

<input class="form-check-input"
type="checkbox"
name="permisos[]"
value="<?= $p['id_permiso'] ?>"
<?= in_array($p['id_permiso'],$permisosAsignados) ? 'checked' : '' ?>>

<label class="form-check-label">

<?= htmlspecialchars($p['descripcion']) ?>

</label>

</div>

<?php endforeach; ?>

<?php endforeach; ?>

</div>

<div class="card-footer text-end">

<button class="btn btn-primary">
Guardar permisos
</button>

</div>

</div>

</form>



<!-- =========================
     SELECCIONAR USUARIO
========================= -->

<div class="card mb-4">

<div class="card-header fw-bold">
Seleccionar Usuario
</div>

<div class="card-body">

<form method="GET">

<input type="hidden" name="rol" value="<?= $idRolSeleccionado ?>">

<select name="usuario"
class="form-select"
onchange="this.form.submit()">

<?php foreach ($usuarios as $u): ?>

<option value="<?= $u['id_usuario'] ?>"
<?= $idUsuarioSeleccionado == $u['id_usuario'] ? 'selected' : '' ?>>

<?= htmlspecialchars($u['nombre_completo']) ?>

</option>

<?php endforeach; ?>

</select>

</form>

</div>

</div>


<!-- =========================
     ESTADO DEL USUARIO
========================= -->

<?php if ($idUsuarioSeleccionado > 0): ?>

<?php
$usuarioActual = null;
foreach ($usuarios as $u) {
    if ((int)$u['id_usuario'] === (int)$idUsuarioSeleccionado) {
        $usuarioActual = $u;
        break;
    }
}
?>

<?php if ($usuarioActual): ?>

<div class="card mb-4">

<div class="card-header fw-bold">
Estado del Usuario
</div>

<div class="card-body">

<form method="POST"
      action="<?= BASE_URL ?>/cruds/proceso_estado_usuario.php">

<input type="hidden"
       name="id_usuario"
       value="<?= (int)$usuarioActual['id_usuario'] ?>">

<?php if ((int)$usuarioActual['activo'] === 1): ?>

    <input type="hidden" name="activo" value="0">

    <button class="btn btn-danger btn-sm"
            onclick="return confirm('¿Desactivar usuario?')">
        🔴 Desactivar
    </button>

<?php else: ?>

    <input type="hidden" name="activo" value="1">

    <button class="btn btn-success btn-sm"
            onclick="return confirm('¿Activar usuario?')">
        🟢 Activar
    </button>

<?php endif; ?>

</form>

</div>

</div>

<?php endif; ?>

<?php endif; ?>


<!-- =========================
     AREAS DEL USUARIO
========================= -->

<form method="POST"
action="<?= BASE_URL ?>/cruds/proceso_guardar_areas_usuario.php">

<input type="hidden"
name="id_usuario"
value="<?= $idUsuarioSeleccionado ?>">

<div class="card mb-4">

<div class="card-header fw-bold">
Áreas permitidas
</div>

<div class="card-body">

<div class="row">

<?php foreach ($areas as $a): ?>

<div class="col-md-4">

<div class="form-check">

<input class="form-check-input"
type="checkbox"
name="areas[]"
value="<?= $a['id_area'] ?>"
<?= in_array($a['id_area'],$areasAsignadas) ? 'checked' : '' ?>>

<label class="form-check-label">

<?= htmlspecialchars($a['nombre_area']) ?>

</label>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<div class="card-footer text-end">

<button class="btn btn-success">
Guardar áreas
</button>

</div>

</div>

</form>


</div>


<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>