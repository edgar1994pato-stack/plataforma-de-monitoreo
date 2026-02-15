<?php
/**
 * /vistas_pantallas/corregir_monitoreo.php
 * ✅ 100% funcional con tu stack actual (diseño_unificado + servidor_filtros.php)
 *
 * OBJETIVO:
 * - Mostrar la “foto” del monitoreo
 * - Permitir CORREGIR creando NUEVA versión (POST a proceso_corregir_monitoreo.php)
 * - Motivo de corrección OBLIGATORIO
 * - ÁREA / COLA / AGENTE se mantienen (solo lectura)
 *
 * ENTRADA:
 * - listado_monitoreos.php envía ?id=<id_version>
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

/* ============================================================
   1) SEGURIDAD CENTRALIZADA
   ============================================================ */
require_login();
force_password_change();

/* ============================================================
   2) HELPERS
   ============================================================ */
function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function abort_msg($msg, $code = 400) {
  http_response_code($code);
  echo "<div style='padding:16px;font-family:Arial'>".h($msg)."</div>";
  exit;
}

/* ============================================================
   3) BASE URL
   ============================================================ */
$BASE_URL = BASE_URL;

/* ============================================================
   4) SESIÓN / PERMISOS (SOLO DESDE seguridad.php)
   ============================================================ */
$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
$idRol     = (int)($_SESSION['id_rol'] ?? 0);
$idAreaSes = (int)($_SESSION['id_area'] ?? 0);

$puedeCorregir = can_correct();      // centralizado
$soloLectura   = is_readonly();      // centralizado
$veTodo        = can_see_all_areas();// centralizado

/* ============================================================
   5) ID ENTRADA
   ============================================================ */
$idVersion = (int)($_GET['id'] ?? 0);
if ($idVersion <= 0) abort_msg('Parámetro inválido: id.');

/* ============================================================
   6) CARGA DE DATOS BASE
   ============================================================ */
try {
  // Traer versión base
  $sqlV = "
    SELECT TOP 1
      v.id_version,
      v.id_origen,
      v.numero_version,
      v.estado_version,
      v.id_area,
      v.id_cola,
      v.id_medio,
      v.id_agente,

      v.gestor_monitoreo,
      v.id_interaccion,
      v.numero_contrato,
      v.numero_contacto,
      v.link_evidencia,
      v.descripcion_final,
      v.fecha_interaccion,

      v.hora_inicio,
      v.hora_fin,
      v.duracion_segundos,

      v.porcentaje_resultado,
      v.estado_final
    FROM dbo.MONITOREO_VERSION v
    WHERE v.id_version = ?
      AND v.estado_version <> 'ELIMINADO'
  ";
  $stV = $conexion->prepare($sqlV);
  $stV->execute([$idVersion]);
  $version = $stV->fetch(PDO::FETCH_ASSOC);

  if (!$version) abort_msg('No se encontró el monitoreo (id_version) o está eliminado.', 404);

  $idOrigen      = (int)$version['id_origen'];
  $idVersionBase = (int)$version['id_version'];

  $idArea   = (int)$version['id_area'];
  $idCola   = (int)$version['id_cola'];
  $idMedio  = (int)$version['id_medio'];
  $idAgente = (int)$version['id_agente'];

  /* ============================================================
     6.1) ENFORCE DE ÁREA (SEGURIDAD BACKEND)
     - Si no ve todo, solo puede trabajar con su id_area
     ============================================================ */
  if (!$veTodo) {
    if ($idAreaSes <= 0) abort_msg('Tu usuario no tiene un área asignada. Contacta al administrador.', 403);

    if ($idArea !== $idAreaSes) {
      abort_msg('Acceso denegado: este monitoreo no pertenece a tu área.', 403);
    }
  }

  // Traer nombres para mostrar
  $stA = $conexion->prepare("SELECT TOP 1 nombre_area FROM dbo.AREAS WHERE id_area=?");
  $stA->execute([$idArea]);
  $areaNombre = (string)($stA->fetchColumn() ?: '');

  $stC = $conexion->prepare("SELECT TOP 1 nombre_cola FROM dbo.COLAS WHERE id_cola=?");
  $stC->execute([$idCola]);
  $colaNombre = (string)($stC->fetchColumn() ?: '');

  $stM = $conexion->prepare("SELECT TOP 1 nombre_medio FROM dbo.MEDIOS_ATENCION WHERE id_medio=?");
  $stM->execute([$idMedio]);
  $medioNombre = (string)($stM->fetchColumn() ?: '');

  $stG = $conexion->prepare("SELECT TOP 1 nombre_agente FROM dbo.AGENTES WHERE id_agente_int=?");
  $stG->execute([$idAgente]);
  $agenteNombre = (string)($stG->fetchColumn() ?: '');

  // Traer cabecera ORIGEN
  $sqlO = "
    SELECT TOP 1
      id_origen,
      fecha_hora_interaccion,
      duracion_interaccion_segundos,
      medio_de_atencion,
      tipo_monitoreo
    FROM dbo.MONITOREO_ORIGEN
    WHERE id_origen = ?
  ";
  $stO = $conexion->prepare($sqlO);
  $stO->execute([$idOrigen]);
  $origen = $stO->fetch(PDO::FETCH_ASSOC) ?: [];

  $fechaHoraInteraccion = (string)($origen['fecha_hora_interaccion'] ?? '');
  $durInterSeg = (int)($origen['duracion_interaccion_segundos'] ?? 0);

  $medioTxt = (string)($origen['medio_de_atencion'] ?? $medioNombre);
  $tipoMon  = (string)($origen['tipo_monitoreo'] ?? ($version['tipo_monitoreo'] ?? ''));

  // Si no hay fechaHoraInteraccion en ORIGEN, construimos desde VERSION
  $fechaInteraccion = (string)($version['fecha_interaccion'] ?? '');
  if ($fechaHoraInteraccion === '' && $fechaInteraccion !== '') {
    $fechaHoraInteraccion = $fechaInteraccion . ' 00:00:00';
  }

  // Partir fecha/hora para inputs
  $fechaInter = '';
  $horaInter  = '';
  if ($fechaHoraInteraccion !== '') {
    $ts = strtotime($fechaHoraInteraccion);
    if ($ts) {
      $fechaInter = date('Y-m-d', $ts);
      $horaInter  = date('H:i', $ts);
    }
  }

  if ($fechaInter === '') $fechaInter = date('Y-m-d');
  if ($horaInter  === '') $horaInter  = date('H:i');

  // Respuestas anteriores
  $sqlD = "
    SELECT id_pregunta, UPPER(respuesta) AS respuesta
    FROM dbo.MONITOREO_DETALLE
    WHERE id_version = ?
  ";
  $stD = $conexion->prepare($sqlD);
  $stD->execute([$idVersionBase]);
  $det = $stD->fetchAll(PDO::FETCH_ASSOC);

  $mapRespuestas = [];
  foreach ($det as $row) {
    $pid = (int)$row['id_pregunta'];
    $resp = (string)$row['respuesta'];
    if ($pid > 0 && $resp !== '') $mapRespuestas[$pid] = $resp;
  }

} catch (Throwable $e) {
  abort_msg('Error cargando monitoreo: '.$e->getMessage(), 500);
}

