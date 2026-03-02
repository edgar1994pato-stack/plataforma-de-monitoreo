<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
require_permission('ver_modulo_roles');
force_password_change();

echo "<h3>INICIO DEL PROCESO</h3>";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "No es método POST";
    exit;
}

$idRol = (int)($_POST['id_rol'] ?? 0);
$permisos = $_POST['permisos'] ?? [];

echo "<pre>";
echo "ID ROL: " . $idRol . "\n";
echo "PERMISOS:\n";
print_r($permisos);
echo "</pre>";

if ($idRol <= 0) {
    echo "ID de rol inválido";
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
        echo "<h3 style='color:red'>No se puede quitar permiso crítico al ADMIN</h3>";
        exit;
    }
}

/* =========================
   ACTUALIZAR PERMISOS
========================= */

try {

    echo "<h4>Eliminando permisos actuales...</h4>";
    $stmtDelete = $conexion->prepare("DELETE FROM ROL_PERMISO WHERE id_rol = ?");
    $stmtDelete->execute([$idRol]);

    echo "<h4>Insertando permisos nuevos...</h4>";

    if (!empty($permisos)) {
        $stmtInsert = $conexion->prepare("
            INSERT INTO ROL_PERMISO (id_rol, id_permiso)
            VALUES (?, ?)
        ");

        foreach ($permisos as $idPermiso) {
            $stmtInsert->execute([$idRol, (int)$idPermiso]);
        }
    }

    echo "<h2 style='color:green'>GUARDADO CORRECTAMENTE</h2>";

} catch (Throwable $e) {

    echo "<h2 style='color:red'>ERROR EN BASE DE DATOS</h2>";
    echo "<pre>";
    echo $e->getMessage();
    echo "</pre>";
}

exit;