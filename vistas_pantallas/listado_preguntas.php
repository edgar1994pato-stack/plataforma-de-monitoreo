<?php
/**
 * /vistas_pantallas/listado_preguntas.php
 * =========================================================
 * LISTADO OFICIAL DE PREGUNTAS
 *
 * ✅ Diseño unificado (diseño_arriba / diseño_abajo)
 * ✅ Protegido por sesión (seguridad.php)
 * ✅ Filtros GET: area, cola, seccion, ev
 *
 * ✅ Seguridad backend centralizada:
 *   - can_see_all_areas(): si ve todo, puede filtrar por área
 *   - si NO ve todo: SIEMPRE se fuerza el área de sesión
 *
 * ✅ Acciones:
 *   - Crear: solo si can_create()
 *   - Anular POR COLA: solo si can_create() y ESTADO_VIGENCIA='VIGENTE'
 *     -> POST a /cruds/proceso_anular_pregunta.php enviando id_pregunta + id_cola
 *
 * ✅ SP utilizado:
 *   - dbo.PR_LISTAR_PREGUNTAS(@ID_AREA, @ID_COLA, @SOLO_VIGENTES)
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';


require_login();
force_password_change();

function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }



/* =========================================================
 * SESIÓN
 * ========================================================= */
$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
$idRol     = (int)($_SESSION['id_rol'] ?? 0);
$idAreaSes = (int)($_SESSION['id_area'] ?? 0);

/* =========================================================
 * PERMISOS (centralizados)
 * ========================================================= */
$veTodo      = can_see_all_areas();
$puedeCrear  = can_create();
$soloLectura = is_readonly();

/* Si NO ve todo, debe tener área */
if (!$veTodo && $idAreaSes <= 0) {
  http_response_code(403);
  die('Tu usuario no tiene un área asignada. Contacta al administrador.');
}

/* =========================================================
 * HEADER
 * ========================================================= */
$PAGE_TITLE    = "🧩 Módulo de Preguntas";
$PAGE_SUBTITLE = "";

$PAGE_ACTION_HTML = '
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a class="btn btn-outline-secondary btn-sm shadow-sm" href="'.h($BASE_URL).'/vistas_pantallas/menu.php">
      <i class="bi bi-house"></i> Inicio
    </a>
    <a class="btn btn-outline-danger btn-sm shadow-sm" href="'.h($BASE_URL).'/cruds/logout.php">
      <i class="bi bi-box-arrow-right"></i> Salir
    </a>
  </div>
';

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';


/* =========================================================
 * FLASH (mensajes)
 * ========================================================= */
if (!empty($_SESSION['flash_ok'])) {
  echo '<div class="alert alert-success"><b>'.h($_SESSION['flash_ok']).'</b></div>';
  unset($_SESSION['flash_ok']);
}
if (!empty($_SESSION['flash_err'])) {
  echo '<div class="alert alert-danger"><b>'.h($_SESSION['flash_err']).'</b></div>';
  unset($_SESSION['flash_err']);
}

/* =========================================================
 * FILTROS GET
 * ========================================================= */
$idAreaGet    = isset($_GET['area']) ? (int)$_GET['area'] : 0;
$idColaGet    = isset($_GET['cola']) ? (int)$_GET['cola'] : 0;
$idSeccionGet = isset($_GET['seccion']) ? (int)$_GET['seccion'] : 0;

/**
 * ev = estado de vigencia:
 *  - TODOS (default)
 *  - VIGENTE / ANULADA / VENCIDA / NO_VIGENTE / SIN_PONDERACION (si tu SP lo devuelve)
 */
$ev = strtoupper(trim((string)($_GET['ev'] ?? 'TODOS')));
$validEV = ['TODOS','VIGENTE','ANULADA','VENCIDA','NO_VIGENTE','SIN_PONDERACION'];
if (!in_array($ev, $validEV, true)) $ev = 'TODOS';

/* =========================================================
 * CONTROL DE ÁREA por rol (backend)
 * ========================================================= */
$idAreaParam = null;

if ($veTodo) {
  $idAreaParam = ($idAreaGet > 0) ? $idAreaGet : null; // null = todas
} else {
  $idAreaParam = ($idAreaSes > 0) ? $idAreaSes : null; // forzado
}

$idColaParam = ($idColaGet > 0) ? $idColaGet : null;