/* ============================================================
   7) AUDITOR (quien corrige) desde sesión
   ============================================================ */
$idAuditor = (int)($_SESSION['id_usuario'] ?? 0);
$nombreGestorSesion = trim((string)($_SESSION['nombre_completo'] ?? ''));
if ($nombreGestorSesion === '') $nombreGestorSesion = 'SIN_NOMBRE_SESION';

$READONLY_ATTR = ($soloLectura || !$puedeCorregir) ? 'disabled' : '';

/* ============================================================
   8) HEADER / TOPBAR
   ============================================================ */
$PAGE_TITLE    = "✏️ Corregir Monitoreo";
$PAGE_SUBTITLE = "Origen: <b>".h($idOrigen)."</b> | Versión base: <b>#".h((int)$version['numero_version'])."</b> | Estado: <b>".h((string)($version['estado_final'] ?? ''))."</b>";

$urlVolver = $BASE_URL . "/vistas_pantallas/listado_monitoreos.php";
$urlPdf    = $BASE_URL . "/cruds/generar_pdf_monitoreo.php?id=" . $idVersionBase . "&t=" . time();

$PAGE_ACTION_HTML = '
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a class="btn btn-outline-secondary btn-sm shadow-sm" href="'.h($urlVolver).'">
      <i class="bi bi-arrow-left"></i> Volver
    </a>

    <a class="btn btn-outline-danger btn-sm shadow-sm" href="'.h($urlPdf).'" target="_blank" rel="noopener">
      <i class="bi bi-file-earmark-pdf"></i> PDF (base)
    </a>

    <a class="btn btn-outline-primary btn-sm shadow-sm" href="'.h($BASE_URL).'/vistas_pantallas/menu.php">
      <i class="bi bi-house-door"></i> Inicio
    </a>

    <a class="btn btn-outline-danger btn-sm shadow-sm" href="'.h($BASE_URL).'/cruds/logout.php">
      <i class="bi bi-box-arrow-right"></i> Cerrar sesión
    </a>

    <div id="qaChip" class="qa-chip qa-chip-warn" style="display:none;" title="Seleccione respuestas para ver el avance.">
      <div class="qa-chip-top">
        <div class="qa-chip-title">
          <span class="qa-chip-icon" id="qaChipIcon">⚠️</span>
          <span id="qaChipEstado">En proceso</span>
        </div>
        <div class="qa-chip-mid">
          <span class="qa-chip-score" id="qaChipScore">0.0</span>
          <span class="qa-chip-unit">%</span>
        </div>
      </div>
      <div class="qa-chip-right">
        <div class="qa-chip-meta">Umbral: <b id="qaChipUmbral">60</b>%</div>
      </div>
    </div>

    <button id="btnReset" type="button" class="btn btn-outline-secondary btn-sm shadow-sm">
      <i class="bi bi-arrow-counterclockwise"></i> Reiniciar
    </button>
  </div>
