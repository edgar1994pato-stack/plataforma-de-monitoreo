<?php
/**
 * /cruds/proceso_corregir_monitoreo.php
 * ✅ Guarda corrección (nueva versión) + observaciones + redirige al listado con mensaje.
 * ✅ Permisos centralizados (seguridad.php)
 * ✅ Enforce de área para evitar manipulación de POST
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

/* =========================
   Helpers
========================= */
function post($k, $default=null){ return $_POST[$k] ?? $default; }

function redirect_with_msg($url, $ok, $msg){
  $sep = (strpos($url,'?') === false) ? '?' : '&';
  header("Location: {$url}{$sep}ok=".(int)$ok."&msg=".urlencode($msg));
  exit;
}

/* =========================
   1) PERMISOS (CENTRALIZADOS)
========================= */
if (is_readonly() || !can_correct()) {
  redirect_with_msg('../vistas_pantallas/listado_monitoreos.php', 0, 'No tiene permisos para corregir.');
}

/* =========================
   2) Auditor desde sesión
========================= */
$idAuditorSesion = (int)($_SESSION['id_usuario'] ?? 0);
if ($idAuditorSesion <= 0) {
  redirect_with_msg('../vistas_pantallas/listado_monitoreos.php', 0, 'Sesión inválida (IdAuditor).');
}

$idAreaSesion = (int)($_SESSION['id_area'] ?? 0);
$veTodo       = can_see_all_areas();

/* =========================
   3) Captura POST
========================= */
$idOrigen      = (int)post('IdOrigen', 0);
$idVersionBase = (int)post('IdVersionBase', 0);

$idCola   = (int)post('IdCola', 0);
$idAgente = (int)post('IdAgente', 0);
$idMedio  = (int)post('IdMedio', 0);

$tipoMonitoreo   = trim((string)post('TipoMonitoreo', ''));
$gestorMonitoreo = trim((string)post('gestor_monitoreo', ''));

$idInteraccion  = trim((string)post('id_interaccion', ''));
$numeroContrato = trim((string)post('numero_contrato', ''));
$numeroContacto = trim((string)post('numero_contacto', ''));
$linkEvidencia  = trim((string)post('link_evidencia', ''));

$fechaInteraccion = trim((string)post('fecha_interaccion', '')); // YYYY-MM-DD
$horaInteraccion  = trim((string)post('hora_interaccion', ''));  // HH:MM
$durInterSeg      = (int)post('duracion_interaccion_segundos', 0);

$descripcionFinal = trim((string)post('DescripcionFinal', ''));
$motivoCorreccion = trim((string)post('motivo_correccion', ''));

$jsonRespuestas = trim((string)post('JsonRespuestas', ''));

// tiempos corrección (opcionales)
$horaInicioCorr   = trim((string)post('hora_inicio', '')); // HH:MM
$horaFinCorr      = trim((string)post('hora_fin', ''));    // HH:MM

/* =========================
   4) Validaciones rápidas
========================= */
$errores = [];

if ($idOrigen <= 0)      $errores[] = 'IdOrigen inválido.';
if ($idVersionBase <= 0) $errores[] = 'IdVersionBase inválido.';
if ($idCola <= 0)        $errores[] = 'IdCola inválido.';
if ($idAgente <= 0)      $errores[] = 'IdAgente inválido.';
if ($idMedio <= 0)       $errores[] = 'IdMedio inválido.';

if ($motivoCorreccion === '' || mb_strlen($motivoCorreccion) < 10) $errores[] = 'Motivo de corrección obligatorio (mín. 10 caracteres).';
if ($jsonRespuestas === '') $errores[] = 'JsonRespuestas vacío.';

if ($tipoMonitoreo === '') $errores[] = 'TipoMonitoreo requerido.';
if ($idInteraccion === '') $errores[] = 'ID Interacción requerido.';
if ($numeroContrato === '') $errores[] = 'Número de contrato requerido.';
if ($numeroContacto === '') $errores[] = 'Número de contacto requerido.';
if ($linkEvidencia === '') $errores[] = 'Link evidencia requerido.';
if ($descripcionFinal === '') $errores[] = 'Descripción final requerida.';
if ($fechaInteraccion === '') $errores[] = 'Fecha interacción requerida.';
if ($horaInteraccion === '')  $errores[] = 'Hora interacción requerida.';

