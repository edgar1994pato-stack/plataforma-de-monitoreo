<?php
/**
 * ARCHIVO: /cruds/proceso_login.php
 * =========================================================
 * PROCESO DE LOGIN (BACKEND) – FINAL PRODUCCIÓN AZURE
 *
 * ✔ Mantiene SP, roles y reglas de negocio
 * ✔ Mantiene guardado de nombre_completo (clave para formularios)
 * ✔ Sin lógica localhost
 * ✔ Sin BASE_URL local
 * ✔ Sin errores 404 en redirecciones
 */

/* =========================================================
 * 1) SESIÓN SEGURA
 * ========================================================= */
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

/* =========================================================
 * 2) CONEXIÓN BD
 * ========================================================= */
require_once BASE_PATH . '/config_ajustes/conectar_db.php';

/* =========================================================
 * 3) LEER DATOS DEL FORMULARIO (POST)
 * ========================================================= */
$correo   = strtolower(trim($_POST['correo'] ?? ''));
$password = (string)($_POST['password'] ?? '');

/* =========================================================
 * 4) VALIDACIÓN BÁSICA
 * ========================================================= */
if (
    $correo === '' ||
    $password === '' ||
    !filter_var($correo, FILTER_VALIDATE_EMAIL)
) {
    usleep(600000);
    $_SESSION['login_error'] = 'Credenciales incorrectas.';
    header('Location: ' . BASE_URL . '/');
    exit;
}

/* =========================================================
 * 5) LÓGICA PRINCIPAL
 * ========================================================= */
try {

    /* =====================================================
     * 5.1) CONSULTAR USUARIO (SP)
     * ===================================================== */
    $stmt = $conexion->prepare("EXEC dbo.PR_LOGIN_GET_USUARIO :correo");
    $stmt->execute([':correo' => $correo]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    /* =====================================================
     * 5.2) VALIDAR EXISTENCIA + ACTIVO
     * ===================================================== */
    if (!$usuario || (int)($usuario['activo'] ?? 0) !== 1) {
        usleep(600000);
        $_SESSION['login_error'] = 'Credenciales incorrectas.';
        header('Location: ' . BASE_URL . '/');
        exit;
    }

    /* =====================================================
     * 5.3) VALIDAR HASH
     * ===================================================== */
    $hashBD = (string)($usuario['password_hash'] ?? '');

    $pareceHash =
        strpos($hashBD, '$2y$') === 0 ||
        strpos($hashBD, '$argon2') === 0;

    if (!$pareceHash) {
        $_SESSION['login_error'] = 'Debe restablecer su contraseña.';
        header('Location: ' . BASE_URL . '/index.php?accion=recuperar_password');
        exit;
    }

    /* =====================================================
     * 5.4) VALIDAR CONTRASEÑA
     * ===================================================== */
    if (!password_verify($password, $hashBD)) {
        usleep(600000);
        $_SESSION['login_error'] = 'Credenciales incorrectas.';
        header('Location: ' . BASE_URL . '/');
        exit;
    }

    /* =====================================================
     * 5.5) LOGIN OK → REGENERAR SESIÓN
     * ===================================================== */
    session_regenerate_id(true);

    /* =====================================================
     * 5.6) CREAR SESIÓN (IDENTIDAD + DATOS ÚTILES)
     * ===================================================== */
    $_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
    $_SESSION['id_rol']     = (int)($usuario['id_rol'] ?? 0);
    $_SESSION['id_area']    = (int)($usuario['id_area'] ?? 0);

    $_SESSION['debe_cambiar_password'] =
        !empty($usuario['debe_cambiar_password']) ? 1 : 0;

    $_SESSION['correo_corporativo'] =
        (string)($usuario['correo_corporativo'] ?? $correo);

    $_SESSION['nombre_completo'] =
        trim((string)($usuario['nombre_completo'] ?? ''));

    $_SESSION['nombre_rol'] =
        (string)($usuario['nombre_rol'] ?? '');

    $_SESSION['area'] =
        (string)($usuario['nombre_area'] ?? '');

    if ($_SESSION['nombre_completo'] === '') {
        $_SESSION['nombre_completo'] = 'SIN_NOMBRE_SESION';
    }

    /* =====================================================
     * 5.7) REDIRECCIONES
     * ===================================================== */
    if (!empty($_SESSION['debe_cambiar_password'])) {
        header('Location: ' . BASE_URL . '/vistas_pantallas/cambiar_password.php');
        exit;
    }

    header('Location: ' . BASE_URL . '/vistas_pantallas/menu.php');
    exit;

} catch (Throwable $e) {

    /* =====================================================
     * 6) ERROR GENERAL
     * ===================================================== */
    $_SESSION['login_error'] = 'Credenciales incorrectas.';
    header('Location: ' . BASE_URL . '/');
    exit;
}
