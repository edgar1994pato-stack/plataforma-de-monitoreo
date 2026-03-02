<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
require_permission('ver_modulo_roles');
force_password_change();

$BASE_URL = BASE_URL;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$BASE_URL}/vistas_pantallas/roles.php");
    exit;
}

$idRol = (int)($_POST['id_rol'] ?? 0);
$permisos = $_POST['permisos'] ?? [];

if ($idRol <= 0) {
    header("Location: {$BASE_URL}/vistas_pantallas/roles.php");
    exit;
}

/* =========================
   PROTEGER ADMIN
========================= */
$stmt = $conexion->prepare("SELECT nombre_rol FROM ROLES WHERE id_rol = ?");
$stmt->execute([$idRol]);
$rol = $stmt->fetch(PDO::FETCH_ASSOC);

if ($rol && strtoupper($rol['nombre_rol']) === 'ADMINISTRADOR DEL SISTEMA') {

    $stmt = $conexion->prepare("SELECT id_permiso FROM PERMISOS WHERE codigo = 'ver_modulo_roles'");
    $stmt->execute();
    $permCritico = (int)$stmt->fetchColumn();

    if (!in_array($permCritico, $permisos)) {
        $_SESSION['flash_err'] = "No se puede quitar el permiso crítico del ADMIN.";
        header("Location: {$BASE_URL}/vistas_pantallas/roles.php?rol={$idRol}");
        exit;
    }
}

/* =========================
   ACTUALIZAR PERMISOS
========================= */

try {

    // Eliminar permisos actuales
    $stmtDelete = $conexion->prepare("DELETE FROM ROL_PERMISO WHERE id_rol = ?");
    $stmtDelete->execute([$idRol]);

    // Insertar nuevos
    if (!empty($permisos)) {
        $stmtInsert = $conexion->prepare("
            INSERT INTO ROL_PERMISO (id_rol, id_permiso)
            VALUES (?, ?)
        ");

        foreach ($permisos as $idPermiso) {
            $stmtInsert->execute([$idRol, (int)$idPermiso]);
        }
    }

    $_SESSION['flash_ok'] = "Permisos actualizados correctamente.";

} catch (Throwable $e) {

    $_SESSION['flash_err'] = "Error al actualizar permisos.";
}

header("Location: {$BASE_URL}/vistas_pantallas/roles.php?rol={$idRol}");
exit;