/* =========================================================
 * CARGA ÁREAS (para filtro)
 * ========================================================= */
$areas = [];
try {
  if ($veTodo) {
    $res = $conexion->query("SELECT id_area, nombre_area FROM dbo.AREAS WHERE estado=1 ORDER BY nombre_area");
    $areas = $res->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $st = $conexion->prepare("SELECT id_area, nombre_area FROM dbo.AREAS WHERE estado=1 AND id_area=? ORDER BY nombre_area");
    $st->execute([$idAreaSes]);
    $areas = $st->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $areas = [];
}

/* =========================================================
 * CARGA SECCIONES (server-side, según área efectiva)
 * ========================================================= */
$secciones = [];
try {
  if ($idAreaParam !== null && $idAreaParam > 0) {
    $stS = $conexion->prepare("
      SELECT id_seccion, nombre_seccion
      FROM dbo.SECCIONES
      WHERE id_area = ?
        AND ISNULL(estado,1) = 1
      ORDER BY nombre_seccion
    ");
    $stS->execute([$idAreaParam]);
    $secciones = $stS->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $secciones = [];
}

/* =========================================================
 * EJECUTAR SP LISTADO (por área/cola)
 * ========================================================= */
$rows = [];
$errorListado = '';

try {
  // ✅ Pasa SIEMPRE los 3 parámetros del SP (incluye @SOLO_VIGENTES)
  $soloVigentes = 0; // para UI dejamos todo y filtramos por ev en PHP
  $sql = "EXEC dbo.PR_LISTAR_PREGUNTAS ?, ?, ?";
  $stmt = $conexion->prepare($sql);
  $stmt->execute([$idAreaParam, $idColaParam, $soloVigentes]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $errorListado = $e->getMessage();
  $rows = [];
}

/* =========================================================
 * FILTROS EXTRA en PHP (sección / estado vigencia)
 * ========================================================= */
$rowsFiltradas = $rows;

if ($idSeccionGet > 0) {
  $rowsFiltradas = array_values(array_filter($rowsFiltradas, function($r) use ($idSeccionGet){
    return (int)($r['id_seccion'] ?? 0) === $idSeccionGet;
  }));
}

if ($ev !== 'TODOS') {
  $rowsFiltradas = array_values(array_filter($rowsFiltradas, function($r) use ($ev){
    return strtoupper(trim((string)($r['ESTADO_VIGENCIA'] ?? ''))) === $ev;
  }));
}

/* =========================================================
 * URLs acciones
 * ========================================================= */
$urlSelf         = $BASE_URL . "/vistas_pantallas/listado_preguntas.php";
$urlCrear        = $BASE_URL . "/vistas_pantallas/preguntas_formulario.php";
$urlProcesoAnular= $BASE_URL . "/cruds/proceso_anular_pregunta.php";
?>

<!-- ===================== FILTROS ===================== -->
<div class="card card-soft mb-3">
  <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
    <div><i class="bi bi-funnel me-2"></i>Filtros</div>
    <div class="small opacity-75">Resultados: <b><?= (int)count($rowsFiltradas) ?></b></div>
  </div>

  <div class="card-body">
    <form method="GET" action="<?= h($urlSelf) ?>" class="row g-2 align-items-end">

      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">ÁREA</label>
        <select class="form-select form-select-sm" name="area" id="f_area" <?= $veTodo ? '' : 'disabled' ?>>
          <option value="0">Todas</option>
          <?php foreach ($areas as $a): ?>
            <?php
              $idA = (int)$a['id_area'];
              $sel = ($veTodo ? ($idAreaGet === $idA) : ($idAreaSes === $idA)) ? 'selected' : '';
            ?>
            <option value="<?= $idA ?>" <?= $sel ?>><?= h($a['nombre_area']) ?></option>
          <?php endforeach; ?>
        </select>

        <?php if (!$veTodo): ?>
          <input type="hidden" name="area" value="<?= (int)$idAreaSes ?>">
        <?php endif; ?>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">COLA</label>
        <select class="form-select form-select-sm" name="cola" id="f_cola">
          <option value="0">Todas</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">SECCIÓN</label>
        <select class="form-select form-select-sm" name="seccion">
          <option value="0">Todas</option>
          <?php foreach ($secciones as $s): ?>
            <?php $idS = (int)$s['id_seccion']; ?>
            <option value="<?= $idS ?>" <?= ($idSeccionGet === $idS ? 'selected' : '') ?>>
              <?= h($s['nombre_seccion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">ESTADO</label>
        <select class="form-select form-select-sm" name="ev">
          <?php foreach (['TODOS','VIGENTE','ANULADA','VENCIDA','NO_VIGENTE','SIN_PONDERACION'] as $opt): ?>
            <option value="<?= $opt ?>" <?= ($ev === $opt ? 'selected' : '') ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm">
          <i class="bi bi-search"></i> Buscar
        </button>

        <a class="btn btn-outline-secondary btn-sm w-100 shadow-sm" href="<?= h($urlSelf) ?>">
          <i class="bi bi-x-circle"></i> Limpiar
        </a>
      </div>

      <div class="col-12 col-md-3">
        <?php if ($puedeCrear && !$soloLectura): ?>
          <a class="btn btn-warning btn-sm w-100 shadow-sm fw-bold" href="<?= h($urlCrear) ?>">
            <i class="bi bi-plus-circle"></i> Crear pregunta
          </a>
        <?php else: ?>
          <button type="button" class="btn btn-outline-secondary btn-sm w-100 shadow-sm" disabled>
            <i class="bi bi-lock"></i> Sin permisos para crear
          </button>
        <?php endif; ?>
      </div>

    </form>
  </div>
</div>

<?php if ($errorListado !== ''): ?>
  <div class="alert alert-danger">
    <b>❌ Error al listar preguntas:</b>
    <div class="small mt-1"><?= h($errorListado) ?></div>
  </div>
<?php endif; ?>

<!-- ===================== RESULTADOS ===================== -->
<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
    <div><i class="bi bi-table me-2"></i>Resultados</div>
    <div class="small opacity-75">
      <?= $veTodo ? 'Ámbito: todas las áreas' : ('Ámbito: solo tu área (ID: '.(int)$idAreaSes.')') ?>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr class="small text-muted">
            <th>Área</th>
            <th>Cola</th>
            <th>Sección</th>
            <th style="width:110px;">Tipo</th>
            <th style="width:140px;">Gestión</th>
            <th style="width:120px;">Peso</th>
            <th>Pregunta</th>
            <th style="width:140px;">Estado</th>
            <th style="width:220px;" class="text-end">Acciones</th>
          </tr>
        </thead>

        <tbody>
        <?php if (count($rowsFiltradas) === 0): ?>
          <tr>
            <td colspan="9" class="text-center text-muted py-4">
              <i class="bi bi-inbox fs-3 opacity-25"></i>
              <div class="mt-2">No hay preguntas para los filtros seleccionados.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rowsFiltradas as $r): ?>
            <?php
              $idPregunta = (int)($r['id_pregunta'] ?? 0);
              $idColaFila = (int)($r['id_cola'] ?? 0);

              $estadoV = strtoupper(trim((string)($r['ESTADO_VIGENCIA'] ?? '')));

              $badgeV = 'bg-secondary';
              if ($estadoV === 'VIGENTE') $badgeV = 'bg-success';
              elseif ($estadoV === 'ANULADA') $badgeV = 'bg-danger';
              elseif ($estadoV === 'VENCIDA') $badgeV = 'bg-warning text-dark';
              elseif ($estadoV === 'NO_VIGENTE') $badgeV = 'bg-info text-dark';
              elseif ($estadoV === 'SIN_PONDERACION') $badgeV = 'bg-secondary';

              $activaPreg = (int)($r['ACTIVA_PREGUNTA'] ?? 0) === 1;
              $activaPon  = (int)($r['ACTIVA_PONDERACION'] ?? 0) === 1;

              $peso = isset($r['CALIFICACION_PORC']) ? (float)$r['CALIFICACION_PORC'] : 0.0;

              $textoPregunta = trim((string)($r['PREGUNTA'] ?? ''));
              $textoDesc = trim((string)($r['DESCRIPCION'] ?? ''));

              $collapseId = "p_q_" . $idPregunta . "_" . $idColaFila;
            ?>
            <tr>
              <td><?= h($r['AREA'] ?? '-') ?></td>
              <td><?= h($r['COLA'] ?? '-') ?></td>
              <td><?= h($r['NOMBRE_SECCION'] ?? '-') ?></td>

              <td>
                <span class="badge bg-light text-dark border"><?= h($r['TIPO'] ?? 'NORMAL') ?></span>
              </td>

              <td><?= h($r['GESTION'] ?? '-') ?></td>

              <td>
                <span class="badge <?= $activaPon ? 'bg-primary' : 'bg-secondary' ?>">
                  <?= number_format($peso, 2) ?>
                </span>
              </td>

              <td>
                <div class="fw-semibold"><?= h($textoPregunta !== '' ? $textoPregunta : '—') ?></div>

                <?php if ($textoDesc !== ''): ?>
                  <a class="small text-decoration-none" data-bs-toggle="collapse" href="#<?= h($collapseId) ?>" role="button" aria-expanded="false">
                    Ver descripción
                  </a>
                  <div class="collapse mt-1" id="<?= h($collapseId) ?>">
                    <div class="small text-muted border rounded p-2 bg-light">
                      <?= nl2br(h($textoDesc)) ?>
                    </div>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <span class="badge <?= $badgeV ?>"><?= h($estadoV !== '' ? $estadoV : '—') ?></span>
                <div class="small text-muted mt-1">
                  Preg: <?= $activaPreg ? '🟢' : '🔴' ?>
                  &nbsp;|&nbsp; Pon: <?= $activaPon ? '🟢' : '🟡' ?>
                </div>
              </td>

              <td class="text-end">
                <?php if ($puedeCrear && !$soloLectura && $estadoV === 'VIGENTE'): ?>
                  <?php if ($idColaFila > 0): ?>
                    <form method="POST" action="<?= h($urlProcesoAnular) ?>" class="d-inline"
                          onsubmit="return confirm('¿Anular esta pregunta SOLO para esta cola?');">
                      <input type="hidden" name="id_pregunta" value="<?= (int)$idPregunta ?>">
                      <input type="hidden" name="id_cola" value="<?= (int)$idColaFila ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-x-octagon"></i> Anular
                      </button>
                    </form>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled
                            title="No se pudo determinar id_cola de la fila">
                      <i class="bi bi-dash-circle"></i> Anular
                    </button>
                  <?php endif; ?>
                <?php else: ?>
                  <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                    <i class="bi bi-dash-circle"></i> Anular
                  </button>
                <?php endif; ?>

                <?php if ($puedeCrear && !$soloLectura): ?>
                  <a class="btn btn-outline-primary btn-sm"
                     href="<?= h($urlCrear) ?>?modo=duplicar&id=<?= (int)$idPregunta ?>">
                    <i class="bi bi-files"></i> Duplicar
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
/* =========================================================
 * SCRIPTS (AJAX: carga colas por área)
 * ========================================================= */
ob_start();
?>
<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;

const fArea = document.getElementById('f_area');
const fCola = document.getElementById('f_cola');

const selectedCola = <?= (int)$idColaGet ?>;

function getAreaActual(){
  const v = parseInt((fArea?.value || '0'), 10);
  return isNaN(v) ? 0 : v;
}

async function cargarColas(idArea){
  fCola.innerHTML = '<option value="0">Todas</option>';
  if(!idArea || idArea <= 0) return;

  try{
    const r = await fetch(`${BASE_URL}/cruds/servidor_filtros.php?tipo=colas&id_area=${encodeURIComponent(idArea)}`, { credentials: 'same-origin' });
    const data = await r.json();

    data.forEach(d => {
      const opt = document.createElement('option');
      opt.value = d.id_cola;
      opt.textContent = d.nombre_cola;
      if(parseInt(opt.value,10) === selectedCola) opt.selected = true;
      fCola.appendChild(opt);
    });
  }catch(e){
    console.error('Error cargando colas:', e);
  }
}

// Inicial
cargarColas(getAreaActual());

// Si el select está habilitado (veTodo), al cambiar área resetea cola y recarga
fArea?.addEventListener('change', () => {
  const idArea = getAreaActual();

  const url = new URL(window.location.href);
  url.searchParams.set('area', idArea > 0 ? String(idArea) : '0');
  url.searchParams.set('cola', '0');       // reset
  url.searchParams.set('seccion', '0');    // reset (secciones son server-side)
  window.location.href = url.toString();
});
</script>
<?php
$PAGE_SCRIPTS = ob_get_clean();

require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';

