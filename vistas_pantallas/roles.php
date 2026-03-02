<?php
/**
 * /vistas_pantallas/roles.php
 * ---------------------------------------
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
   CONEXIÓN BD (PDO)
========================= */
require_once BASE_PATH . '/config_ajustes/conectar_db.php';

/* =========================
   HEADER
========================= */
$PAGE_TITLE = "Administración de Roles";
$PAGE_SUBTITLE = "Gestión de permisos del sistema";

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

    <!-- SELECTOR DE ROL -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <label class="form-label fw-bold">Seleccionar Rol</label>
            <select class="form-select" disabled>
                <?php if (count($roles) === 0): ?>
                    <option>No hay roles registrados</option>
                <?php else: ?>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id_rol'] ?>">
                            <?= htmlspecialchars($r['nombre_rol']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <small class="text-muted">
                Fase inicial: solo visualización
            </small>
        </div>
    </div>

    <!-- PERMISOS AGRUPADOS POR MÓDULO -->
    <?php if (count($permisos) === 0): ?>
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
                                           disabled>
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

    <?php endif; ?>

</div>

<?php
/* =========================
   FOOTER
========================= */
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';