<?php
/**
 * /cruds/proceso_guardar_preguntas.php
 * ============================================
 * Procesa CREAR (y CREAR desde DUPLICAR)
 * - Usa SP: dbo.PR_CREAR_PREGUNTA
 */

require_once '../config_ajustes/conectar_db.php';
require_once '../includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$BASE_URL = '/plataforma_de_monitoreo';

/* ===============================
   Seguridad / permisos
=============================== */
if (is_readonly() || !can_create()) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "No tiene permisos para crear preguntas.";
  require_once '../includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Acceso denegado.</div>';
  require_once '../includes_partes_fijas/diseno_abajo.php';
  exit;
}

$veTodo    = can_see_all_areas();
$idAreaSes = (int)($_SESSION['id_area'] ?? 0);

if (!$veTodo && $idAreaSes <= 0) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "Usuario sin área asignada.";
  require_once '../includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Tu usuario no tiene área asignada. Contacta al administrador.</div>';
  require_once '../includes_partes_fijas/diseno_abajo.php';
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
   Leer datos POST
=============================== */
$accion = strtoupper(trim((string)($_POST['accion'] ?? '')));
$modo   = strtolower(trim((string)($_POST['modo'] ?? 'crear')));

if ($accion !== 'CREAR') {
  header("Location: {$BASE_URL}/vistas_pantallas/listado_preguntas.php");
  exit;
}

$id_area    = (int)($_POST['id_area'] ?? 0);
$id_cola    = (int)($_POST['id_cola'] ?? 0);
$id_seccion = (int)($_POST['id_seccion'] ?? 0);

$aspecto    = trim((string)($_POST['aspecto'] ?? ''));
$direccion  = trim((string)($_POST['direccion'] ?? ''));
$tipo       = strtoupper(trim((string)($_POST['tipo'] ?? 'NORMAL')));

$pregunta    = trim((string)($_POST['pregunta'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));

$gestion    = trim((string)($_POST['gestion'] ?? ''));
$peso       = (string)($_POST['peso'] ?? '0');

$id_origen  = (int)($_POST['id_origen'] ?? 0);

/* Forzar área si NO ve todo */
if (!$veTodo) {
  $id_area = $idAreaSes;
}

/* ===============================
   Validación mínima en PHP
=============================== */
$errores = [];

if ($id_area <= 0)    $errores[] = "Área inválida.";
if ($id_cola <= 0)    $errores[] = "Cola inválida.";
if ($id_seccion <= 0) $errores[] = "Sección inválida.";

if ($aspecto === '')   $errores[] = "Aspecto es obligatorio.";
if ($direccion === '') $errores[] = "Dirección es obligatoria.";
if ($gestion === '')   $errores[] = "Gestión es obligatoria.";

if (mb_strlen($pregunta) < 10) $errores[] = "La pregunta debe tener mínimo 10 caracteres.";

$pesoNum = (float)str_replace(',', '.', $peso);
if (!($pesoNum > 0)) $errores[] = "El peso debe ser mayor a 0.";
if ($pesoNum > 100)  $errores[] = "El peso no puede ser mayor a 100.";

if (!in_array($tipo, ['NORMAL','CRITICO','IMPULSOR'], true)) {
  $errores[] = "Tipo inválido.";
}

if (!empty($errores)) {
  http_response_code(400);
  $PAGE_TITLE = "⚠️ Validación";
  $PAGE_SUBTITLE = "Revisa los campos.";
  require_once '../includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger"><b>Corrige lo siguiente:</b><ul class="mb-0 mt-2">';
  foreach ($errores as $e) echo '<li>'.h($e).'</li>';
  echo '</ul></div>';
  echo '<a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>';
  require_once '../includes_partes_fijas/diseno_abajo.php';
  exit;
}

/* ===============================
   Ejecutar SP: PR_CREAR_PREGUNTA
   ✅ NO dependemos del OUTPUT
   ✅ leemos el SELECT id_pregunta_creada
=============================== */
try {
  $sql = "EXEC dbo.PR_CREAR_PREGUNTA
            @ID_AREA = ?,
            @ID_COLA = ?,
            @ID_SECCION = ?,
            @PREGUNTA = ?,
            @DESCRIPCION = ?,
            @ASPECTO = ?,
            @DIRECCION = ?,
            @TIPO = ?,
            @GESTION = ?,
            @PESO = ?,
            @ID_PREGUNTA_NUEVA = NULL";

  $stmt = $conexion->prepare($sql);

  $stmt->execute([
    $id_area,
    $id_cola,
    $id_seccion,
    $pregunta,
    ($descripcion !== '' ? $descripcion : null),
    $aspecto,
    $direccion,
    $tipo,
    $gestion,
    $pesoNum
  ]);

  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $id_nueva = (int)($row['id_pregunta_creada'] ?? 0);

  if ($id_nueva <= 0) {
    throw new Exception("No se pudo obtener el ID creado. Verifica que el SP retorne 'id_pregunta_creada'.");
  }

  $_SESSION['flash_ok'] = "✅ Pregunta creada correctamente. ID: {$id_nueva}";
  header("Location: {$BASE_URL}/vistas_pantallas/listado_preguntas.php?id_area={$id_area}");
  exit;

} catch (Throwable $e) {
  $msg = $e->getMessage();

  http_response_code(500);
  $PAGE_TITLE = "❌ Error al crear";
  $PAGE_SUBTITLE = "El SP rechazó la operación o hubo un error.";
  require_once '../includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger"><b>No se pudo crear la pregunta.</b><div class="mt-2 small">'.h($msg).'</div></div>';
  echo '<a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>';
  require_once '../includes_partes_fijas/diseno_abajo.php';
  exit;
}