';

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';


?>

<div id="alertBox" class="alert d-none" role="alert"></div>

<?php if (!$puedeCorregir): ?>
  <div class="alert alert-danger">
    <i class="bi bi-shield-x me-1"></i>
    No tiene permisos para corregir monitoreos.
  </div>
  <?php require_once '../includes_partes_fijas/diseno_abajo.php'; exit; ?>
<?php endif; ?>

<?php if ($soloLectura): ?>
  <div class="alert alert-warning">
    <i class="bi bi-eye me-1"></i>
    Está en modo <b>solo lectura</b>. No podrá guardar correcciones.
  </div>
<?php endif; ?>

<form id="formCorreccion" method="POST" action="<?= h($BASE_URL) ?>/cruds/proceso_corregir_monitoreo.php" novalidate>

  <!-- ===================== METADATA (Solo lectura) ===================== -->
  <div class="card card-soft mb-3">
    <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
      <div><i class="bi bi-info-circle me-2"></i>Datos base (no modificables)</div>
      <div class="small opacity-75">ID Versión base: <?= (int)$idVersionBase ?></div>
    </div>

    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">ÁREA</label>
          <input type="text" class="form-control form-control-sm" value="<?= h($areaNombre) ?>" readonly>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">PLANTILLA</label>
          <input type="text" class="form-control form-control-sm" value="<?= h($colaNombre) ?>" readonly>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">AGENTE</label>
          <input type="text" class="form-control form-control-sm" value="<?= h($agenteNombre) ?>" readonly>
        </div>

 <!--
        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">MEDIO</label>
          <input type="text" class="form-control form-control-sm" value="<?= h($medioNombre) ?>" readonly>
        </div>
-->


        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">GESTOR DE MONITOREO</label>
          <input type="text" class="form-control form-control-sm" value="<?= h($nombreGestorSesion) ?>" readonly>
          <div class="help-mini"></div>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">ESTADO / NOTA (base)</label>
          <input type="text" class="form-control form-control-sm"
                 value="<?= h((string)($version['estado_final'] ?? '')).' / '.number_format((float)($version['porcentaje_resultado'] ?? 0),1).'%' ?>"
                 readonly>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== CABECERA CORREGIBLE ===================== -->
  <div class="card card-soft mb-3">
    <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
      <div><i class="bi bi-pencil-square me-2"></i>Información de la interacción (corregible)</div>
      <div class="small opacity-75">Se guardará como nueva versión</div>
    </div>

    <div class="card-body">
      <div class="row g-3">

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">FECHA INTERACCIÓN <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="date" name="fecha_interaccion" id="fecha_interaccion"
                 class="form-control form-control-sm" value="<?= h($fechaInter) ?>" required>
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">HORA INTERACCIÓN <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="time" name="hora_interaccion" id="hora_interaccion"
                 class="form-control form-control-sm" value="<?= h($horaInter) ?>" required>
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">DURACIÓN INTERACCIÓN</label>
          <input <?= $READONLY_ATTR ?> type="time" id="duracion_interaccion_hms"
                 class="form-control form-control-sm" step="1"
                 value="<?= h(sprintf('%02d:%02d:%02d', (int)($durInterSeg/3600), (int)(($durInterSeg%3600)/60), (int)($durInterSeg%60))) ?>">
          <input type="hidden" name="duracion_interaccion_segundos" id="duracion_interaccion_segundos" value="<?= (int)$durInterSeg ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">TIPO MONITOREO <span class="text-danger">*</span></label>
          <select <?= $READONLY_ATTR ?> id="sel_tipo_monitoreo" class="form-select form-select-sm" required>
            <option value="">Seleccione...</option>
            <?php
              $opts = ['REMOTO','PRESENCIAL','FANTASMA','FOCALIZADO'];
              foreach ($opts as $op) {
                $sel = (strtoupper($tipoMon) === $op) ? 'selected' : '';
                echo "<option value='".h($op)."' {$sel}>".h($op)."</option>";
              }
            ?>
          </select>
          <input type="hidden" name="TipoMonitoreo" id="hidden_tipo_monitoreo" value="<?= h(strtoupper($tipoMon)) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">ID TICKET / INTERACCIÓN <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="text" name="id_interaccion" id="id_interaccion"
                 class="form-control form-control-sm" value="<?= h((string)$version['id_interaccion']) ?>" required>
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">NRO. CONTRATO <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="text" name="numero_contrato" id="numero_contrato"
                 class="form-control form-control-sm" value="<?= h((string)$version['numero_contrato']) ?>" required>
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">TELÉFONO CLIENTE <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="tel" name="numero_contacto" id="numero_contacto"
                 class="form-control form-control-sm" value="<?= h((string)$version['numero_contacto']) ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label small fw-bold text-muted">LINK EVIDENCIA <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="url" name="link_evidencia" id="link_evidencia"
                 class="form-control form-control-sm" value="<?= h((string)$version['link_evidencia']) ?>" required>
        </div>

              <div class="col-md-3">
  <label class="form-label small fw-bold text-muted">
    COLA <span class="text-danger">*</span>
  </label>

  <select <?= $READONLY_ATTR ?> id="sel_cola"
          class="form-select form-select-sm" required>
    <option value="">Seleccione...</option>

    <option value="1"  <?= $idCola==1?'selected':'' ?>>ATC CALL CENTER</option>
    <option value="2"  <?= $idCola==2?'selected':'' ?>>ATC APP</option>
    <option value="3"  <?= $idCola==3?'selected':'' ?>>ATC BOT ALI</option>
    <option value="4"  <?= $idCola==4?'selected':'' ?>>ATC CHAT WEB</option>
    <option value="5"  <?= $idCola==5?'selected':'' ?>>ATC REDES SOCIALES</option>
    <option value="6"  <?= $idCola==6?'selected':'' ?>>CORREO ATC</option>
    <option value="7"  <?= $idCola==7?'selected':'' ?>>FACEBOOK MURO</option>
    <option value="8"  <?= $idCola==8?'selected':'' ?>>INB EMPLEADOS</option>
    <option value="9"  <?= $idCola==9?'selected':'' ?>>ST N2 APP</option>
    <option value="10" <?= $idCola==10?'selected':'' ?>>ST N2 CALL CENTER</option>
    <option value="11" <?= $idCola==11?'selected':'' ?>>ST N2 CHAT WEB</option>
    <option value="12" <?= $idCola==12?'selected':'' ?>>ST N2 FB MURO</option>
    <option value="13" <?= $idCola==13?'selected':'' ?>>ST N2 IG MURO</option>
    <option value="14" <?= $idCola==14?'selected':'' ?>>ST N2 RS</option>
    <option value="15" <?= $idCola==15?'selected':'' ?>>FRONT PRESENCIAL</option>
    <option value="16" <?= $idCola==16?'selected':'' ?>>COBRANZA CALL</option>
    <option value="17" <?= $idCola==17?'selected':'' ?>>AGENDAMIENTO CALL</option>
  </select>

  <!-- 🔒 Lo que consume el SP -->
  <input type="hidden" name="IdMedio" id="hidden_medio" value="<?= (int)$idCola ?>">
  <input type="hidden" name="MedioDeAtencion" id="hidden_medio_texto"
         value="<?= h($colaNombre) ?>">
