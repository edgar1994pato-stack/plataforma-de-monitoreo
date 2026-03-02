<?php
/**
 * /cruds/proceso_guardar_roles.php
 * ============================================
 * Actualiza permisos de un rol
 * - Usa DELETE + INSERT dentro de transacción
 * - Blindaje para rol ADMIN
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();
require_permission('ver_modulo_roles');

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$BASE_URL = BASE_URL;

/* ===============================
   Solo método POST
=============================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$BASE_URL}/vistas_pantallas/roles.php");
    exit;
}

/* ===============================
   Leer datos POST
=============================== */
$id_rol = isset($_POST['id_rol']) ? (int)$_POST['id_rol'] : 0;
$permisos = $_POST['permisos'] ?? [];

if ($id_rol <= 0) {
    http_response_code(400);
    $PAGE_TITLE = "⚠️ Validación";
    $PAGE_SUBTITLE = "Rol inválido.";
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
    echo '<div class="alert alert-danger">Rol inválido.</div>';
    require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
    exit;
}

/* ===============================
   Verificar que el rol exista
=============================== */
try {
    $stmtRol = $conexion->prepare("
        SELECT id_rol, nombre_rol
        FROM ROLES
        WHERE id_rol = ? AND fecha_fin IS NULL
    ");
    $stmtRol->execute([$id_rol]);
    $rol = $stmtRol->fetch(PDO::FETCH_ASSOC);

    if (!$rol) {
        throw new Exception("El rol seleccionado no existe o está inactivo.");
    }

} catch (Throwable $e) {

    http_response_code(400);
    $PAGE_TITLE = "⚠️ Validación";
    $PAGE_SUBTITLE = "Error de rol.";
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
    echo '<div class="alert alert-danger">'.h($e->getMessage()).'</div>';
    require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
    exit;
}

/* ===============================
   PROCESO PRINCIPAL
=============================== */
try {

    $conexion->beginTransaction();

    /* ===============================
       Blindaje ADMIN (ID = 1)
       Nunca puede perder ver_modulo_roles
    =============================== */
    if ($id_rol == 1) {

        $stmtPerm = $conexion->prepare("
            SELECT id_permiso
            FROM PERMISOS
            WHERE codigo = 'ver_modulo_roles'
              AND activo = 1
        ");
        $stmtPerm->execute();
        $idPermisoCritico = (int)$stmtPerm->fetchColumn();

        if ($idPermisoCritico <= 0) {
            throw new Exception("Permiso crítico no encontrado.");
        }

        if (!in_array($idPermisoCritico, array_map('intval', $permisos))) {
            throw new Exception("No se puede eliminar el permiso crítico del ADMIN (ver_modulo_roles).");
        }
    }

    /* ===============================
       Eliminar permisos actuales
    =============================== */
    $stmtDelete = $conexion->prepare("
        DELETE FROM ROL_PERMISO
        WHERE id_rol = ?
    ");
    $stmtDelete->execute([$id_rol]);

    /* ===============================
       Insertar nuevos permisos
    =============================== */
    if (!empty($permisos)) {

        $stmtInsert = $conexion->prepare("
            INSERT INTO ROL_PERMISO (id_rol, id_permiso)
            VALUES (?, ?)
        ");

        foreach ($permisos as $id_permiso) {
            $stmtInsert->execute([
                $id_rol,
                (int)$id_permiso
            ]);
        }
    }

    $conexion->commit();

    $_SESSION['flash_ok'] = "✅ Permisos actualizados correctamente para el rol: {$rol['nombre_rol']}";

    header("Location: {$BASE_URL}/vistas_pantallas/roles.php?rol={$id_rol}");
    exit;

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    http_response_code(500);
    $PAGE_TITLE = "❌ Error al guardar";
    $PAGE_SUBTITLE = "No se pudieron actualizar los permisos.";
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
    echo '<div class="alert alert-danger">';
    echo '<b>Error:</b> '.h($e->getMessage());
    echo '</div>';
    echo '<a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>';
    require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
    exit;
}