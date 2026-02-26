<?php
/**
 * /vistas_pantallas/formulario.php
 * ✅ NO TOCA SP NI BD
 * ✅ Solo agrega buscador de preguntas que funciona aunque estén en tabs (secciones) no visibles
 */
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';


/* ============================================================
   1) SEGURIDAD CENTRALIZADA (LOGIN + CAMBIO PASSWORD)
============================================================ */
require_once '../includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

/* ============================================================
   2) HELPERS
============================================================ */
function h($str) {
  return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/* ============================================================
   3) BASE URL
============================================================ */
$BASE_URL = BASE_URL;


/* ============================================================
   4) PERMISOS (SOLO DESDE seguridad.php)
============================================================ */
$puedeCrear  = can_create();
$soloLectura = is_readonly();
$veTodo      = can_see_all_areas();

$idAreaSesion = (int)($_SESSION['id_area'] ?? 0);

/* En creación: si no puede crear, NO entra */
if (!$puedeCrear) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "No tiene permisos para crear monitoreos.";
  $PAGE_ACTION_HTML = '
    <a class="btn btn-outline-secondary btn-sm shadow-sm" href="'.$BASE_URL.'/vistas_pantallas/menu.php">
      <i class="bi bi-house"></i> Volver al menú
    </a>
  ';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger"><i class="bi bi-shield-x me-1"></i>No tiene permisos para crear monitoreos.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

  exit;
}

$READONLY_ATTR = $soloLectura ? 'disabled' : '';

/* ============================================================
   5) GESTOR DE MONITOREO (desde sesión)
============================================================ */
$nombreGestor = trim((string)($_SESSION['nombre_completo'] ?? ($_SESSION['nombre'] ?? '')));
if ($nombreGestor === '') $nombreGestor = 'SIN_NOMBRE_SESION';

/* ============================================================
   6) TOPBAR / DISEÑO
============================================================ */
$PAGE_TITLE    = "📝 Nuevo Monitoreo de Calidad";
$PAGE_SUBTITLE = "";

$PAGE_ACTION_HTML = '
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a class="btn btn-outline-primary btn-sm shadow-sm" href="'.$BASE_URL.'/vistas_pantallas/menu.php">
      <i class="bi bi-house-door"></i> Inicio
    </a>

    <a class="btn btn-outline-danger btn-sm shadow-sm" href="'.$BASE_URL.'/cruds/logout.php">
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

/* ============================================================
   7) SESIÓN / AUDITOR
============================================================ */
$idAuditor = (int)($_SESSION['id_usuario'] ?? 0);