</div>




      </div>
    </div>
  </div>

  <!-- ===================== MOTIVO DE CORRECCIÓN ===================== -->
  <div class="card card-soft mb-3">
    <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
      <div><i class="bi bi-exclamation-diamond me-2"></i>Motivo de corrección <span class="text-danger">*</span></div>
      <div class="small opacity-75"><span id="char_motivo">0</span> / 500</div>
    </div>

    <div class="card-body">
      <textarea <?= ($soloLectura ? 'readonly' : '') ?>
        name="motivo_correccion"
        id="motivo_correccion"
        class="form-control"
        rows="2"
        placeholder="Explique claramente por qué se corrige este monitoreo..."
        maxlength="500"
        required></textarea>
      <div class="help-mini mt-1">Obligatorio. Recomendado mínimo 10 caracteres.</div>
    </div>
  </div>

    <!-- ===================== 🔎 BUSCADOR DE PREGUNTAS ===================== -->
  <div class="card card-soft mt-3">
    <div class="card-body py-2">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input id="txtBuscarPregunta" type="text" class="form-control"
               placeholder="Buscar en preguntas... (ej: wifi, saludo, contrato, metraje)">
        <button id="btnLimpiarBusqueda" class="btn btn-outline-secondary" type="button" title="Limpiar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="small text-muted mt-1">
      
      </div>
    </div>
  </div>


  <!-- ===================== PREGUNTAS (AJAX) ===================== -->
  <div id="seccion_preguntas" class="mt-3" style="min-height: 220px;">
    <div class="text-center py-5 text-muted bg-white rounded border">
      <i class="bi bi-card-checklist fs-1 opacity-25"></i>
      <p class="mt-2 mb-0">Cargando cuestionario de la cola...</p>
    </div>
  </div>

  <!-- ===================== OBSERVACIONES FINALES ===================== -->
  <div class="card card-soft mt-4 mb-4">
    <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
      <div><i class="bi bi-chat-right-text me-2"></i>Observaciones Finales <span class="text-danger">*</span></div>
      <div class="small opacity-75"><span id="char_obs">0</span> / 500</div>
    </div>

    <div class="card-body">
      <textarea <?= ($soloLectura ? 'readonly' : '') ?>
        name="DescripcionFinal"
        id="descripcion_final"
        class="form-control"
        rows="3"
        maxlength="500"
        required><?= h((string)$version['descripcion_final']) ?></textarea>
      <div class="help-mini mt-1"></div>
    </div>
  </div>

  <!-- ===================== HIDDENs PARA EL SP ===================== -->
  <input type="hidden" name="IdOrigen" value="<?= (int)$idOrigen ?>">
  <input type="hidden" name="IdVersionBase" value="<?= (int)$idVersionBase ?>">

  <input type="hidden" name="IdAuditor" value="<?= (int)$idUsuario ?>">
  <input type="hidden" name="IdCola" value="<?= (int)$idCola ?>">
  <input type="hidden" name="IdAgente" value="<?= (int)$idAgente ?>">
  <input type="hidden" name="IdMedio" value="<?= (int)$idMedio ?>">

  <input type="hidden" name="gestor_monitoreo" value="<?= h($nombreGestorSesion) ?>">
  <input type="hidden" name="JsonRespuestas" id="JsonRespuestas" value="">

  <!-- ⏱ tiempos de corrección -->
  <input type="hidden" name="ts_inicio" id="ts_inicio">
  <input type="hidden" name="ts_fin" id="ts_fin">
  <input type="hidden" name="duracion_segundos" id="duracion_segundos">
  <input type="hidden" name="hora_inicio" id="hora_inicio">
  <input type="hidden" name="hora_fin" id="hora_fin">

  <div class="text-end mb-5">
    <button <?= ($soloLectura ? 'disabled' : '') ?> id="btnSubmit" type="submit"
            class="btn btn-primary btn-lg px-5 fw-bold shadow">
      GUARDAR CORRECCIÓN
    </button>
  </div>

