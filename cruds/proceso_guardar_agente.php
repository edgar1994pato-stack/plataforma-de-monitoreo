<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

$BASE_URL = BASE_URL;

if (is_readonly()) {
  header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
  exit;
}

/* =============================
   CONFIG SP
============================= */
$SP_CREAR  = 'sp_Adm_CrearAgente';
$SP_EDITAR = 'PR_EDITAR_AGENTE'; // Ajustar si tu SP de edición tiene otro nombre

/* =============================
   RECIBIR DATOS
============================= */
$idAgente   = (int)($_POST['id_agente'] ?? 0);
$esEdicion  = $idAgente > 0;

$nombre     = trim($_POST['nombre_agente'] ?? '');
$email      = trim($_POST['email'] ?? '');
$celular    = trim($_POST['celular'] ?? '');
$idArea     = (int)($_POST['id_area'] ?? 0);
$idSucursal = (int)($_POST['id_sucursal'] ?? 0);
$estado     = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

$veTodo        = can_see_all_areas();
$idAreaSesion  = (int)($_SESSION['id_area'] ?? 0);

/* =============================
   VALIDACIONES BACKEND
============================= */

// Nombre obligatorio y solo letras + espacios
if (
    strlen($nombre) < 3 ||
    !preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u', $nombre)
) {
    die("Nombre inválido.");
}

// Área obligatoria
if ($idArea <= 0) {
    die("Debe seleccionar un área válida.");
}

// Sucursal obligatoria
if ($idSucursal <= 0) {
    die("Debe seleccionar una sucursal válida.");
}

// Validar email si se ingresa
if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !preg_match('/@alfanet\.net\.ec$/', $email)) {
        die("El correo debe ser corporativo @alfanet.net.ec");
    }
}

// Validar celular Ecuador si se ingresa
if ($celular !== '') {
    if (!preg_match('/^09[0-9]{8}$/', $celular)) {
        die("El celular debe tener 10 dígitos y empezar con 09.");
    }
}

/* Forzar área si no puede ver todo */
if (!$veTodo && $idAreaSesion > 0) {
    $idArea = $idAreaSesion;
}

try {

    if ($esEdicion) {

        if (function_exists('can_edit') && !can_edit()) {
            header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
            exit;
        }

        $stmt = $conexion->prepare("EXEC dbo.$SP_EDITAR ?, ?, ?, ?, ?, ?, ?, ?");
        $stmt->execute([
            $idAgente,
            $nombre,
            $email ?: null,
            $celular ?: null,
            $idArea,
            $idSucursal,
            $estado,
            null // si tu SP requiere usuario sesión puedes agregarlo aquí
        ]);

    } else {

        if (!can_create()) {
            header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
            exit;
        }

        $stmt = $conexion->prepare("EXEC dbo.$SP_CREAR ?, ?, ?, ?, ?, ?, ?, ?");
        $stmt->execute([
            null,              // codigo_personal (no usado)
            $nombre,
            $email ?: null,
            $celular ?: null,
            $idArea,
            null,              // id_cola
            $idSucursal,
            null               // id_supervisor
        ]);
    }

    header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
    exit;

} catch (Throwable $e) {
    die("Error al guardar agente: " . $e->getMessage());
}
