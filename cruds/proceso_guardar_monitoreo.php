<?php
/**
 * /cruds/proceso_guardar_monitoreo.php
 * =========================================================
 * PROCESO: Guardar Monitoreo (BLINDADO)
 *
 * ✅ Gestor SIEMPRE desde SESSION (no POST)
 * ✅ Valida sesión y permiso can_create()
 * ✅ Anti-manipulación:
 *    - valida que Cola exista y esté vigente
 *    - valida que Agente exista y esté activo
 *    - si usuario NO ve todo: valida que cola/agente sean de SU área
 *
 * ✅ Mantiene llamada al SP dbo.PR_CREAR_MONITOREO (17 params)
 */
require_once __DIR__ . '/../config_ajustes/app.php';

require_once BASE_PATH . '/config_ajustes/conectar_db.php';


/* =========================================================
 * 0) SEGURIDAD / SESIÓN
 * ========================================================= */
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_URL . '/index.php');
  exit;
}

/* ✅ Permiso centralizado: CREAR */
if (!function_exists('can_create') || !can_create()) {
  http_response_code(403);
  exit('No tiene permisos para crear monitoreos.');
}

/* =========================================================
 * HELPERS
 * ========================================================= */

/** ISO 8601 -> "YYYY-MM-DD HH:MM:SS" (DATETIME2(0)) */
function isoToSqlDateTime2(?string $iso): ?string {
  if (!$iso) return null;
  $iso = trim($iso);
  if ($iso === '') return null;

  $iso = str_replace('Z', '', $iso);
  $iso = preg_replace('/\.\d+$/', '', $iso);
  $iso = str_replace('T', ' ', $iso);

  if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $iso)) return null;
  return $iso;
}

/** "YYYY-MM-DD" + "HH:MM(:SS)" -> "YYYY-MM-DD HH:MM:SS" */
function combineDateAndTime(?string $date, ?string $time): ?string {
  $date = $date ? trim($date) : '';
  $time = $time ? trim($time) : '';
  if ($date === '' || $time === '') return null;

  if (preg_match('/^\d{2}:\d{2}$/', $time)) $time .= ':00';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
  if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) return null;

  return $date . ' ' . $time;
}

/** IdMedio -> Texto (prioriza POST si viene) */
function medioTexto(int $idMedio, ?string $txtPost): ?string {
  $txtPost = $txtPost ? trim($txtPost) : '';
  if ($txtPost !== '') return $txtPost;

  return match ($idMedio) {
    1 => 'Teléfono',
    2 => 'Chat',
    3 => 'WhatsApp',
    default => null,
  };
}

/** Gestor desde SESSION */
function gestorDesdeSesion(): string {
  $v = $_SESSION['nombre_completo'] ?? ($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? ''));
  $v = trim((string)$v);
  if ($v === '') $v = 'SIN_NOMBRE_SESION';
  return $v;
}