</form>

<?php
/* ============================================================
   SCRIPTS (sin cambios funcionales)
   ============================================================ */
ob_start();
?>
<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;
const SOLO_LECTURA = <?= json_encode($soloLectura) ?>;

const ID_COLA = <?= (int)$idCola ?>;
const RESPUESTAS_ANTERIORES = <?= json_encode($mapRespuestas, JSON_UNESCAPED_UNICODE) ?>;

const seccionPreguntas = document.getElementById('seccion_preguntas');
/* ============================================================
   🔎 BUSCADOR (FULL): cards + tab-panes + menú secciones
   - No rompe lógica de respuestas
   - Filtra secciones NO visibles (tabs) porque revisa TODOS los .pregunta-card
============================================================ */
const txtBuscarPregunta  = document.getElementById('txtBuscarPregunta');
const btnLimpiarBusqueda = document.getElementById('btnLimpiarBusqueda');

function hEscape(str){
  return (str || '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}
function normalizarTexto(s){
  return (s || '')
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g,'');
}
function ensureInfoBusqueda(){
  let info = document.getElementById('infoBusqueda');
  if(!info){
    info = document.createElement('div');
    info.id = 'infoBusqueda';
    info.className = 'alert alert-light border small mt-2 d-none';
    // lo insertamos antes del cuestionario
    const parent = seccionPreguntas?.parentElement;
    if(parent) parent.insertBefore(info, seccionPreguntas);
  }
  return info;
}

function indexarPreguntasParaBusqueda(){
  if(!seccionPreguntas) return;
  seccionPreguntas.querySelectorAll('.pregunta-card').forEach(card => {
    // cache
    card.dataset.search = normalizarTexto(card.textContent);
    // marca su pane (sección)
    const pane = card.closest('.tab-pane');
    if (pane && !pane.dataset.seccion) {
      pane.dataset.seccion = (pane.querySelector('h5')?.textContent || '').trim();
    }
  });
}

function aplicarFiltroPreguntas(){
  const qRaw = (txtBuscarPregunta?.value || '');
  const q = normalizarTexto(qRaw);

  const cards = Array.from(seccionPreguntas?.querySelectorAll('.pregunta-card') || []);
  const panes = Array.from(seccionPreguntas?.querySelectorAll('.tab-pane') || []);
  const menuLinks = Array.from(seccionPreguntas?.querySelectorAll('.list-group a.list-group-item') || []);

  const info = ensureInfoBusqueda();

  if (!cards.length){
    info.classList.add('d-none');
    return;
  }

  const countByPaneId = new Map();
  panes.forEach(p => countByPaneId.set(p.id, 0));

  let visibles = 0;

  // 1) filtra cards + cuenta por pane
  cards.forEach(card => {
    const txt = card.dataset.search || normalizarTexto(card.textContent);
    const match = !q || txt.includes(q);

    card.style.display = match ? '' : 'none';
    if(match) visibles++;

    const pane = card.closest('.tab-pane');
    if (pane) {
      const prev = countByPaneId.get(pane.id) || 0;
      countByPaneId.set(pane.id, prev + (match ? 1 : 0));
    }
  });

  // 2) panes: mostrar solo si hay match (cuando q existe)
  panes.forEach(p => {
    const cnt = countByPaneId.get(p.id) || 0;
    if(!q){
      p.style.display = '';
      p.classList.remove('d-none');
    }else{
      p.style.display = (cnt > 0) ? '' : 'none';
    }
  });

  // 3) menú: ocultar links sin match + actualizar badge
  menuLinks.forEach(a => {
    const href = a.getAttribute('href') || '';
    const paneId = href.startsWith('#') ? href.substring(1) : '';
    const cnt = (paneId && countByPaneId.has(paneId)) ? (countByPaneId.get(paneId) || 0) : 0;

    const badge = a.querySelector('.badge');
    if(badge && q){
      badge.textContent = String(cnt);
    }

    if(!q){
      a.style.display = '';
    }else{
      a.style.display = (cnt > 0) ? '' : 'none';
    }
  });

  // 4) activar primera sección con resultados (solo cuando q existe)
  if(q){
    const firstLink = menuLinks.find(a => a.style.display !== 'none');
    if(firstLink){
      menuLinks.forEach(x => x.classList.remove('active'));
      firstLink.classList.add('active');

      const target = firstLink.getAttribute('href') || '';
      panes.forEach(p => p.classList.remove('show','active'));

      const pane = seccionPreguntas.querySelector(target);
      if(pane){
        pane.classList.add('show','active');
      }
    }
  }

  // 5) info
  if(!q){
    info.classList.add('d-none');
  }else{
    info.classList.remove('d-none');
    info.innerHTML =
      `<i class="bi bi-filter me-1"></i> Mostrando <b>${visibles}</b> pregunta(s) que coinciden con: <b>${hEscape(qRaw)}</b>`;
  }
}

txtBuscarPregunta?.addEventListener('input', () => {
  aplicarFiltroPreguntas();
});

btnLimpiarBusqueda?.addEventListener('click', () => {
  if(txtBuscarPregunta) txtBuscarPregunta.value = '';
  aplicarFiltroPreguntas();
  txtBuscarPregunta?.focus();
});

const btnSubmit = document.getElementById('btnSubmit');

function hhmm(d){
  return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}
function loadingHTML(msg='Cargando...') {
  return `<div class="loading-inline"><div class="spinner-border spinner-border-sm"></div><span>${msg}</span></div>`;
}

const selTipoMon = document.getElementById('sel_tipo_monitoreo');
const hiddenTipoMon = document.getElementById('hidden_tipo_monitoreo');
function syncTipoMonitoreo() {
  if (!selTipoMon || !hiddenTipoMon) return;
  hiddenTipoMon.value = selTipoMon.value || '';
}
selTipoMon?.addEventListener('change', syncTipoMonitoreo);
syncTipoMonitoreo();

const durHMS = document.getElementById('duracion_interaccion_hms');
const durSeg = document.getElementById('duracion_interaccion_segundos');
function syncDuracionInteraccion() {
  if (!durHMS || !durSeg) return;
  const v = (durHMS.value || '').trim();
  if (!v) { durSeg.value = ''; return; }
  const partes = v.split(':');
  const h = parseInt(partes[0] || 0, 10);
  const m = parseInt(partes[1] || 0, 10);
  const s = parseInt(partes[2] || 0, 10);
  durSeg.value = (h * 3600) + (m * 60) + s;
}
durHMS?.addEventListener('change', syncDuracionInteraccion);
syncDuracionInteraccion();

const KEY_TS_INICIO = 'correccion_ts_inicio_iso';
let tsInicioISO = sessionStorage.getItem(KEY_TS_INICIO);
if(!tsInicioISO){
  tsInicioISO = new Date().toISOString();
  sessionStorage.setItem(KEY_TS_INICIO, tsInicioISO);
}
const tsInicio = new Date(tsInicioISO);

document.getElementById('ts_inicio').value = tsInicio.toISOString();
document.getElementById('hora_inicio').value = hhmm(tsInicio);

function setHoraFinYDuracion() {
  const tsFin = new Date();
  document.getElementById('ts_fin').value = tsFin.toISOString();
  document.getElementById('hora_fin').value = hhmm(tsFin);
  const diffMs = tsFin - tsInicio;
  const diffSeg = Math.max(0, Math.floor(diffMs / 1000));
  document.getElementById('duracion_segundos').value = diffSeg;
}

const txtObs = document.getElementById('descripcion_final');
const txtMot = document.getElementById('motivo_correccion');
const charObs = document.getElementById('char_obs');
const charMot = document.getElementById('char_motivo');

function syncCounters(){
  if (txtObs && charObs) charObs.textContent = (txtObs.value || '').length;
  if (txtMot && charMot) charMot.textContent = (txtMot.value || '').length;
}
txtObs?.addEventListener('input', syncCounters);
txtMot?.addEventListener('input', syncCounters);
syncCounters();

async function cargarCuestionario(){
  seccionPreguntas.innerHTML = loadingHTML('Cargando cuestionario...');
  try{
    const rP = await fetch(`${BASE_URL}/cruds/servidor_filtros.php?tipo=preguntas&id_cola=${ID_COLA}`);
    const payload = await rP.json();
    seccionPreguntas.innerHTML = payload.html;

    window.__UMBRAL_COLA__ = payload.umbral;

    // 1️⃣ Prefill: marcar respuestas y estados
    aplicarPrefillYBadges();

    // 2️⃣ Buscador: indexar DOM completo
    indexarPreguntasParaBusqueda();
    aplicarFiltroPreguntas();

    // 3️⃣ Modo solo lectura (si aplica)
    if (SOLO_LECTURA) {
      document.querySelectorAll('.respuesta').forEach(el => el.disabled = true);
      document.querySelectorAll('.limpiar-btn').forEach(el => el.disabled = true);
    }

    // 4️⃣ Reglas y score
    recalcularScoreEnVivo();
    aplicarReglaImpulsorVsCritico();

    // 5️⃣ ✅ ÚNICA llamada para marcar secciones
    marcarSeccionesConEvaluadas();

  }catch(e){
    console.error(e);
    seccionPreguntas.innerHTML = `<div class="alert alert-danger">No se pudo cargar el cuestionario.</div>`;
  }
}


function aplicarPrefillYBadges(){
  const map = RESPUESTAS_ANTERIORES || {};
  Object.keys(map).forEach(k => {
    const pid = parseInt(k, 10);
    const resp = (map[k] || '').toUpperCase();
    if(!pid || !resp) return;

    const radios = document.querySelectorAll(`input[name="respuestas[${pid}]"]`);
    radios.forEach(r => {
      if ((r.value || '').toUpperCase() === resp) r.checked = true;
    });

    const btnLimpiar = document.querySelector(`button.limpiar-btn[data-target="${pid}"]`);
    const card = btnLimpiar?.closest('.pregunta-card');
    const headerText = card?.querySelector('.fw-semibold');

    if (headerText && !headerText.querySelector(`[data-badge-prev="${pid}"]`)) {
      const span = document.createElement('span');
      span.setAttribute('data-badge-prev', String(pid));
      span.className = 'badge bg-light text-dark border ms-2';
      span.style.fontSize = '.65rem';
      span.textContent = `Evaluada antes: ${resp === 'SI' ? 'CUMPLE' : (resp === 'NO' ? 'FALLA' : 'N/A')}`;
      headerText.appendChild(span);
    }

    if (card) {
      let estado = 'neutro';
      if (resp === 'SI') estado = 'cumple';
      else if (resp === 'NO') estado = 'falla';
      else if (resp === 'NO_APLICA') estado = 'na';
      card.className = `card mb-3 shadow-sm border-0 pregunta-card estado-${estado}`;
    }
  });
}




function marcarSeccionesConEvaluadas() {

  // limpiar marcas previas
  document
    .querySelectorAll('.list-group a.list-group-item.seccion-calificada')
    .forEach(a => a.classList.remove('seccion-calificada'));

  // recorrer el menú real de secciones
  document
    .querySelectorAll('.list-group a.list-group-item[href^="#"]')
    .forEach(link => {

      const id = link.getAttribute('href')?.replace('#','');
      if (!id) return;

      const pane = document.getElementById(id);
      if (!pane) return;

      // si hay respuestas marcadas → marcar sección
      if (pane.querySelector('input.respuesta:checked')) {
        link.classList.add('seccion-calificada');
      }
    });
}











function aplicarReglaImpulsorVsCritico() {
  if(SOLO_LECTURA) return;
  const checks = Array.from(document.querySelectorAll('.respuesta:checked'));
  const tieneImpulsorSI = checks.some(x => (x.dataset.tipo || '').toUpperCase() === 'IMPULSOR' && (x.value || '').toUpperCase() === 'SI');

  document.querySelectorAll('.respuesta').forEach(inp => {
    const tipo = (inp.dataset.tipo || '').toUpperCase();
    if (tieneImpulsorSI && tipo === 'CRITICO') {
      inp.disabled = true;
      if (inp.checked) inp.checked = false;
    } else {
      inp.disabled = false;
    }
  });
}

function recalcularScoreEnVivo(){
  const chip = document.getElementById('qaChip');
  if(!chip) return;

  const iconEl   = document.getElementById('qaChipIcon');
  const estadoEl = document.getElementById('qaChipEstado');
  const scoreEl  = document.getElementById('qaChipScore');
  const umbralEl = document.getElementById('qaChipUmbral');

  const sel = Array.from(document.querySelectorAll('.respuesta:checked'));
  const UMBRAL = (typeof window.__UMBRAL_COLA__ === 'number') ? window.__UMBRAL_COLA__ : 60;
  umbralEl.textContent = UMBRAL;

  if(sel.length === 0){
    chip.style.display = 'none';
    chip.classList.remove('qa-chip-ok','qa-chip-bad','qa-chip-warn');
    chip.classList.add('qa-chip-warn');
    iconEl.textContent = '⚠️';
    estadoEl.textContent = 'En proceso';
    scoreEl.textContent = '0.0';
    chip.title = 'Seleccione respuestas para ver el avance.';
    return;
  }

  chip.style.display = 'inline-flex';

  const items = sel.map(r => ({
    respuesta: (r.value || '').toUpperCase(),
    tipo: (r.dataset.tipo || 'NORMAL').toUpperCase(),
    peso: parseFloat(r.dataset.peso || '0') || 0
  }));

  const criticoFallado = items.some(x => x.tipo === 'CRITICO' && x.respuesta === 'NO');

  // SOLO IMPULSOR VALIDO (SIN N/A)
  const impulsorItems = items.filter(x => 
    x.tipo === 'IMPULSOR' &&
    x.respuesta !== 'NO_APLICA' &&
    x.respuesta !== 'NA' &&
    x.respuesta !== 'N/A'
  );

  let nota = 0;

  if(criticoFallado){
    nota = 0;
  }
  else if(impulsorItems.length > 0){

    const impulsorNO = impulsorItems.some(x => x.respuesta === 'NO');
    const impulsorSI = impulsorItems.some(x => x.respuesta === 'SI');

    if(impulsorNO){
      nota = 0;
    }else if(impulsorSI){
      nota = 100;
    }

  }
  else{
    // 🔵 CALCULO NORMAL (incluye cuando impulsor es N/A)
    let posibles = 0, obtenidos = 0;

    items.forEach(x => {
      if((x.tipo === 'CRITICO' || x.tipo === 'NORMAL') && x.respuesta !== 'NO_APLICA'){
        posibles += x.peso;
        if(x.respuesta === 'SI') obtenidos += x.peso;
      }
    });

    if(posibles > 0){
      nota = (obtenidos / posibles) * 100;
    }
  }

  const notaFmt = (Math.round(nota * 10) / 10).toFixed(1);
  scoreEl.textContent = notaFmt;

  chip.classList.remove('qa-chip-ok','qa-chip-bad','qa-chip-warn');

  if(nota >= UMBRAL){
    chip.classList.add('qa-chip-ok');
    iconEl.textContent = '✅';
    estadoEl.textContent = 'Aprobado';
  }else{
    chip.classList.add('qa-chip-bad');
    iconEl.textContent = '⚠️';
    estadoEl.textContent = 'Reprobado';
  }

  chip.title = `Nota: ${notaFmt}% | Umbral: ${UMBRAL}%`;
}


document.addEventListener('change', (e) => {
  if (SOLO_LECTURA) return;
  if (!e.target.classList.contains('respuesta')) return;

  const card = e.target.closest('.pregunta-card');
  if (card) {
    card.className = `card mb-3 shadow-sm border-0 pregunta-card estado-${e.target.dataset.estado}`;
  }

  aplicarReglaImpulsorVsCritico();
  recalcularScoreEnVivo();
  marcarSeccionesConEvaluadas(); // ✅ ACTUALIZA EL MENÚ DE SECCIONES
});


document.addEventListener('click', (e) => {
  if (SOLO_LECTURA) return;

  const btn = e.target.closest('.limpiar-btn');
  if (!btn) return;

  const id = btn.dataset.target;
  if (!id) return;

  document.querySelectorAll(`input[name="respuestas[${id}]"]`).forEach(i => {
    i.checked = false;
    i.disabled = false;
  });

  const card = btn.closest('.pregunta-card');
  if (card) {
    card.className = 'card mb-3 shadow-sm border-0 pregunta-card estado-neutro';
  }

  aplicarReglaImpulsorVsCritico();
  recalcularScoreEnVivo();
  marcarSeccionesConEvaluadas(); // ✅ ACTUALIZA EL MENÚ DE SECCIONES
});

document.getElementById('formCorreccion').addEventListener('submit', (e) => {
  if(SOLO_LECTURA){
    e.preventDefault();
    alert('Modo solo lectura: no puede guardar.');
    return;
  }

  setHoraFinYDuracion();
  syncTipoMonitoreo();
  syncDuracionInteraccion();

  const motivo = (document.getElementById('motivo_correccion').value || '').trim();
  if(motivo.length < 10){
    alert('❌ El motivo de corrección es obligatorio (mínimo 10 caracteres).');
    e.preventDefault();
    return;
  }

  const respuestas = [];
  document.querySelectorAll('.respuesta:checked').forEach(r => {
    const m = (r.name || '').match(/\d+/);
    if(!m) return;
    respuestas.push({ id_pregunta: parseInt(m[0]), respuesta: r.value });
  });

  if(respuestas.length === 0){
    alert('❌ Debe calificar al menos una pregunta.');
    e.preventDefault();
    return;
  }

  const tipoMon = (document.getElementById('hidden_tipo_monitoreo').value || '').trim();
  const idInt   = (document.getElementById('id_interaccion').value || '').trim();

  if(!tipoMon || !idInt){
    alert('❌ Complete: Tipo de Monitoreo e ID Interacción.');
    e.preventDefault();
    return;
  }

  document.getElementById('JsonRespuestas').value = JSON.stringify(respuestas);

  btnSubmit.disabled = true;
  btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
});

document.getElementById('btnReset').addEventListener('click', () => {
  sessionStorage.removeItem(KEY_TS_INICIO);
  location.reload();
});

cargarCuestionario();
</script>
<?php
$PAGE_SCRIPTS = ob_get_clean();



require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';

?>
