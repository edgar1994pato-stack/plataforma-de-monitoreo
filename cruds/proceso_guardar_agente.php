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
$SP_EDITAR = 'PR_EDITAR_AGENTE'; // Ajusta si tu SP de editar tiene otro nombre

/* =============================
   RECIBIR DATOS
============================= */
$idAgente   = (int)($_POST['id_agente'] ?? 0);
$esEdicion  = $idAgente > 0;

$codigo     = trim($_POST['codigo_personal'] ?? '');
$nombre     = trim($_POST['nombre_agente'] ?? '');
$email      = trim($_POST['email'] ?? '');
$celular    = trim($_POST['celular'] ?? '');
$idArea     = (int)($_POST['id_area'] ?? 0);
$estado     = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

$veTodo        = can_see_all_areas();
$idAreaSesion  = (int)($_SESSION['id_area'] ?? 0);

/* =============================
   VALIDACIONES MÍNIMAS
============================= */
if ($nombre === '' || $idArea <= 0) {
  header("Location: $BASE_URL/vistas_pantallas/agente_formulario.php" . ($esEdicion ? "?id=$idAgente" : ""));
  exit;
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

    // Ajusta parámetros según tu SP de edición real
    $stmt = $conexion->prepare("EXEC dbo.$SP_EDITAR ?, ?, ?, ?, ?, ?, ?");
    $stmt->execute([
      $idAgente,
      $codigo ?: null,
      $nombre,
      $email ?: null,
      $celular ?: null,
      $idArea,
      $estado
    ]);

  } else {

    if (!can_create()) {
      header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
      exit;
    }

    $stmt = $conexion->prepare("EXEC dbo.$SP_CREAR ?, ?, ?, ?, ?, ?, ?, ?");
    $stmt->execute([
      $codigo ?: null,
      $nombre,
      $email ?: null,
      $celular ?: null,
      $idArea,
      null,  // id_cola
      null,  // id_sucursal
      null   // id_supervisor
    ]);
  }

  header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
  exit;

} catch (Throwable $e) {
  die("Error al guardar agente: " . $e->getMessage());
}