/* ============================================================
   8) CARGAR ÁREAS (según permiso)
============================================================ */
$areas = [];
try {
  if ($veTodo) {
    $stmt = $conexion->query("SELECT id_area, nombre_area FROM dbo.AREAS WHERE estado=1 ORDER BY nombre_area");
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $stmt = $conexion->prepare("SELECT id_area, nombre_area FROM dbo.AREAS WHERE estado=1 AND id_area=? ORDER BY nombre_area");
    $stmt->execute([$idAreaSesion]);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $areas = [];
}

$hoy = date('Y-m-d');

/* ============================================================
   9) INCLUDE HEADER
============================================================ */
require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div id="alertBox" class="alert d-none" role="alert"></div>

<?php if ($soloLectura): ?>
  <div class="alert alert-warning">
    <i class="bi bi-eye me-1"></i>
    Está en modo <b>solo lectura</b>. No podrá guardar cambios.
  </div>
<?php endif; ?>

<form id="formAuditoria" method="POST" action="<?= h($BASE_URL) ?>/cruds/proceso_guardar_monitoreo.php" novalidate>

  <!-- ============================================================
       DATOS DE INTERACCIÓN
  ============================================================ -->
  <div class="card card-soft mb-3">
    <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
      <div><i class="bi bi-info-circle me-2"></i> Información Técnica de la Interacción</div>
      <div class="small opacity-75">Fecha registro: <?= date('d/m/Y') ?></div>
    </div>

    <div class="card-body">
      <div class="row g-3">

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">FECHA INTERACCIÓN <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="date" name="fecha_interaccion" id="fecha_interaccion"
                 class="form-control form-control-sm" max="<?= h($hoy) ?>" value="<?= h($hoy) ?>" required>
        </div>

        <!-- internos -->
        <div class="col-md-3 d-none">
          <label class="form-label small fw-bold text-muted">HORA INICIO</label>
          <input type="time" name="hora_inicio" id="hora_inicio" class="form-control form-control-sm" readonly>
        </div>

        <div class="col-md-3 d-none">
          <label class="form-label small fw-bold text-muted">HORA FIN</label>
          <input type="time" name="hora_fin" id="hora_fin" class="form-control form-control-sm" readonly>
        </div>

        <div class="col-md-3 d-none">
          <label class="form-label small fw-bold text-muted">DURACIÓN (min)</label>
          <input type="text" id="duracion_min" class="form-control form-control-sm" readonly value="0">
        </div>

        <!-- Tipo -->
        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">TIPO DE MONITOREO <span class="text-danger">*</span></label>

          <select <?= $READONLY_ATTR ?> id="sel_tipo_monitoreo" class="form-select form-select-sm" required>
            <option value="">Seleccione...</option>
            <option value="REMOTO">Remoto</option>
            <option value="PRESENCIAL">Presencial</option>
            <option value="FANTASMA">Fantasma</option>
            
          </select>

          <input type="hidden" name="TipoMonitoreo" id="hidden_tipo_monitoreo" value="">
        </div>

         <!-- COLA (oculto pero funcional) -->
<div class="col-md-3 d-none">

  <label class="form-label small fw-bold text-muted">COLA</label>

  <select <?= $READONLY_ATTR ?> id="sel_medio" class="form-select form-select-sm">
    <option value="1">ATC CALL CENTER</option>
    <option value="2">ATC APP</option>
    <option value="3">ATC BOT ALI</option>
    <option value="4">ATC CHAT WEB</option>
    <option value="5">ATC REDES SOCIALES</option>
    <option value="6">CORREO ATC</option>
    <option value="7">FACEBOOK MURO</option>
    <option value="8">INB EMPLEADOS</option>
    <option value="9">ST N2 APP</option>
    <option value="10">ST N2 CALL CENTER</option>
    <option value="11">ST N2 CHAT WEB</option>
    <option value="12">ST N2 FB MURO</option>
    <option value="13">ST N2 IG MURO</option>
    <option value="14">ST N2 RS</option>
    <option value="15">FRONT PRESENCIAL</option>
    <option value="16">COBRANZA CALL</option>
    <option value="17">AGENDAMIENTO CALL</option>
  </select>

  <!-- IMPORTANTE: estos se mantienen para que el JS y el SP funcionen -->
  <input type="hidden" name="IdMedio" id="hidden_medio" value="1">
  <input type="hidden" name="MedioDeAtencion" id="hidden_medio_texto" value="ATC CALL CENTER">

</div>


        <!-- Gestor -->
        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">GESTOR DE MONITOREO <span class="text-danger">*</span></label>

          <input type="text" id="gestor_monitoreo_view" class="form-control form-control-sm"
                 value="<?= h($nombreGestor) ?>" readonly>

          <div class="help-mini"></div>

          <input type="hidden" name="gestor_monitoreo" id="gestor_monitoreo" value="<?= h($nombreGestor) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">ID TICKET / INTERACCIÓN <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="text" name="id_interaccion" id="id_interaccion"
                 class="form-control form-control-sm" placeholder="ID TICKET" required>
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">NRO. CONTRATO <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="text" name="numero_contrato" id="numero_contrato"
                 class="form-control form-control-sm" placeholder="NRO. CONTRATO" required>
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">TELÉFONO CLIENTE <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="tel" name="numero_contacto" id="numero_contacto"
                 class="form-control form-control-sm" placeholder="TELÉFONO CLIENTE" required>
        </div>

        <div class="col-md-6">
          <label class="form-label small fw-bold text-muted">LINK DE LA INTERACCIÓN <span class="text-danger">*</span></label>
          <input <?= $READONLY_ATTR ?> type="url" name="link_evidencia" id="link_evidencia"
                 class="form-control form-control-sm" placeholder="https://..." required>
        </div>

<!-- HORA INTERACCIÓN (oculto pero funcional) -->
<div class="col-md-3 d-none">

  <label class="form-label small fw-bold text-muted">HORA INTERACCIÓN</label>

  <input <?= $READONLY_ATTR ?> 
         type="time" 
         name="hora_interaccion" 
         id="hora_interaccion"
         class="form-control form-control-sm"
         value="10:30">

</div>


<div class="col-md-3">
  <label class="form-label small fw-bold text-muted">DURACIÓN DE MONITOREO</label>

  <input type="time"
         id="duracion_interaccion_hms"
         class="form-control form-control-sm bloqueo-tiempo"
         step="1"
         value="00:00:00"
         readonly>

  <input type="hidden"
         name="duracion_interaccion_segundos"
         id="duracion_interaccion_segundos"
         value="">
</div>



      </div>
    </div>
  </div>

  <!-- ============================================================
       FILTROS (AREA/COLA/AGENTE)
  ============================================================ -->
  <div class="sticky-bar">
    <div class="card card-soft shadow-sm border-0">
      <div class="card-body py-2">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label small fw-bold mb-1">ÁREA <span class="text-danger">*</span></label>

            <select <?= $READONLY_ATTR ?> id="sel_area" class="form-select form-select-sm fw-bold" required>
              <option value="">Seleccione Área</option>
              <?php foreach ($areas as $a): ?>
                <option value="<?= (int)$a['id_area'] ?>"><?= h($a['nombre_area']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold mb-1">PLANTILLA <span class="text-danger">*</span></label>
            <select <?= $READONLY_ATTR ?> id="sel_cola" class="form-select form-select-sm fw-bold" disabled required>
              <option value="">Seleccione Plantilla</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold mb-1">AGENTE <span class="text-danger">*</span></label>
            <select <?= $READONLY_ATTR ?> id="sel_agente" class="form-select form-select-sm fw-bold" disabled required>
              <option value="">Seleccione Agente</option>
            </select>
          </div>
        </div>

        <div class="mt-2 d-flex gap-2 flex-wrap">
          <span class="badge badge-soft rounded-pill" id="hdr_area">ÁREA: —</span>
          <span class="badge badge-soft rounded-pill" id="hdr_cola">COLA: —</span>
          <span class="badge bg-light text-primary border rounded-pill" id="hdr_agente">AGENTE: —</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================================
       🔎 BUSCADOR (MEJORADO: FILTRA CARDS + SECCIONES + MENU)
       ✅ Funciona aunque estén en tabs NO activos
       ✅ Muestra solo secciones con coincidencias
       ✅ Abre automáticamente la primera sección con resultados
  ============================================================ -->
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

  <!-- ============================================================
       PREGUNTAS (AJAX)
  ============================================================ -->
  <div id="seccion_preguntas" class="mt-3" style="min-height: 200px;">
    <div class="text-center py-5 text-muted bg-white rounded border">
      <i class="bi bi-card-checklist fs-1 opacity-25"></i>
      <p class="mt-2">Seleccione Área y Cola para cargar el cuestionario.</p>
    </div>
  </div>

  <!-- ============================================================
       OBSERVACIONES
  ============================================================ -->
  <div class="card card-soft mt-4 mb-5">
    <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
      <div><i class="bi bi-chat-right-text me-2"></i> Observaciones Finales</div>
      <div class="small opacity-75"><span id="char_count">0</span> / 500</div>
    </div>

    <div class="card-body">
      <textarea <?= $soloLectura ? 'readonly' : '' ?>
        name="DescripcionFinal"
        id="descripcion_final"
        class="form-control"
        rows="3"
        placeholder="Escriba aquí los detalles de la auditoría..."
        maxlength="500"
        required></textarea>

      <div class="help-mini mt-1">Recomendado: mínimo 10 caracteres.</div>
    </div>
  </div>

  <!-- ============================================================
       HIDDENs PARA EL SP
  ============================================================ -->
  <input type="hidden" name="IdAgente" id="hidden_agente">
  <input type="hidden" name="IdCola" id="hidden_cola">
  <input type="hidden" name="IdAuditor" value="<?= (int)$idAuditor ?>">
  <input type="hidden" name="JsonRespuestas" id="JsonRespuestas">

  <input type="hidden" name="ts_inicio" id="ts_inicio">
  <input type="hidden" name="ts_fin" id="ts_fin">
  <input type="hidden" name="duracion_segundos" id="duracion_segundos">

  <div class="text-end mb-5">
    <button <?= $soloLectura ? 'disabled' : '' ?> id="btnSubmit" type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow">
      GUARDAR MONITOREO
    </button>
  </div>

</form>

<?php
ob_start();
?>
<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;
const SOLO_LECTURA = <?= json_encode($soloLectura) ?>;

const selArea    = document.getElementById('sel_area');
const selCola    = document.getElementById('sel_cola');
const selAgente  = document.getElementById('sel_agente');
const btnSubmit  = document.getElementById('btnSubmit');

const hiddenAgente = document.getElementById('hidden_agente');
const hiddenCola   = document.getElementById('hidden_cola');

const seccionPreguntas = document.getElementById('seccion_preguntas');

function setBadge(id, text) { const el=document.getElementById(id); if(el) el.textContent = text; }
function loadingHTML(msg='Cargando...') {
  return `<div class="loading-inline"><div class="spinner-border spinner-border-sm"></div><span>${msg}</span></div>`;
}
function hhmm(d){
  return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}


/* ⏱ CONTADOR AUTOMÁTICO DURACIÓN INTERACCIÓN */
let timerInterval = null;
let timerStart = null;

function iniciarContadorDuracion() {

  // evita duplicar contadores si cambian de cola
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
  }

  timerStart = new Date();

  timerInterval = setInterval(() => {
    const now = new Date();
    const diff = Math.floor((now - timerStart) / 1000);

    const hh = String(Math.floor(diff / 3600)).padStart(2,'0');
    const mm = String(Math.floor((diff % 3600) / 60)).padStart(2,'0');
    const ss = String(diff % 60).padStart(2,'0');

    const inputHMS = document.getElementById('duracion_interaccion_hms');
    const inputSeg = document.getElementById('duracion_interaccion_segundos');

    if (inputHMS) inputHMS.value = `${hh}:${mm}:${ss}`;
    if (inputSeg) inputSeg.value = diff;
  }, 1000);
}











// 🔵 Marca en el menú las secciones que ya tienen preguntas calificadas
function actualizarSeccionesCalificadas() {
  document.querySelectorAll('.list-group-item.seccion-calificada')
    .forEach(el => el.classList.remove('seccion-calificada'));

  document.querySelectorAll('.respuesta:checked').forEach(radio => {
    const card = radio.closest('.pregunta-card');
    const pane = card?.closest('.tab-pane');
    if (!pane || !pane.id) return;

    const link = document.querySelector(`.list-group a[href="#${pane.id}"]`);
    if (link) link.classList.add('seccion-calificada');
  });
}


/* ============================================================
   🔎 BUSCADOR (FULL): cards + tab-panes + menu secciones
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
    seccionPreguntas.parentElement?.insertBefore(info, seccionPreguntas);
  }
  return info;
}

function indexarPreguntasParaBusqueda(){
  if(!seccionPreguntas) return;
  seccionPreguntas.querySelectorAll('.pregunta-card').forEach(card => {
    // cache
    card.dataset.search = normalizarTexto(card.textContent);
    // marca su pane (sección) por seguridad
    const pane = card.closest('.tab-pane');
    if (pane && !pane.dataset.seccion) {
      pane.dataset.seccion = (pane.querySelector('h5')?.textContent || '').trim();
    }
  });
}

/**
 * ✅ CLAVE:
 * - Oculta/Muestra cards
 * - Oculta/Muestra panes completos si no tienen coincidencias
 * - Oculta/Muestra items del menú lateral según coincidencias
 * - Activa automáticamente la primera sección con match
 */
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

  // Conteo por pane id
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
      p.style.display = '';          // vuelve normal
      p.classList.remove('d-none');  // por si acaso
    }else{
      p.style.display = (cnt > 0) ? '' : 'none';
    }
  });

  // 3) menú lateral: ocultar links sin match + actualizar badge
  menuLinks.forEach(a => {
    const href = a.getAttribute('href') || '';
    const paneId = href.startsWith('#') ? href.substring(1) : '';
    const cnt = (paneId && countByPaneId.has(paneId)) ? (countByPaneId.get(paneId) || 0) : 0;

    const badge = a.querySelector('.badge');
    if(badge && q){
      badge.textContent = String(cnt);
    }else if(badge && !q){
      // si no hay búsqueda, no toques el número original (dejar como está)
      // (no hacemos nada)
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
      // activar link
      menuLinks.forEach(x => x.classList.remove('active'));
      firstLink.classList.add('active');

      // activar pane
      const target = firstLink.getAttribute('href') || '';
      panes.forEach(p => {
        p.classList.remove('show','active');
      });
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

/* MEDIO -> hidden */
const selMedio = document.getElementById('sel_medio');
const hiddenMedioId = document.getElementById('hidden_medio');
const hiddenMedioTxt = document.getElementById('hidden_medio_texto');

function syncMedio() {
  if(!selMedio || !hiddenMedioId || !hiddenMedioTxt) return;
  hiddenMedioId.value = selMedio.value;
  const txt = selMedio.options[selMedio.selectedIndex]?.text || '';
  hiddenMedioTxt.value = txt;
}
selMedio?.addEventListener('change', syncMedio);
syncMedio();

/* TIPO MONITOREO -> hidden */
const selTipoMon = document.getElementById('sel_tipo_monitoreo');
const hiddenTipoMon = document.getElementById('hidden_tipo_monitoreo');

const inputFecha = document.getElementById('fecha_interaccion');

function controlarFechaPorTipo() {
  const tipo = selTipoMon.value;

  if (tipo === 'PRESENCIAL' || tipo === 'FANTASMA') {

    // Obtener fecha actual
    const hoy = new Date();
    const yyyy = hoy.getFullYear();
    const mm = String(hoy.getMonth() + 1).padStart(2,'0');
    const dd = String(hoy.getDate()).padStart(2,'0');

    inputFecha.value = `${yyyy}-${mm}-${dd}`;

    // Bloquear edición
    inputFecha.readOnly = true;

  } else {
    inputFecha.readOnly = false;
  }
}

selTipoMon?.addEventListener('change', controlarFechaPorTipo);

// Ejecutar una vez al cargar (por si ya viene seleccionado)
controlarFechaPorTipo();


function syncTipoMonitoreo() {
  if (!selTipoMon || !hiddenTipoMon) return;
  hiddenTipoMon.value = selTipoMon.value || '';
}
selTipoMon?.addEventListener('change', syncTipoMonitoreo);
syncTipoMonitoreo();

/* DURACIÓN HH:MM:SS -> segundos */
const durHMS = document.getElementById('duracion_interaccion_hms');
const durSeg = document.getElementById('duracion_interaccion_segundos');
function syncDuracionInteraccion() {
  if (!durHMS || !durSeg) return;
  const v = (durHMS.value || '').trim();
  if (!v) { durSeg.value = ''; return; }
  const partes = v.split(':');
  const hh = parseInt(partes[0] || 0, 10);
  const mm = parseInt(partes[1] || 0, 10);
  const ss = parseInt(partes[2] || 0, 10);
  durSeg.value = (hh * 3600) + (mm * 60) + ss;
}
durHMS?.addEventListener('change', syncDuracionInteraccion);
syncDuracionInteraccion();

/* ⏱ inicio/fin */

/* 🔹 Función para generar fecha ISO LOCAL (no UTC) */
function toLocalISO(dt){
  const pad = n => String(n).padStart(2,'0');
  return dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate())
    + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());
}

const KEY_TS_INICIO = 'monitoreo_ts_inicio_iso';
let tsInicioISO = sessionStorage.getItem(KEY_TS_INICIO);

if(!tsInicioISO){
  tsInicioISO = toLocalISO(new Date());   // ✅ ahora LOCAL
  sessionStorage.setItem(KEY_TS_INICIO, tsInicioISO);
}

const tsInicio = new Date(tsInicioISO);

document.getElementById('ts_inicio').value = tsInicioISO;  // ✅ LOCAL
document.getElementById('hora_inicio').value = hhmm(tsInicio);

function setHoraFinYDuracion() {
  const tsFin = new Date();

  document.getElementById('ts_fin').value = toLocalISO(tsFin);  // ✅ LOCAL
  document.getElementById('hora_fin').value = hhmm(tsFin);

  const diffMs = tsFin - tsInicio;
  const diffSeg = Math.max(0, Math.floor(diffMs / 1000));
  document.getElementById('duracion_segundos').value = diffSeg;
  document.getElementById('duracion_min').value = Math.floor(diffSeg / 60);
}


/* contador observaciones */


/* 📞 NORMALIZAR TELÉFONO ECUADOR */
const telefonoInput = document.getElementById('numero_contacto');

telefonoInput?.addEventListener('input', function() {

  // Quitar todo lo que no sea número
  let valor = this.value.replace(/\D/g, '');

  // Si empieza con 593 y tiene 12 dígitos → convertir a 09XXXXXXXX
  if (valor.startsWith('593') && valor.length === 12) {
    valor = '0' + valor.substring(3);
  }

  this.value = valor;
});


/* 🔢 LIMPIEZA AUTOMÁTICA NRO CONTRATO */
const contratoInput = document.getElementById('numero_contrato');

contratoInput?.addEventListener('input', function() {
  const numeros = this.value.match(/\d+/g);
  this.value = numeros ? numeros.join('') : '';
});


const txtObs = document.getElementById('descripcion_final');
const charCount = document.getElementById('char_count');
txtObs?.addEventListener('input', () => { if(charCount) charCount.textContent = txtObs.value.length; });

/* ÁREA -> COLAS */
selArea?.addEventListener('change', async () => {
  const idArea = parseInt(selArea.value);
  setBadge('hdr_area', idArea ? `ÁREA: ${selArea.options[selArea.selectedIndex].text}` : 'ÁREA: —');

  selCola.innerHTML = '<option value="">Cargando...</option>';
  selCola.disabled = true;
  selAgente.innerHTML = '<option value="">Seleccione Agente...</option>';
  selAgente.disabled = true;

  setBadge('hdr_cola', 'COLA: —');
  setBadge('hdr_agente', 'AGENTE: —');
  hiddenAgente.value = '';
  hiddenCola.value = '';

  const chip = document.getElementById('qaChip');
  if(chip) chip.style.display = 'none';

  seccionPreguntas.innerHTML = `<div class="text-center py-5 text-muted bg-white rounded border">
      <i class="bi bi-card-checklist fs-1 opacity-25"></i>
      <p class="mt-2">Seleccione Área y Cola para cargar el cuestionario.</p>
  </div>`;
  aplicarFiltroPreguntas();

  if(!idArea || isNaN(idArea)) return;

  try {
    const r = await fetch(`${BASE_URL}/cruds/servidor_filtros.php?tipo=colas&id_area=${idArea}`);
    const data = await r.json();
    selCola.innerHTML = '<option value="">Seleccione Plantilla...</option>';
    data.forEach(d => selCola.innerHTML += `<option value="${d.id_cola}">${d.nombre_cola}</option>`);
    selCola.disabled = SOLO_LECTURA ? true : false;
  } catch (e) {
    console.error('Error colas:', e);
    selCola.innerHTML = '<option value="">Error al cargar</option>';
  }
});

/* COLA -> AGENTES + PREGUNTAS */
selCola?.addEventListener('change', async () => {
  iniciarContadorDuracion();
  const idCola = parseInt(selCola.value);
  hiddenCola.value = idCola || '';
  setBadge('hdr_cola', idCola ? `COLA: ${selCola.options[selCola.selectedIndex].text}` : 'COLA: —');

  selAgente.disabled = true;
  selAgente.innerHTML = '<option value="">Cargando...</option>';
  seccionPreguntas.innerHTML = loadingHTML('Cargando cuestionario...');

  const chip = document.getElementById('qaChip');
  if(chip) chip.style.display = 'none';

  if(!idCola || isNaN(idCola)) return;

  try {
    const idArea = parseInt(selArea.value);

    const rA = await fetch(`${BASE_URL}/cruds/servidor_filtros.php?tipo=agentes&id_area=${idArea}`);
    const agentes = await rA.json();
    selAgente.innerHTML = '<option value="">Seleccione Agente...</option>';
    agentes.forEach(a => selAgente.innerHTML += `<option value="${a.id_agente_int}">${a.nombre_agente}</option>`);
    selAgente.disabled = SOLO_LECTURA ? true : false;

    const rP = await fetch(`${BASE_URL}/cruds/servidor_filtros.php?tipo=preguntas&id_cola=${idCola}`);
    const payload = await rP.json();
    seccionPreguntas.innerHTML = payload.html;

    window.__UMBRAL_COLA__ = payload.umbral;

    if (SOLO_LECTURA) {
      document.querySelectorAll('.respuesta').forEach(el => el.disabled = true);
      document.querySelectorAll('.limpiar-btn').forEach(el => el.disabled = true);
    }

    // ✅ INDEX + APLICAR filtro (AHORA también filtra tabs + menú)
    indexarPreguntasParaBusqueda();
    aplicarFiltroPreguntas();

    recalcularScoreEnVivo();
    aplicarReglaImpulsorVsCritico();

  } catch (e) {
    console.error('Error preguntas/agentes:', e);
    seccionPreguntas.innerHTML = `<div class="alert alert-danger">No se pudo cargar el cuestionario.</div>`;
    aplicarFiltroPreguntas();
  }
});

selAgente?.addEventListener('change', () => {
  hiddenAgente.value = selAgente.value || '';
  setBadge('hdr_agente', selAgente.value ? `AGENTE: ${selAgente.options[selAgente.selectedIndex].text}` : 'AGENTE: —');
});

/* estados visuales */
document.addEventListener('change', (e) => {
  if(SOLO_LECTURA) return;
  if(!e.target.classList.contains('respuesta')) return;
  const card = e.target.closest('.pregunta-card');
  if(card){
    card.className = `card mb-3 shadow-sm border-0 pregunta-card estado-${e.target.dataset.estado}`;
  }
  aplicarReglaImpulsorVsCritico();
  recalcularScoreEnVivo();
  actualizarSeccionesCalificadas();
});

/* limpiar */
document.addEventListener('click', (e) => {
  if(SOLO_LECTURA) return;
  const btn = e.target.closest('.limpiar-btn');
  if(!btn) return;

  const id = btn.dataset.target;
  if(!id) return;

  document.querySelectorAll(`input[name="respuestas[${id}]"]`).forEach(i => {
    i.checked = false;
    i.disabled = false;
  });

  const card = btn.closest('.pregunta-card');
  if(card){
    card.className = 'card mb-3 shadow-sm border-0 pregunta-card estado-neutro';
  }

  aplicarReglaImpulsorVsCritico();
  recalcularScoreEnVivo();
  actualizarSeccionesCalificadas(); //
});




function aplicarReglaImpulsorVsCritico() {
  if (SOLO_LECTURA) return;

  const checks = Array.from(document.querySelectorAll('.respuesta:checked'));

  const hayCriticoNO = checks.some(x =>
    x.dataset.tipo === 'CRITICO' && x.value === 'NO'
  );

  document.querySelectorAll('.respuesta').forEach(inp => {

    // 🔴 Si hay CRÍTICO en NO → bloquear todo el IMPULSOR
    if (hayCriticoNO && inp.dataset.tipo === 'IMPULSOR') {

      inp.onclick = function(e){
        e.preventDefault();
        alert("❌ No puede calificar el Impulsor porque existe un Crítico en 'No Cumple'.");
      };

      if(inp.checked){
        inp.checked = false;
      }

      return;
    }

    // 🟡 El IMPULSOR solo puede marcarse como SI
    if (inp.dataset.tipo === 'IMPULSOR' && inp.value === 'NO') {

      inp.onclick = function(e){
        e.preventDefault();
        alert("⚠️ El Impulsor solo puede calificarse como 'Cumple'.");
      };

      if(inp.checked){
        inp.checked = false;
      }

      return;
    }

    // 🔓 Limpia bloqueo en los demás casos
    inp.onclick = null;

  });
}

/* score chip */
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
  const impulsorSI     = items.some(x => x.tipo === 'IMPULSOR' && x.respuesta === 'SI');

  let nota = 0;

  // 🔴 1. Crítico domina siempre
  if (criticoFallado) {
    nota = 0;
  }
  // 🔵 2. Impulsor fuerza 100% si no hay crítico fallado
  else if (impulsorSI) {
    nota = 100;
  }
  // 🟢 3. Cálculo normal
  else {
    let posibles = 0;
    let obtenidos = 0;

    items.forEach(x => {
      if (
        (x.tipo === 'CRITICO' || x.tipo === 'NORMAL') &&
        x.respuesta !== 'NO_APLICA' &&
        x.respuesta !== 'NA' &&
        x.respuesta !== 'N/A'
      ) {
        posibles += x.peso;
        if (x.respuesta === 'SI') {
          obtenidos += x.peso;
        }
      }
    });

    nota = (posibles > 0) ? (obtenidos / posibles) * 100 : 0;
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

 
/* submit */
document.getElementById('formAuditoria').addEventListener('submit', (e) => {

  if(SOLO_LECTURA){
    e.preventDefault();
    alert('Modo solo lectura: no puede guardar.');
    return;
  }


  // 📞 Validar teléfono Ecuador (09XXXXXXXX)
const telefono = document.getElementById('numero_contacto');

if (!telefono.value.trim()) {
  alert('❌ Debe ingresar un número de teléfono.');
  e.preventDefault();
  telefono.focus();
  return;
}

if (!/^09\d{8}$/.test(telefono.value)) {
  alert('❌ Ingrese un número celular válido (09XXXXXXXX).');
  e.preventDefault();
  telefono.focus();
  return;
}


  // 🔢 Validar número de contrato obligatorio
  const contrato = document.getElementById('numero_contrato');

  if (!contrato.value.trim()) {
    alert('❌ Debe ingresar un número de contrato válido.');
    e.preventDefault();
    contrato.focus();
    return;
  }

  setHoraFinYDuracion();
  syncTipoMonitoreo();
  syncDuracionInteraccion();

  const respuestas = [];
  document.querySelectorAll('.respuesta:checked').forEach(r => {
    const m = (r.name || '').match(/\d+/);
    if(!m) return;
    respuestas.push({ id_pregunta: parseInt(m[0]), respuesta: r.value });
  });

  if(!hiddenAgente.value || !hiddenCola.value){
    alert('❌ Debe seleccionar Área, Cola y Agente.');
    e.preventDefault();
    return;
  }

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


/* reset */
document.getElementById('btnReset').addEventListener('click', () => {
  sessionStorage.removeItem('monitoreo_ts_inicio_iso');
  location.reload();
});
</script>
<?php
$PAGE_SCRIPTS = ob_get_clean();



require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';

?>
