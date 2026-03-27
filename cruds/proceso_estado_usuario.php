<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();
require_permission('ver_modulo_roles');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$BASE_URL = BASE_URL;

/* =============================
   VALIDACIONES INICIALES
============================= */
if (is_readonly()) {
    $_SESSION['flash_error'] = "Modo solo lectura.";
    header("Location: $BASE_URL/vistas_pantallas/roles.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $BASE_URL/vistas_pantallas/roles.php");
    exit;
}

/* =============================
   RECIBIR DATOS
============================= */
$idUsuario = (int)($_POST['id_usuario'] ?? 0);
$estado    = (int)($_POST['activo'] ?? 0); // 1 o 0

if ($idUsuario <= 0) {
    $_SESSION['flash_error'] = "Usuario inválido.";
    header("Location: $BASE_URL/vistas_pantallas/roles.php");
    exit;
}

/* =============================
   EJECUTAR SP
============================= */
try {

    $sql = "EXEC dbo.sp_Seg_CambiarEstadoUsuario 
                @id_usuario = ?, 
                @activo = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        $idUsuario,
        $estado
    ]);

    $_SESSION['flash_success'] = "Estado actualizado correctamente.";
    header("Location: $BASE_URL/vistas_pantallas/roles.php");
    exit;

} catch (Throwable $e) {

    $_SESSION['flash_error'] = "Error al cambiar estado: " . $e->getMessage();
    header("Location: $BASE_URL/vistas_pantallas/roles.php");
    exit;
}