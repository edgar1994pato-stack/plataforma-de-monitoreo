<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

$BASE_URL = BASE_URL;

/* =============================
   VALIDACIONES INICIALES
============================= */

if (is_readonly()) {
  $_SESSION['flash_error'] = "Modo solo lectura.";
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
$SP_EDITAR = 'PR_EDITAR_AGENTE';

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

function redirError($msg, $BASE_URL, $idAgente = 0) {
    $_SESSION['flash_error'] = $msg;
    $url = $BASE_URL . "/vistas_pantallas/agente_formulario.php";
    if ($idAgente > 0) {
        $url .= "?id=" . (int)$idAgente;
    }
    header("Location: $url");
    exit;
}

// Nombre obligatorio
if (strlen($nombre) < 3 ||
    !preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u', $nombre)) {
    redirError("Nombre inválido. Solo letras y mínimo 3 caracteres.", $BASE_URL, $idAgente);
}

// Área obligatoria
if ($idArea <= 0) {
    redirError("Debe seleccionar un área válida.", $BASE_URL, $idAgente);
}

// Sucursal obligatoria
if ($idSucursal <= 0) {
    redirError("Debe seleccionar una sucursal válida.", $BASE_URL, $idAgente);
}

// Email corporativo
if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !preg_match('/@alfanet\.net\.ec$/i', $email)) {
        redirError("El correo debe ser corporativo @alfanet.net.ec", $BASE_URL, $idAgente);
    }
}

// Celular Ecuador
if ($celular !== '') {
    if (!preg_match('/^09[0-9]{8}$/', $celular)) {
        redirError("El celular debe tener 10 dígitos y empezar con 09.", $BASE_URL, $idAgente);
    }
}

// Forzar área si no puede ver todo
if (!$veTodo && $idAreaSesion > 0) {
    $idArea = $idAreaSesion;
}

/* =============================
   GUARDAR EN BD
============================= */

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
            null
        ]);

        $_SESSION['flash_success'] = "Agente actualizado correctamente.";

    } else {

        if (!can_create()) {
            header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
            exit;
        }

        $stmt = $conexion->prepare("EXEC dbo.$SP_CREAR ?, ?, ?, ?, ?, ?, ?, ?");
        $stmt->execute([
            null,
            $nombre,
            $email ?: null,
            $celular ?: null,
            $idArea,
            null,
            $idSucursal,
            null
        ]);

        $_SESSION['flash_success'] = "Agente creado correctamente.";
    }

    header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
    exit;

} catch (Throwable $e) {

    $_SESSION['flash_error'] = "Error al guardar agente.";
    header("Location: $BASE_URL/vistas_pantallas/agente_formulario.php");
    exit;
}
