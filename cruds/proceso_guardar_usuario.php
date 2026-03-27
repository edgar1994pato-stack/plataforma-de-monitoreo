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
$nombre = trim($_POST['nombre_completo'] ?? '');
$correo = trim($_POST['correo_corporativo'] ?? '');
$idRol  = (int)($_POST['id_rol'] ?? 0);
$idArea = (int)($_POST['id_area'] ?? 0);

/* =============================
   VALIDACIONES
============================= */
if ($nombre === '' || mb_strlen($nombre) < 3) {
    $_SESSION['flash_error'] = "El nombre completo es obligatorio.";
    header("Location: $BASE_URL/vistas_pantallas/usuarios_formulario.php");
    exit;
}

if ($correo === '') {
    $_SESSION['flash_error'] = "El correo corporativo es obligatorio.";
    header("Location: $BASE_URL/vistas_pantallas/usuarios_formulario.php");
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._%+\-]+@alfanet\.net\.ec$/', $correo)) {
    $_SESSION['flash_error'] = "El correo debe ser @alfanet.net.ec.";
    header("Location: $BASE_URL/vistas_pantallas/usuarios_formulario.php");
    exit;
}

if ($idRol <= 0) {
    $_SESSION['flash_error'] = "Debe seleccionar un rol.";
    header("Location: $BASE_URL/vistas_pantallas/usuarios_formulario.php");
    exit;
}

/* =============================
   VALIDAR SUPERVISOR
============================= */
$ROL_SUPERVISOR = 4; // ⚠️ valida este ID en tu tabla ROLES

if ($idRol === $ROL_SUPERVISOR && $idArea <= 0) {
    $_SESSION['flash_error'] = "Debe seleccionar un área para Supervisor.";
    header("Location: $BASE_URL/vistas_pantallas/usuarios_formulario.php");
    exit;
}

if ($idRol !== $ROL_SUPERVISOR) {
    $idArea = null;
}

/* =============================
   PASSWORD TÉCNICA (OBLIGATORIA POR SP)
============================= */
$passwordTecnica = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

/* =============================
   EJECUTAR SP
============================= */
try {

    $sql = "EXEC dbo.sp_Seg_CrearUsuario 
                @nombre_completo = ?, 
                @correo_corporativo = ?, 
                @password = ?, 
                @id_rol = ?, 
                @id_area = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        $nombre,
        $correo,
        $passwordTecnica,
        $idRol,
        $idArea
    ]);

    $_SESSION['flash_success'] = "Usuario creado correctamente.";
    header("Location: $BASE_URL/vistas_pantallas/roles.php");
    exit;

} catch (Throwable $e) {

    $_SESSION['flash_error'] = "Error al guardar: " . $e->getMessage();
    header("Location: $BASE_URL/vistas_pantallas/usuarios_formulario.php");
    exit;
}