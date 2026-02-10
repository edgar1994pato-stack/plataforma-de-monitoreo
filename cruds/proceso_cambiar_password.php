<?php
// /cruds/proceso_cambiar_password.php

require_once '../config_ajustes/conectar_db.php';
session_start();

$BASE_URL = "/plataforma_de_monitoreo";

// Debe existir sesión
if (empty($_SESSION['id_usuario'])) {
    header("Location: {$BASE_URL}/vistas_pantallas/login.php");

    exit;
}

$actual    = (string)($_POST['actual'] ?? '');
$nueva     = (string)($_POST['nueva'] ?? '');
$confirmar = (string)($_POST['confirmar'] ?? '');

// Validaciones básicas
if ($actual === '' || $nueva === '' || $confirmar === '') {
    $_SESSION['pw_error'] = 'Complete todos los campos.';
    header("Location: {$BASE_URL}/vistas_pantallas/cambiar_password.php");

    exit;
}

if ($nueva !== $confirmar) {
    $_SESSION['pw_error'] = 'La confirmación no coincide.';
    header("Location: {$BASE_URL}/vistas_pantallas/cambiar_password.php");

    exit;
}

if (strlen($nueva) < 8) {
    $_SESSION['pw_error'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    header("Location: {$BASE_URL}/vistas_pantallas/cambiar_password.php");

    exit;
}

try {
    // 1) Obtener hash actual del usuario (para validar la contraseña actual)
    $stmt = $conexion->prepare("
        SELECT password_hash, activo
        FROM dbo.USUARIOS
        WHERE id_usuario = :id
    ");
    $stmt->execute([':id' => (int)$_SESSION['id_usuario']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u || (int)$u['activo'] !== 1) {
        $_SESSION['pw_error'] = 'Usuario inválido o inactivo.';
        header("Location: {$BASE_URL}/vistas/cambiar_password.php");
        exit;
    }

    if (!password_verify($actual, $u['password_hash'])) {
        $_SESSION['pw_error'] = 'La contraseña actual es incorrecta.';
        header("Location: {$BASE_URL}/vistas/cambiar_password.php");
        exit;
    }

    // 2) Generar nuevo hash bcrypt (PHP valida y SQL guarda)
    $nuevoHash = password_hash($nueva, PASSWORD_BCRYPT);

    // 3) Ejecutar SP para actualizar password
    $upd = $conexion->prepare("EXEC dbo.PR_USUARIO_CAMBIAR_PASSWORD :id_usuario, :nuevo_password_hash");
    $upd->execute([
        ':id_usuario'          => (int)$_SESSION['id_usuario'],
        ':nuevo_password_hash' => $nuevoHash
    ]);

    // 4) Quitar flag en sesión para que ya no redirija a cambiar_password
    $_SESSION['debe_cambiar_password'] = 0;

    $_SESSION['pw_ok'] = 'Contraseña actualizada correctamente.';
    header("Location: {$BASE_URL}/vistas/formulario.php");
    exit;

} catch (Throwable $e) {
    $_SESSION['pw_error'] = 'Error interno al actualizar la contraseña.';
    header("Location: {$BASE_URL}/vistas/cambiar_password.php");
    exit;
}
