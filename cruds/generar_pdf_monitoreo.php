<?php
/**
 * =========================================================
 * /cruds/generar_pdf_monitoreo.php
 * ---------------------------------------------------------
 * ✅ Genera SIEMPRE el PDF VIGENTE (última versión REAL)
 * ✅ Etiqueta ORIGINAL / CORREGIDO
 * ✅ Trazabilidad: auditor original vs quien corrigió + fechas
 * ✅ Bloques: Sin cambios vs Modificadas (antes/ahora)
 *
 * SP usado: dbo.PR_GET_MONITOREO_DETALLE (@IdVersion)
 * Devuelve: CABECERA (1) + DETALLE (N) de la versión vigente real
 * =========================================================
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';
require_once BASE_PATH . '/libs/dompdf/autoload.inc.php';


use Dompdf\Dompdf;

/* =========================================================
 * 1) SEGURIDAD
 * ========================================================= */
require_login();
force_password_change();

/* =========================================================
 * 2) HELPER HTML SAFE
 * ========================================================= */
if (!function_exists('h')) {
  function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
  }
}

/* =========================================================
 * 3) LEER ID (ACEPTA id o id_version)
 * ========================================================= */
$idVersion = 0;
if (isset($_GET['id'])) {
  $idVersion = (int)$_GET['id'];
} elseif (isset($_GET['id_version'])) {
  $idVersion = (int)$_GET['id_version'];
}

if ($idVersion <= 0) {
  http_response_code(400);
  exit('ID de monitoreo inválido');
}

/* =========================================================
 * 4) ENFORCE DE ÁREA (BACKEND)
 * - Si el rol NO ve todo: solo puede acceder a monitoreos de su área
 * - Se valida contra el ORIGEN y su última versión (vigente real)
 * ========================================================= */
$veTodo = can_see_all_areas();
$idAreaSesion = (int)($_SESSION['id_area'] ?? 0);