try {
  /* =========================================================
   * 1) Auditor desde sesión
   * ========================================================= */
  $idAuditor = (int)($_SESSION['id_usuario'] ?? 0);
  if ($idAuditor <= 0) throw new Exception('Sesión inválida: no se encontró id_usuario.');

  $idRol = (int)($_SESSION['id_rol'] ?? 0);
  $idAreaSesion = (int)($_SESSION['id_area'] ?? 0);

  $veTodo = function_exists('can_see_all_areas') ? can_see_all_areas() : false;

  /* =========================================================
   * 2) Validación obligatorios mínimos
   * ========================================================= */
  if (
    empty($_POST['IdAgente']) ||
    empty($_POST['IdCola'])   ||
    empty($_POST['IdMedio'])  ||
    empty($_POST['JsonRespuestas'])
  ) {
    throw new Exception('Faltan datos obligatorios para registrar el monitoreo.');
  }

  $idAgente = (int)$_POST['IdAgente'];
  $idCola   = (int)$_POST['IdCola'];
  $idMedio  = (int)$_POST['IdMedio'];
  $jsonRes  = (string)$_POST['JsonRespuestas'];

  /* =========================================================
   * 3) Validar JSON
   * ========================================================= */
  $decoded = json_decode($jsonRes, true);
  if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || count($decoded) === 0) {
    throw new Exception('El formato de respuestas es inválido.');
  }
  foreach ($decoded as $it) {
    if (!isset($it['id_pregunta'], $it['respuesta'])) {
      throw new Exception('JSON inválido: faltan campos en una respuesta.');
    }
  }

  /* =========================================================
   * 4) ✅ Anti-manipulación (cola/agente/área)
   * ========================================================= */

  // 4.1) Cola: debe existir y estar vigente
  $sqlCola = "SELECT TOP 1 id_cola, id_area, nombre_cola
              FROM dbo.COLAS
              WHERE id_cola = ?
                AND fecha_fin IS NULL";
  $stCola = $conexion->prepare($sqlCola);
  $stCola->execute([$idCola]);
  $colaRow = $stCola->fetch(PDO::FETCH_ASSOC);

  if (!$colaRow) {
    throw new Exception('Cola inválida o no vigente.');
  }
  $idAreaCola = (int)$colaRow['id_area'];

  // 4.2) Agente: debe existir y estar activo
  $sqlAg = "SELECT TOP 1 id_agente_int, id_area, nombre_agente
            FROM dbo.AGENTES
            WHERE id_agente_int = ?
              AND ISNULL(estado,1)=1";
  $stAg = $conexion->prepare($sqlAg);
  $stAg->execute([$idAgente]);
  $agRow = $stAg->fetch(PDO::FETCH_ASSOC);

  if (!$agRow) {
    throw new Exception('Agente inválido o inactivo.');
  }
  $idAreaAgente = (int)$agRow['id_area'];

  // 4.3) Si NO ve todo: forzar área sesión
  if (!$veTodo) {
    if ($idAreaSesion <= 0) throw new Exception('Sesión inválida: sin área asignada.');
    if ($idAreaCola !== $idAreaSesion) throw new Exception('No puede registrar monitoreos en colas de otra área.');
    if ($idAreaAgente !== $idAreaSesion) throw new Exception('No puede registrar monitoreos para agentes de otra área.');
  } else {
    // Si ve todo, igual evitamos inconsistencias:
    // Cola y Agente deberían pertenecer a la misma área (si tu negocio lo exige)
    if ($idAreaCola !== $idAreaAgente) {
      throw new Exception('Inconsistencia: el agente no pertenece al área de la cola.');
    }
  }

  /* =========================================================
   * 5) Capturar campos extra
   * ========================================================= */
  $idInteraccion = isset($_POST['id_interaccion']) ? trim((string)$_POST['id_interaccion']) : null;
  if ($idInteraccion === '') $idInteraccion = null;

  $fechaInteraccion = isset($_POST['fecha_interaccion']) ? trim((string)$_POST['fecha_interaccion']) : null;
  if ($fechaInteraccion === '') $fechaInteraccion = null;

  $horaInteraccion  = isset($_POST['hora_interaccion']) ? trim((string)$_POST['hora_interaccion']) : null;
  if ($horaInteraccion === '') $horaInteraccion = null;

  $fechaHoraInteraccion = combineDateAndTime($fechaInteraccion, $horaInteraccion);

  $duracionInteraccionSeg = null;
  if (isset($_POST['duracion_interaccion_segundos']) && $_POST['duracion_interaccion_segundos'] !== '') {
    $duracionInteraccionSeg = (int)$_POST['duracion_interaccion_segundos'];
    if ($duracionInteraccionSeg < 0) $duracionInteraccionSeg = 0;
  }

  $medioDeAtencion = medioTexto($idMedio, $_POST['MedioDeAtencion'] ?? null);

  $tipoMonitoreo = isset($_POST['TipoMonitoreo']) ? trim((string)$_POST['TipoMonitoreo']) : null;
  if ($tipoMonitoreo === '') $tipoMonitoreo = null;

  $numeroContrato = isset($_POST['numero_contrato']) ? trim((string)$_POST['numero_contrato']) : null;
  if ($numeroContrato === '') $numeroContrato = null;

  $numeroContacto = isset($_POST['numero_contacto']) ? trim((string)$_POST['numero_contacto']) : null;
  if ($numeroContacto === '') $numeroContacto = null;

  $linkEvidencia = isset($_POST['link_evidencia']) ? trim((string)$_POST['link_evidencia']) : null;
  if ($linkEvidencia === '') $linkEvidencia = null;

  $descripcionFinal = isset($_POST['DescripcionFinal']) ? trim((string)$_POST['DescripcionFinal']) : null;
  if ($descripcionFinal === '') $descripcionFinal = null;

  $gestorMonitoreo = gestorDesdeSesion();

  /* =========================================================
   * 6) Tiempos de monitoreo (ts_inicio/ts_fin)
   * ========================================================= */
  $tsInicioISO = $_POST['ts_inicio'] ?? null;
  $tsFinISO    = $_POST['ts_fin'] ?? null;

  $horaInicioMonSql = isoToSqlDateTime2(is_string($tsInicioISO) ? $tsInicioISO : null);
  $horaFinMonSql    = isoToSqlDateTime2(is_string($tsFinISO) ? $tsFinISO : null);

  $duracionMonSeg = null;
  if (isset($_POST['duracion_segundos']) && $_POST['duracion_segundos'] !== '') {
    $duracionMonSeg = (int)$_POST['duracion_segundos'];
    if ($duracionMonSeg < 0) $duracionMonSeg = 0;
  }

  /* =========================================================
   * 7) Ejecutar SP dbo.PR_CREAR_MONITOREO (17 params)
   * ========================================================= */
  $sql = "{CALL dbo.PR_CREAR_MONITOREO(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
  $stmt = $conexion->prepare($sql);

  $i = 1;
  $stmt->bindValue($i++, $idAgente,  PDO::PARAM_INT);
  $stmt->bindValue($i++, $idCola,    PDO::PARAM_INT);
  $stmt->bindValue($i++, $idAuditor, PDO::PARAM_INT);
  $stmt->bindValue($i++, $idMedio,   PDO::PARAM_INT);
  $stmt->bindValue($i++, $jsonRes,   PDO::PARAM_STR);

  $stmt->bindValue($i++, $idInteraccion,          $idInteraccion ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $fechaHoraInteraccion,   $fechaHoraInteraccion ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $duracionInteraccionSeg, ($duracionInteraccionSeg !== null) ? PDO::PARAM_INT : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $medioDeAtencion,        $medioDeAtencion ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $tipoMonitoreo,          $tipoMonitoreo ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $numeroContrato,         $numeroContrato ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $numeroContacto,         $numeroContacto ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $linkEvidencia,          $linkEvidencia ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $descripcionFinal,       $descripcionFinal ? PDO::PARAM_STR : PDO::PARAM_NULL);

  $stmt->bindValue($i++, $gestorMonitoreo,        $gestorMonitoreo ? PDO::PARAM_STR : PDO::PARAM_NULL);

  $stmt->bindValue($i++, $horaInicioMonSql,       $horaInicioMonSql ? PDO::PARAM_STR : PDO::PARAM_NULL);
  $stmt->bindValue($i++, $horaFinMonSql,          $horaFinMonSql ? PDO::PARAM_STR : PDO::PARAM_NULL);

  $stmt->execute();

  $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
  $idVersion = $row['id_version'] ?? null;

  /* =========================================================
   * 8) (Opcional) actualizar duración en VERSION
   * ========================================================= */
  if ($idVersion && $duracionMonSeg !== null) {
    $qUpd = $conexion->prepare("
      UPDATE dbo.MONITOREO_VERSION
      SET duracion_segundos = ?
      WHERE id_version = ?
    ");
    $qUpd->execute([$duracionMonSeg, (int)$idVersion]);
  }

  /* =========================================================
   * 9) ÉXITO
   * ========================================================= */
  header('Location: ../index.php?monitoreo=ok');
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo "
  <div style='
      max-width:760px;
      margin:40px auto;
      font-family:Segoe UI, Arial;
      background:#fff5f5;
      border-left:6px solid #dc3545;
      padding:20px;
      border-radius:12px;
      box-shadow:0 6px 18px rgba(0,0,0,.08);
  '>
      <h3 style='color:#dc3545;margin-top:0'>❌ Error al guardar el monitoreo</h3>
      <p><strong>Detalle técnico:</strong></p>
      <pre style='white-space:pre-wrap; background:#fff; border:1px solid #f1c6c6; padding:12px; border-radius:10px;'>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>
      <div style='margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;'>
        <a style='display:inline-block' href='javascript:history.back()'>⬅ Volver</a>
      </div>
  </div>";
}
