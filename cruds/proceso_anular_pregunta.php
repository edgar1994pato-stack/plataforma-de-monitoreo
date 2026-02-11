<?php
/**
 * /cruds/proceso_anular_pregunta.php
 * =========================================================
 * ANULAR PREGUNTA (SOLO POR COLA)
 * - Desactiva PONDERACION.ACTIVO = 0 para esa pregunta y cola
 * - Registra auditoría en PREGUNTAS_HISTORIAL vía SP
 *
 * SP: dbo.PR_ANULAR_PREGUNTA
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$BASE_URL = BASE_URL;

/* ===============================
   Permisos
=============================== */
if (is_readonly() || !can_create()) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "No tiene permisos para anular preguntas.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Acceso denegado.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

  exit;
}

$veTodo    = can_see_all_areas();
$idAreaSes = (int)($_SESSION['id_area'] ?? 0);
$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

if (!$veTodo && $idAreaSes <= 0) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "Usuario sin área asignada.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Tu usuario no tiene área asignada. Contacta al administrador.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

  exit;
}

/* ===============================
   Solo POST
=============================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: {$BASE_URL}/vistas_pantallas/listado_preguntas.php");
  exit;
}

/* ===============================
   Leer POST
=============================== */
$id_pregunta = (int)($_POST['id_pregunta'] ?? 0);
$id_cola     = (int)($_POST['id_cola'] ?? 0);
$motivo      = trim((string)($_POST['motivo'] ?? ''));

/* ===============================
   Validaciones básicas
=============================== */
if ($id_pregunta <= 0 || $id_cola <= 0) {
  http_response_code(400);
  $PAGE_TITLE = "⚠️ Validación";
  $PAGE_SUBTITLE = "Datos incompletos.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">ID de pregunta o cola inválido.</div>';
  echo '<a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

  exit;
}

/* ===============================
   Validar área si NO ve todo
=============================== */
if (!$veTodo) {
  $st = $conexion->prepare("SELECT id_area FROM dbo.PREGUNTAS WHERE id_pregunta = ?");
  $st->execute([$id_pregunta]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    $PAGE_TITLE = "❌ No encontrado";
    $PAGE_SUBTITLE = "La pregunta no existe.";
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
    echo '<div class="alert alert-danger">La pregunta no existe.</div>';
   require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

    exit;
  }

  if ((int)$row['id_area'] !== $idAreaSes) {
    http_response_code(403);
    $PAGE_TITLE = "⛔ Acceso denegado";
    $PAGE_SUBTITLE = "La pregunta no pertenece a tu área.";
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
    echo '<div class="alert alert-danger">Acceso denegado: esta pregunta no pertenece a tu área.</div>';
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

    exit;
  }
}

/* ===============================
   Ejecutar SP (SIN OUTPUT):
   - El SP devuelve SELECT ok, mensaje
=============================== */
try {
  // IMPORTANTE: aquí llamamos al SP con 4 parámetros (sin OUTPUT)
  $sql = "EXEC dbo.PR_ANULAR_PREGUNTA @ID_PREGUNTA = ?, @ID_COLA = ?, @USUARIO_ANULA = ?, @MOTIVO = ?";
  $stmt = $conexion->prepare($sql);

  $pUsuario = ($idUsuario > 0) ? $idUsuario : null;
  $pMotivo  = ($motivo !== '') ? $motivo : null;

  $stmt->execute([$id_pregunta, $id_cola, $pUsuario, $pMotivo]);

  // leer el SELECT del SP
  $resp = $stmt->fetch(PDO::FETCH_ASSOC);
  $ok = (int)($resp['ok'] ?? 1);
  $mensaje = (string)($resp['mensaje'] ?? 'Pregunta anulada correctamente.');

  $_SESSION['flash_ok'] = "✅ " . $mensaje;
  header("Location: {$BASE_URL}/vistas_pantallas/listado_preguntas.php");
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  $PAGE_TITLE = "❌ Error al anular";
  $PAGE_SUBTITLE = "El SP rechazó la operación.";
 require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger"><b>No se pudo anular la pregunta.</b><div class="mt-2 small">'.h($e->getMessage()).'</div></div>';
  echo '<a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

  exit;
}