// validar JSON
$decoded = json_decode($jsonRespuestas, true);
if (!is_array($decoded) || count($decoded) === 0) {
  $errores[] = 'JsonRespuestas inválido o vacío.';
}

if (!empty($errores)) {
  redirect_with_msg("../vistas_pantallas/corregir_monitoreo.php?id={$idVersionBase}", 0, implode(' ', $errores));
}

/* =========================
   5) Construir DATETIME2 de interacción
========================= */
$fechaHoraInteraccion = $fechaInteraccion . ' ' . $horaInteraccion . ':00';

/* =========================
   6) Construir DATETIME2 inicio/fin corrección (opcional)
========================= */
$hoy = date('Y-m-d');
$horaInicioMonitoreo = null;
$horaFinMonitoreo    = null;

if (preg_match('/^\d{2}:\d{2}$/', $horaInicioCorr)) $horaInicioMonitoreo = $hoy.' '.$horaInicioCorr.':00';
if (preg_match('/^\d{2}:\d{2}$/', $horaFinCorr))    $horaFinMonitoreo    = $hoy.' '.$horaFinCorr.':00';

/* =========================
   7) Seguridad extra:
   - Validar versión base existe
   - Forzar cola/agente/medio desde la base
   - Enforce de área si no ve todo
========================= */
try {
  $sqlCheck = "
    SELECT TOP 1 id_cola, id_agente, id_medio, id_area
    FROM dbo.MONITOREO_VERSION
    WHERE id_origen = ? AND id_version = ? AND estado_version <> 'ELIMINADO'
  ";
  $st = $conexion->prepare($sqlCheck);
  $st->execute([$idOrigen, $idVersionBase]);
  $rowBase = $st->fetch(PDO::FETCH_ASSOC);

  if (!$rowBase) {
    redirect_with_msg("../vistas_pantallas/corregir_monitoreo.php?id={$idVersionBase}", 0, 'No existe la versión base para corregir.');
  }

  // Forzar desde la base (anti-manipulación)
  $idCola   = (int)$rowBase['id_cola'];
  $idAgente = (int)$rowBase['id_agente'];
  $idMedio  = (int)$rowBase['id_medio'];
  $idAreaDb = (int)$rowBase['id_area'];

  // Enforce por área si el rol NO ve todo
  if (!$veTodo) {
    if ($idAreaSesion <= 0) {
      redirect_with_msg("../vistas_pantallas/listado_monitoreos.php", 0, 'Tu usuario no tiene un área asignada.');
    }
    if ($idAreaDb !== $idAreaSesion) {
      redirect_with_msg("../vistas_pantallas/listado_monitoreos.php", 0, 'Acceso denegado: este monitoreo no pertenece a tu área.');
    }
  }

} catch (Throwable $e) {
  redirect_with_msg("../vistas_pantallas/corregir_monitoreo.php?id={$idVersionBase}", 0, 'Error validando versión base: '.$e->getMessage());
}

