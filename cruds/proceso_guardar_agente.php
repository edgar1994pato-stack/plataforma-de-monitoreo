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
   CONFIG SP (ajusta si tu SP tiene otro nombre)
============================= */
$SP_CREAR  = 'PR_CREAR_AGENTE';       // <-- si tu SP de crear tiene otro nombre, cámbialo aquí
$SP_EDITAR = 'PR_EDITAR_AGENTE';      // <-- si tu SP de editar tiene otro nombre, cámbialo aquí

/* =============================
   RECIBIR DATOS
============================= */
$idAgente = (int)($_POST['id_agente'] ?? 0);
$esEdicion = $idAgente > 0;

$nombre = trim($_POST['nombre_agente'] ?? '');
$idArea = (int)($_POST['id_area'] ?? 0);
$idCola = (int)($_POST['id_cola'] ?? 0);
$idSupervisor = (int)($_POST['id_supervisor_usuario'] ?? 0);
$estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);
$veTodo = can_see_all_areas();
$idAreaSesion = (int)($_SESSION['id_area'] ?? 0);

/* Reglas mínimas backend */
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

    // Permisos edición (si tienes función)
    if (function_exists('can_edit') && !can_edit()) {
      header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
      exit;
    }

    $stmt = $conexion->prepare("EXEC dbo.$SP_EDITAR ?, ?, ?, ?, ?, ?, ?");
    $stmt->execute([
      $idAgente,
      $nombre,
      $idArea,
      ($idCola > 0 ? $idCola : null),
      ($idSupervisor > 0 ? $idSupervisor : null),
      $estado,
      $idUsuarioSesion
    ]);

  } else {

    if (!can_create()) {
      header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
      exit;
    }

    $stmt = $conexion->prepare("EXEC dbo.$SP_CREAR ?, ?, ?, ?, ?, ?");
    $stmt->execute([
      $nombre,
      $idArea,
      ($idCola > 0 ? $idCola : null),
      ($idSupervisor > 0 ? $idSupervisor : null),
      $estado,
      $idUsuarioSesion
    ]);
  }

  header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
  exit;

} catch (Throwable $e) {
  // Si ya manejas flashes, aquí lo conectas. Por ahora, mostramos el error para depurar.
  die("Error al guardar agente: " . $e->getMessage());
}