if (!$veTodo) {
  if ($idAreaSesion <= 0) {
    http_response_code(403);
    exit('Acceso denegado: usuario sin área asignada.');
  }

  try {
    // 1) Obtener id_origen desde la versión solicitada
    $stO = $conexion->prepare("
      SELECT TOP 1 id_origen
      FROM dbo.MONITOREO_VERSION
      WHERE id_version = ? AND estado_version <> 'ELIMINADO'
    ");
    $stO->execute([$idVersion]);
    $idOrigen = (int)($stO->fetchColumn() ?: 0);

    if ($idOrigen <= 0) {
      http_response_code(404);
      exit('No se encontró el monitoreo.');
    }

    // 2) Tomar el id_area de la ÚLTIMA versión del origen (vigente real)
    $stA = $conexion->prepare("
      SELECT TOP 1 id_area
      FROM dbo.MONITOREO_VERSION
      WHERE id_origen = ? AND estado_version <> 'ELIMINADO'
      ORDER BY numero_version DESC, id_version DESC
    ");
    $stA->execute([$idOrigen]);
    $idAreaDb = (int)($stA->fetchColumn() ?: 0);

    if ($idAreaDb <= 0 || $idAreaDb !== $idAreaSesion) {
      http_response_code(403);
      exit('Acceso denegado: este monitoreo no pertenece a tu área.');
    }

  } catch (Throwable $e) {
    http_response_code(500);
    exit('Error validando acceso al monitoreo.');
  }
}

/* =========================================================
 * 5) EJECUTAR SP (2 RESULTSETS)
 * ========================================================= */
try {
  $stmt = $conexion->prepare("EXEC dbo.PR_GET_MONITOREO_DETALLE :IdVersion");
  $stmt->bindValue(':IdVersion', $idVersion, PDO::PARAM_INT);
  $stmt->execute();

  // CABECERA
  $header = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$header) {
    http_response_code(404);
    exit('No se encontró información del monitoreo');
  }

  // DETALLE
  $stmt->nextRowset();
  $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  http_response_code(500);
  exit('Error al generar PDF.');
}

/* =========================================================
 * 6) MAPEO CABECERA
 * ========================================================= */
$referencia        = $header['referencia'] ?? '-';
$fechaLlamada      = $header['fecha_llamada'] ?? '-';
$horaLlamada       = $header['hora_llamada'] ?? '';
$duracionLlamada   = $header['duracion_llamada'] ?? '-';

$fechaMonitoreo    = $header['fecha_monitoreo'] ?? '-';
$duracionMonitoreo = $header['duracion_monitoreo'] ?? '-';

$area              = $header['area'] ?? '-';
$cola              = $header['cola'] ?? '-';
$agente            = $header['agente'] ?? '-';

$tipoMonitoreo     = $header['tipo_monitoreo'] ?? '-';

$puntajeObtenido   = $header['puntaje_obtenido'] ?? null;
$puntajePosible    = $header['puntaje_posible'] ?? null;

$porcentaje        = (float)($header['porcentaje_resultado'] ?? 0);
$estadoFinal       = $header['estado_final'] ?? '-';

$linkEvidencia     = trim((string)($header['link_evidencia'] ?? ''));
$observaciones     = trim((string)($header['descripcion_final'] ?? ''));
if ($observaciones === '') $observaciones = 'Sin observaciones';

/* === Trazabilidad === */
$auditorOriginal   = trim((string)($header['auditor'] ?? ''));
if ($auditorOriginal === '') $auditorOriginal = trim((string)($header['gestor_monitoreo'] ?? '-'));

$gestorMonitoreo   = trim((string)($header['gestor_monitoreo'] ?? '-'));

$esCorregido       = ((int)($header['es_corregido'] ?? 0) === 1);
$etiquetaEstado    = (string)($header['etiqueta_estado'] ?? ($esCorregido ? 'CORREGIDO' : 'ORIGINAL'));

$motivoCorreccion  = trim((string)($header['motivo_correccion'] ?? ''));
$fechaCorreccion   = $header['fecha_correccion'] ?? null;
$corregidoPorNombre= trim((string)($header['corregido_por_nombre'] ?? ''));

$fechaCorreccionSolo = '-';
if (!empty($fechaCorreccion)) {
  $fechaCorreccionSolo = substr((string)$fechaCorreccion, 0, 10);
}

/* =========================================================
 * 7) SEPARAR DETALLE EN 2 BLOQUES
 * ========================================================= */
$detalleSinCambios = [];
$detalleModificadas = [];

foreach ($detalle as $d) {
  $estado = strtoupper((string)($d['estado_pregunta'] ?? ''));

  if (in_array($estado, ['CORREGIDA','NUEVA'], true)) {
    $detalleModificadas[] = $d;
  } else {
    $detalleSinCambios[] = $d;
  }
}

/* =========================================================
 * 8) HTML PDF
 * ========================================================= */
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  body{ font-family: DejaVu Sans, sans-serif; font-size:11px; color:#111; }
  .wrap{ width:100%; }
  .title{ font-size:16px; font-weight:700; margin:0; }
  .subtitle{ margin:3px 0 0 0; color:#555; font-size:10px; }

  .pill{
    display:inline-block; padding:5px 10px; border-radius:14px;
    font-size:10px; font-weight:700; border:1px solid #1f2937;
  }
  .pill-ok{ background:#eaf7ef; }
  .pill-warn{ background:#fff4d6; }

  .card{
    border:1px solid #1f2937; border-radius:10px; padding:10px; margin-top:10px;
  }
  .kv{ width:100%; border-collapse: collapse; }
  .kv td{ border:none; padding:3px 0; vertical-align:top; }
  .muted{ color:#555; }
  .hr{ border-top:1px solid #1f2937; margin:10px 0; }

  table.tbl{ width:100%; border-collapse: collapse; }
  table.tbl th, table.tbl td{ border:1px solid #1f2937; padding:6px; font-size:10px; vertical-align: top; }
  table.tbl th{ background:#f3f4f6; text-align:center; }

  .tag{
    display:inline-block; padding:2px 6px; border-radius:10px; font-size:9px; border:1px solid #aaa;
  }
  .tag-new{ background:#e8f0ff; }
  .tag-chg{ background:#fff0f0; }
  .right{ text-align:right; }
  .center{ text-align:center; }
  .small{ font-size:9px; }
</style>
</head>
<body>
<div class="wrap">

  <table style="width:100%; border-collapse:collapse;">
    <tr>
      <td style="width:70%;">
        <p class="title">Detalle de Monitoreo</p>
        <p class="subtitle"></p>
      </td>
      <td style="width:30%; text-align:right;">
        <span class="pill <?= $esCorregido ? 'pill-warn' : 'pill-ok' ?>"><?= h($etiquetaEstado) ?></span>
      </td>
    </tr>
  </table>

  <div class="card">
    <table class="kv">
      <tr><td><b>Id ticket / Interacción:</b> <?= h($referencia) ?></td></tr>
      <tr>
        <td><b>Fecha de la interacción:</b> <?= h($fechaLlamada) ?><?= $horaLlamada ? ' '.h($horaLlamada) : '' ?></td>
      </tr>
      <tr><td><b>Fecha del monitoreo:</b> <?= h($fechaMonitoreo) ?></td></tr>
      <tr><td><b>Duración llamada:</b> <?= h($duracionLlamada) ?> <span class="muted">(Monitoreo: <?= h($duracionMonitoreo) ?>)</span></td></tr>
      <tr><td><b>Área:</b> <?= h($area) ?> <span class="muted">/ Cola: <?= h($cola) ?></span></td></tr>
      <tr><td><b>Agente:</b> <?= h($agente) ?></td></tr>

      <tr><td><b>Gestor de Monitoreo:</b> <?= h($auditorOriginal) ?></td></tr>
      <tr><td><b>Gestor de Monitoreo ("Corrección"):</b> <?= h($gestorMonitoreo) ?></td></tr>

      <tr><td><b>Tipo monitoreo:</b> <?= h($tipoMonitoreo) ?></td></tr>

      <tr>
        <td>
          <b>Resultado:</b> <?= number_format($porcentaje, 1) ?>% (<?= h($estadoFinal) ?>)
          <?php if ($puntajeObtenido !== null && $puntajePosible !== null): ?>
            <span class="muted">— Puntaje: <?= h($puntajeObtenido) ?> / <?= h($puntajePosible) ?></span>
          <?php endif; ?>
        </td>
      </tr>

      <?php if ($esCorregido): ?>
        <tr>
          <td>
            <b>Corrección:</b> <?= h($fechaCorreccionSolo) ?>
            <?php if ($corregidoPorNombre !== ''): ?>
              <span class="muted">por <?= h($corregidoPorNombre) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($motivoCorreccion !== ''): ?>
          <tr><td class="small muted"><b>Motivo:</b> <?= h($motivoCorreccion) ?></td></tr>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($linkEvidencia !== ''): ?>
        <tr><td class="small muted"><b>Link de la interacción:</b> <?= h($linkEvidencia) ?></td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="hr"></div>

  <?php if ($esCorregido && count($detalleModificadas) > 0): ?>
    <p style="margin:0; font-weight:700;">Preguntas modificadas en la corrección</p>
    <p class="small muted" style="margin:3px 0 8px 0;">Incluye CORREGIDAS (cambió respuesta) y NUEVAS (no existían antes).</p>

    <table class="tbl">
      <thead>
        <tr>
          <th style="width:28px;">#</th>
          <th>Pregunta</th>
          <th style="width:120px;">Gestión / Tipo</th>
          <th style="width:90px;">Antes</th>
          <th style="width:90px;">Ahora</th>
          <th style="width:60px;">Pts</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($detalleModificadas as $i => $d): ?>
        <?php
          $estadoP = strtoupper((string)($d['estado_pregunta'] ?? ''));
          $tagClass = ($estadoP === 'NUEVA') ? 'tag-new' : 'tag-chg';
        ?>
        <tr>
          <td class="center"><?= (int)$i + 1 ?></td>
          <td>
            <span class="tag <?= $tagClass ?>"><?= h($estadoP) ?></span>
            &nbsp;<?= h($d['pregunta'] ?? '-') ?>
            <?php if (!empty($d['seccion'])): ?>
              <div class="small muted">Sección: <?= h($d['seccion']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= h(($d['gestion'] ?? '-') . ' / ' . ($d['tipo_regla'] ?? '-')) ?></td>
          <td class="center"><?= h($d['respuesta_anterior'] ?? '-') ?></td>
          <td class="center"><b><?= h($d['respuesta_actual'] ?? '-') ?></b></td>
          <td class="right"><?= h($d['puntaje_actual'] ?? '0') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="hr"></div>
  <?php endif; ?>

  <p style="margin:0; font-weight:700;">Preguntas sin cambios (base)</p>
  <p class="small muted" style="margin:3px 0 8px 0;">Incluye ORIGINAL / SIN_CAMBIO.</p>

  <table class="tbl">
    <thead>
      <tr>
        <th style="width:28px;">#</th>
        <th>Pregunta</th>
        <th style="width:120px;">Gestión / Tipo</th>
        <th style="width:80px;">Respuesta</th>
        <th style="width:60px;">Pts</th>
      </tr>
    </thead>
    <tbody>
    <?php if (count($detalleSinCambios) === 0): ?>
      <tr><td colspan="5" class="center muted">Sin detalle</td></tr>
    <?php else: ?>
      <?php foreach ($detalleSinCambios as $i => $d): ?>
        <tr>
          <td class="center"><?= (int)$i + 1 ?></td>
          <td>
            <?= h($d['pregunta'] ?? '-') ?>
            <?php if (!empty($d['seccion'])): ?>
              <div class="small muted">Sección: <?= h($d['seccion']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= h(($d['gestion'] ?? '-') . ' / ' . ($d['tipo_regla'] ?? '-')) ?></td>
          <td class="center"><?= h($d['respuesta_actual'] ?? '-') ?></td>
          <td class="right"><?= h($d['puntaje_actual'] ?? '0') ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <div class="card" style="margin-top:10px;">
    <b>Observaciones</b><br>
    <?= nl2br(h($observaciones)) ?>
  </div>

</div>
</body>
</html>
<?php
$html = ob_get_clean();

/* =========================================================
 * 9) GENERAR PDF (DOMPDF)
 * ========================================================= */
try {
  $dompdf = new Dompdf([
    'isRemoteEnabled' => true,
    'defaultFont'     => 'DejaVu Sans',
  ]);

  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();

  // Headers seguros y compatibles con nginx + Azure
  header('Content-Type: application/pdf');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');

  $dompdf->stream("monitoreo_{$idVersion}.pdf", ['Attachment' => false]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  exit('Error al renderizar PDF.');
}