/* =========================
   8) Ejecutar SP PR_CORREGIR_MONITOREO
========================= */
try {
  $sql = "
    EXEC dbo.PR_CORREGIR_MONITOREO
      @IdOrigen = :IdOrigen,
      @IdVersionBase = :IdVersionBase,
      @MotivoCorreccion = :MotivoCorreccion,

      @IdAgente = :IdAgente,
      @IdCola = :IdCola,
      @IdAuditor = :IdAuditor,
      @IdMedio = :IdMedio,
      @JsonRespuestas = :JsonRespuestas,

      @IdInteraccion = :IdInteraccion,
      @FechaHoraInteraccion = :FechaHoraInteraccion,
      @DuracionInteraccionSegundos = :DuracionInteraccionSegundos,

      @MedioDeAtencion = :MedioDeAtencion,
      @TipoMonitoreo = :TipoMonitoreo,
      @NumeroContrato = :NumeroContrato,
      @NumeroContacto = :NumeroContacto,
      @LinkEvidencia = :LinkEvidencia,
      @DescripcionFinal = :DescripcionFinal,
      @GestorMonitoreo = :GestorMonitoreo,

      @HoraInicioMonitoreo = :HoraInicioMonitoreo,
      @HoraFinMonitoreo = :HoraFinMonitoreo
  ";

  $stmt = $conexion->prepare($sql);

  $stmt->bindValue(':IdOrigen', $idOrigen, PDO::PARAM_INT);
  $stmt->bindValue(':IdVersionBase', $idVersionBase, PDO::PARAM_INT);
  $stmt->bindValue(':MotivoCorreccion', $motivoCorreccion, PDO::PARAM_STR);

  $stmt->bindValue(':IdAgente', $idAgente, PDO::PARAM_INT);
  $stmt->bindValue(':IdCola', $idCola, PDO::PARAM_INT);
  $stmt->bindValue(':IdAuditor', $idAuditorSesion, PDO::PARAM_INT);
  $stmt->bindValue(':IdMedio', $idMedio, PDO::PARAM_INT);
  $stmt->bindValue(':JsonRespuestas', $jsonRespuestas, PDO::PARAM_STR);

  $stmt->bindValue(':IdInteraccion', $idInteraccion, PDO::PARAM_STR);
  $stmt->bindValue(':FechaHoraInteraccion', $fechaHoraInteraccion, PDO::PARAM_STR);
  $stmt->bindValue(':DuracionInteraccionSegundos', $durInterSeg, PDO::PARAM_INT);

  $medioTxt = trim((string)post('MedioDeAtencion', ''));
  $stmt->bindValue(':MedioDeAtencion', $medioTxt, PDO::PARAM_STR);

  $stmt->bindValue(':TipoMonitoreo', $tipoMonitoreo, PDO::PARAM_STR);
  $stmt->bindValue(':NumeroContrato', $numeroContrato, PDO::PARAM_STR);
  $stmt->bindValue(':NumeroContacto', $numeroContacto, PDO::PARAM_STR);
  $stmt->bindValue(':LinkEvidencia', $linkEvidencia, PDO::PARAM_STR);
  $stmt->bindValue(':DescripcionFinal', $descripcionFinal, PDO::PARAM_STR);
  $stmt->bindValue(':GestorMonitoreo', $gestorMonitoreo, PDO::PARAM_STR);

  // NULLables
  if ($horaInicioMonitoreo) $stmt->bindValue(':HoraInicioMonitoreo', $horaInicioMonitoreo, PDO::PARAM_STR);
  else $stmt->bindValue(':HoraInicioMonitoreo', null, PDO::PARAM_NULL);

  if ($horaFinMonitoreo) $stmt->bindValue(':HoraFinMonitoreo', $horaFinMonitoreo, PDO::PARAM_STR);
  else $stmt->bindValue(':HoraFinMonitoreo', null, PDO::PARAM_NULL);

  $stmt->execute();

  $resp = $stmt->fetch(PDO::FETCH_ASSOC);
  $idNuevaVersion = 0;
  if ($resp) {
    if (isset($resp['id_version_new'])) $idNuevaVersion = (int)$resp['id_version_new'];
    elseif (isset($resp['id_version'])) $idNuevaVersion = (int)$resp['id_version'];
  }

  $msg = $idNuevaVersion > 0
    ? "Monitoreo actualizado. Nueva versión: {$idNuevaVersion}"
    : "Monitoreo actualizado.";

  redirect_with_msg("../vistas_pantallas/listado_monitoreos.php", 1, $msg);

} catch (Throwable $e) {
  redirect_with_msg("../vistas_pantallas/corregir_monitoreo.php?id={$idVersionBase}", 0, "Error al guardar corrección: ".$e->getMessage());
}
