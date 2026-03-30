<?php

require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
require_permission('ver_modulo_roles');

require_once BASE_PATH . '/config_ajustes/conectar_db.php';

/* =========================
   VALIDAR USUARIO
========================= */

$idUsuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : 0;

if ($idUsuario <= 0) {

    $_SESSION['flash_err'] = "Usuario inválido.";
    header("Location: " . BASE_URL . "/vistas_pantallas/roles.php");
    exit;

}

/* =========================
   ÁREAS ENVIADAS
========================= */

$areas = $_POST['areas'] ?? [];

/* =========================
   TRANSACCIÓN SEGURA
========================= */

try {

$conexion->beginTransaction();

/* eliminar áreas actuales */

$stmt = $conexion->prepare("
DELETE FROM USUARIO_AREA
WHERE id_usuario = ?
");

$stmt->execute([$idUsuario]);

/* insertar nuevas áreas */

if (!empty($areas)) {

$stmtInsert = $conexion->prepare("
INSERT INTO USUARIO_AREA
(id_usuario,id_area)
VALUES (?,?)
");

foreach ($areas as $area) {

$stmtInsert->execute([
$idUsuario,
(int)$area
]);

}

}

$conexion->commit();

$_SESSION['flash_ok'] = "Áreas actualizadas correctamente.";
/* =========================================
   ACTUALIZAR SESIÓN DE ÁREAS
========================================= */

if ((int)$_SESSION['id_usuario'] === (int)$idUsuario) {

    $stmt = $conexion->prepare("
        SELECT id_area
        FROM USUARIO_AREA
        WHERE id_usuario = ?
    ");

    $stmt->execute([$idUsuario]);

    $_SESSION['areas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

} catch (Throwable $e) {

$conexion->rollBack();

$_SESSION['flash_err'] = "Error al guardar las áreas.";

}

/* =========================
   VOLVER A LA PANTALLA
========================= */

header("Location: " . BASE_URL . "/vistas_pantallas/roles.php?usuario=".$idUsuario);
exit;