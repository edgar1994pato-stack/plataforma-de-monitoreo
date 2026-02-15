<?php
/**
 * /vistas_pantallas/preguntas_formulario.php
 * =========================================================
 * FORMULARIO (UI) - CREAR / DUPLICAR PREGUNTAS
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();
$BASE_URL = BASE_URL;


function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* ===============================
   PERMISOS
=============================== */
if (is_readonly()) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "Modo solo lectura.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Acceso denegado: usuario en modo solo lectura.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
  exit;
}

if (!can_create()) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "No tiene permisos para crear/duplicar preguntas.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Acceso denegado: no tiene permisos para crear/duplicar preguntas.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
  exit;
}

$veTodo     = can_see_all_areas();
$idAreaSes  = (int)($_SESSION['id_area'] ?? 0);

if (!$veTodo && $idAreaSes <= 0) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "Usuario sin área asignada.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Tu usuario no tiene área asignada. Contacta al administrador.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
  exit;
}

/* ===============================
   MODO: crear / duplicar
=============================== */
$modo = strtolower(trim((string)($_GET['modo'] ?? 'crear')));
if (!in_array($modo, ['crear','duplicar'], true)) $modo = 'crear';

$id_origen = (int)($_GET['id'] ?? 0);
if ($modo === 'duplicar' && $id_origen <= 0) $modo = 'crear';

/* ===============================
   ÁREA EFECTIVA (forzada)
=============================== */
$idAreaGet = (int)($_GET['id_area'] ?? 0);
$id_area   = $veTodo ? $idAreaGet : $idAreaSes;
if ($id_area <= 0) $id_area = $idAreaSes;

/* ===============================
   Defaults del formulario
=============================== */
$form = [
  'id_area'     => $id_area,
  'id_cola'     => 0,
  'id_seccion'  => 0,
  'aspecto'     => '',
  'direccion'   => '',
  'tipo'        => 'NORMAL',
  'pregunta'    => '',
  'descripcion' => '',
  'gestion'     => '',
  'peso'        => '0.00',
];

$errores = [];

/* ===============================
   Si DUPLICAR: precarga PREGUNTAS + PONDERACION activa
=============================== */
if ($modo === 'duplicar') {
  try {
    $sql = "
      SELECT
        p.id_pregunta,
        p.id_area,
        p.id_cola,
        p.id_seccion,
        p.ASPECTO,
        p.DIRECCION,
        p.TIPO,
        p.pregunta,
        p.descripcion,
        po.GESTION AS gestion_activa,
        po.PESO    AS peso_activo
      FROM dbo.PREGUNTAS p
      OUTER APPLY (
          SELECT TOP 1 x.GESTION, x.PESO
          FROM dbo.PONDERACION x
          WHERE x.ID_PREGUNTA = p.id_pregunta
            AND x.ID_COLA     = p.id_cola
            AND ISNULL(x.ACTIVO,0) = 1
          ORDER BY x.FECHA_INICIO DESC
      ) po
      WHERE p.id_pregunta = ?
    ";
    $st = $conexion->prepare($sql);
    $st->execute([$id_origen]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $errores[] = "La pregunta origen no existe.";
      $modo = 'crear';
    } else {
      if (!$veTodo && (int)$row['id_area'] !== $idAreaSes) {
        http_response_code(403);
        $PAGE_TITLE = "⛔ Acceso denegado";
        $PAGE_SUBTITLE = "La pregunta origen no pertenece a tu área.";
        require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
        echo '<div class="alert alert-danger">Acceso denegado: la pregunta origen no pertenece a tu área.</div>';
        require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
        exit;
      }

      $form['id_area']     = (int)$row['id_area'];
      $form['id_cola']     = (int)$row['id_cola'];
      $form['id_seccion']  = (int)$row['id_seccion'];
      $form['aspecto']     = (string)($row['ASPECTO'] ?? '');
      $form['direccion']   = (string)($row['DIRECCION'] ?? '');
      $form['tipo']        = strtoupper((string)($row['TIPO'] ?? 'NORMAL'));
      $form['pregunta']    = (string)($row['pregunta'] ?? '');
      $form['descripcion'] = (string)($row['descripcion'] ?? '');
      $form['gestion']     = (string)($row['gestion_activa'] ?? '');
      $form['peso']        = number_format((float)($row['peso_activo'] ?? 0), 2, '.', '');

      $id_area = $form['id_area'];
    }
  } catch (Throwable $e) {
    $errores[] = "Error al cargar pregunta origen: " . $e->getMessage();
    $modo = 'crear';
  }
}

/* ===============================
   Cargar combos iniciales
=============================== */
$areas = $colas = $secciones = [];
$gestiones = [];

try {
  if ($veTodo) {
    $q = $conexion->query("
      DECLARE @HOY date = CONVERT(date, GETDATE());
      SELECT id_area, nombre_area
      FROM dbo.AREAS
      WHERE ISNULL(estado,1)=1
        AND CONVERT(date, fecha_activacion) <= @HOY
        AND (fecha_fin IS NULL OR CONVERT(date, fecha_fin) >= @HOY)
      ORDER BY nombre_area
    ");
    $areas = $q->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $st = $conexion->prepare("
      DECLARE @HOY date = CONVERT(date, GETDATE());
      SELECT id_area, nombre_area
      FROM dbo.AREAS
      WHERE id_area=?
        AND ISNULL(estado,1)=1
        AND CONVERT(date, fecha_activacion) <= @HOY
        AND (fecha_fin IS NULL OR CONVERT(date, fecha_fin) >= @HOY)
      ORDER BY nombre_area
    ");
    $st->execute([$idAreaSes]);
    $areas = $st->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $errores[] = "Error cargando áreas.";
}

try {
  $st = $conexion->prepare("
    DECLARE @HOY date = CONVERT(date, GETDATE());
    SELECT id_cola, nombre_cola
    FROM dbo.COLAS
    WHERE id_area = ?
      AND CONVERT(date, fecha_activacion) <= @HOY
      AND (fecha_fin IS NULL OR CONVERT(date, fecha_fin) >= @HOY)
    ORDER BY nombre_cola
  ");
  $st->execute([$id_area]);
  $colas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $errores[] = "Error cargando colas.";
}

try {
  $st = $conexion->prepare("
    SELECT id_seccion, nombre_seccion
    FROM dbo.SECCIONES
    WHERE id_area = ?
      AND ISNULL(estado,1)=1
    ORDER BY nombre_seccion
  ");
  $st->execute([$id_area]);
  $secciones = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $errores[] = "Error cargando secciones.";
}

try {
  if ((int)$form['id_cola'] > 0) {
    $st = $conexion->prepare("
      SELECT DISTINCT LTRIM(RTRIM(GESTION)) AS gestion
      FROM dbo.PONDERACION
      WHERE ID_COLA = ?
        AND ISNULL(ACTIVO,0)=1
        AND GESTION IS NOT NULL
        AND LTRIM(RTRIM(GESTION)) <> ''
      ORDER BY LTRIM(RTRIM(GESTION))
    ");
    $st->execute([(int)$form['id_cola']]);
    $gestiones = $st->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $errores[] = "Error cargando gestiones.";
}

/* Catálogos */
$aspectos    = ['ERROR NO CRITICO','ERROR CRITICO','IMPULSOR DE SATISFACCIÓN'];
$direcciones = ['USUARIO FINAL','NEGOCIO'];
$tipos       = ['NORMAL','CRITICO','IMPULSOR'];

/* Header UI */
$PAGE_TITLE    = "🧩 Módulo de Preguntas";
$PAGE_SUBTITLE = ($modo === 'duplicar')
  ? "Duplicar pregunta (crea una nueva)"
  : "Crear nueva pregunta";

$PAGE_ACTION_HTML = '
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a class="btn btn-outline-secondary btn-sm shadow-sm" href="'.$BASE_URL.'/vistas_pantallas/menu.php">
      <i class="bi bi-house"></i> Inicio
    </a>
    <a class="btn btn-outline-primary btn-sm shadow-sm" href="'.$BASE_URL.'/vistas_pantallas/listado_preguntas.php">
      <i class="bi bi-list-check"></i> Listado
    </a>
    <a class="btn btn-outline-danger btn-sm shadow-sm" href="'.$BASE_URL.'/cruds/logout.php">
      <i class="bi bi-box-arrow-right"></i> Salir
    </a>
  </div>
';

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<?php if (!empty($errores)): ?>
  <div class="alert alert-danger">
    <b>⚠️ Observaciones:</b>
    <ul class="mb-0 mt-1">
      <?php foreach ($errores as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($modo === 'duplicar' && $id_origen > 0): ?>
  <div class="alert alert-warning">
    <b>Nota:</b> Esto creará una <b>nueva</b> pregunta. La anterior no se modifica.
  </div>
<?php endif; ?>

<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
    <div><i class="bi bi-ui-checks-grid me-2"></i><?= ($modo === 'duplicar') ? 'Duplicar pregunta' : 'Crear pregunta' ?></div>
    <div class="small opacity-75">Campos obligatorios: todos excepto <b>Descripción</b></div>
  </div>

  <div class="card-body">
    <form id="frmPregunta" method="POST"
          action="<?= h($BASE_URL) ?>/cruds/proceso_guardar_preguntas.php"
          autocomplete="off" novalidate>

      <input type="hidden" name="accion" value="CREAR">
      <input type="hidden" name="modo" value="<?= h($modo) ?>">
      <?php if ($modo === 'duplicar' && $id_origen > 0): ?>
        <input type="hidden" name="id_origen" value="<?= (int)$id_origen ?>">
      <?php endif; ?>

      <div class="row g-3">

        <!-- Área -->
        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">ÁREA <span class="text-danger">*</span></label>
          <select class="form-select form-select-sm" name="id_area" id="id_area" <?= $veTodo ? '' : 'disabled' ?> required>
            <option value="">Seleccione...</option>
            <?php foreach ($areas as $a): ?>
              <?php $idA = (int)$a['id_area']; $sel = ((int)$form['id_area'] === $idA) ? 'selected' : ''; ?>
              <option value="<?= $idA ?>" <?= $sel ?>><?= h($a['nombre_area']) ?></option>
            <?php endforeach; ?>
          </select>

          <?php if (!$veTodo): ?>
            <input type="hidden" name="id_area" value="<?= (int)$form['id_area'] ?>">
          <?php endif; ?>
        </div>

        <!-- Cola -->
        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">COLA <span class="text-danger">*</span></label>
          <select class="form-select form-select-sm" name="id_cola" id="id_cola" required>
            <option value="">Seleccione...</option>
            <?php foreach ($colas as $c): ?>
              <?php $idC = (int)$c['id_cola']; $sel = ((int)$form['id_cola'] === $idC) ? 'selected' : ''; ?>
              <option value="<?= $idC ?>" <?= $sel ?>><?= h($c['nombre_cola']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Sección -->
        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">SECCIÓN <span class="text-danger">*</span></label>
          <select class="form-select form-select-sm" name="id_seccion" id="id_seccion" required>
            <option value="">Seleccione...</option>
            <?php foreach ($secciones as $s): ?>
              <?php $idS = (int)$s['id_seccion']; $sel = ((int)$form['id_seccion'] === $idS) ? 'selected' : ''; ?>
              <option value="<?= $idS ?>" <?= $sel ?>><?= h($s['nombre_seccion']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Aspecto -->
        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">ASPECTO <span class="text-danger">*</span></label>
          <select class="form-select form-select-sm" name="aspecto" id="aspecto" required>
            <option value="">Seleccione...</option>
            <?php foreach ($aspectos as $x): ?>
              <?php $sel = ($form['aspecto'] === $x) ? 'selected' : ''; ?>
              <option value="<?= h($x) ?>" <?= $sel ?>><?= h($x) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Dirección -->
        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">DIRECCIÓN <span class="text-danger">*</span></label>
          <select class="form-select form-select-sm" name="direccion" id="direccion" required>
            <option value="">Seleccione...</option>
            <?php foreach ($direcciones as $x): ?>
              <?php $sel = ($form['direccion'] === $x) ? 'selected' : ''; ?>
              <option value="<?= h($x) ?>" <?= $sel ?>><?= h($x) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Tipo -->
        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">TIPO <span class="text-danger">*</span></label>
          <select class="form-select form-select-sm" name="tipo" id="tipo" required>
            <option value="">Seleccione...</option>
            <?php foreach ($tipos as $x): ?>
              <?php $sel = (strtoupper($form['tipo']) === $x) ? 'selected' : ''; ?>
              <option value="<?= h($x) ?>" <?= $sel ?>><?= h($x) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Peso -->
        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">PESO (%) <span class="text-danger">*</span></label>
          <input type="number" class="form-control form-control-sm"
                 name="peso" id="peso"
                 min="0.01" max="100" step="0.01"
                 value="<?= h($form['peso']) ?>" required>
          <div class="help-mini">Obligatorio. Se guarda en <b>PONDERACION.PESO</b>.</div>
        </div>

        <!-- Gestión -->
        <div class="col-md-6">
          <label class="form-label small fw-bold text-muted">GESTIÓN <span class="text-danger">*</span></label>

          <select class="form-select form-select-sm" id="gestion_select">
            <option value="">Seleccione...</option>
            <?php foreach ($gestiones as $g): ?>
              <?php $val = (string)($g['gestion'] ?? ''); $sel = ($form['gestion'] === $val) ? 'selected' : ''; ?>
              <option value="<?= h($val) ?>" <?= $sel ?>><?= h($val) ?></option>
            <?php endforeach; ?>
            <option value="__OTRA__">Otra...</option>
          </select>

          <input type="text" class="form-control form-control-sm mt-2"
                 name="gestion" id="gestion"
                 maxlength="300"
                 value="<?= h($form['gestion']) ?>"
                 placeholder="Escriba la gestión..." required>

          <div class="help-mini">Obligatorio. Se guarda en <b>PONDERACION.GESTION</b>.</div>
        </div>

        <!-- Pregunta -->
        <div class="col-12">
          <label class="form-label small fw-bold text-muted">PREGUNTA <span class="text-danger">*</span></label>
          <input type="text" class="form-control form-control-sm"
                 name="pregunta" id="pregunta"
                 maxlength="500" minlength="10"
                 value="<?= h($form['pregunta']) ?>"
                 required
                 placeholder="Ej: ¿Cumple con el protocolo X?">
          <div class="help-mini">Mínimo 10 caracteres.</div>
        </div>

        <!-- Descripción -->
        <div class="col-12">
          <label class="form-label small fw-bold text-muted">DESCRIPCIÓN (opcional)</label>
          <textarea class="form-control form-control-sm"
                    name="descripcion" id="descripcion"
                    rows="3"
                    maxlength="1000"
                    placeholder="Criterios, ejemplos, condiciones..."><?= h($form['descripcion']) ?></textarea>
        </div>

      </div>

      <hr class="my-4">

      <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary btn-sm fw-bold shadow-sm" id="btnGuardar">
          <?= ($modo === 'duplicar') ? 'Crear nueva (duplicada)' : 'Crear pregunta' ?>
        </button>

        <a class="btn btn-outline-secondary btn-sm shadow-sm"
           href="<?= h($BASE_URL) ?>/vistas_pantallas/listado_preguntas.php<?= $id_area ? ('?id_area='.(int)$id_area) : '' ?>">
          Cancelar
        </a>
      </div>

    </form>
  </div>
</div>

<?php
ob_start();
?>
<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;

const elArea    = document.getElementById('id_area');
const elCola    = document.getElementById('id_cola');
const elSeccion = document.getElementById('id_seccion');

const elGestionSelect = document.getElementById('gestion_select');
const elGestion       = document.getElementById('gestion');


const elAspecto   = document.getElementById('aspecto');
const elDireccion = document.getElementById('direccion');

elGestion.readOnly = true;

function generarGestionAutomatica() {

  const aspecto   = (elAspecto?.value || '').trim();
  const direccion = (elDireccion?.value || '').trim();

  if (!aspecto || !direccion) {
    elGestion.value = '';
    return;
  }

  const mapaAspecto = {
    'IMPULSOR DE SATISFACCIÓN': 'IMP',
    'ERROR CRITICO': 'PEC',
    'ERROR NO CRITICO': 'ENC'
  };

  const mapaDireccion = {
    'USUARIO FINAL': 'UF',
    'CUMPLIMIENTO': 'CUM',
    'NEGOCIO': 'NEG'
  };

  const prefijo = mapaAspecto[aspecto] || '';
  const sufijo  = mapaDireccion[direccion] || '';

  elGestion.value = (prefijo && sufijo) ? `${prefijo} - ${sufijo}` : '';
}

elAspecto?.addEventListener('change', generarGestionAutomatica);
elDireccion?.addEventListener('change', generarGestionAutomatica);

generarGestionAutomatica();



function resetSelect(sel, placeholder='Seleccione...') {
  sel.innerHTML = '';
  const opt = document.createElement('option');
  opt.value = '';
  opt.textContent = placeholder;
  sel.appendChild(opt);
}

async function loadOptions(url, selectEl, valueKey, labelKey) {
  resetSelect(selectEl);
  selectEl.disabled = true;

  try {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    data.forEach(it => {
      const opt = document.createElement('option');
      opt.value = it[valueKey];
      opt.textContent = it[labelKey];
      selectEl.appendChild(opt);
    });
  } catch (e) {
    console.error('Error cargando opciones:', url, e);
  } finally {
    selectEl.disabled = false;
  }
}

async function cargarColasYSecciones(idArea) {
  if (!idArea) {
    resetSelect(elCola);
    resetSelect(elSeccion);
    resetSelect(elGestionSelect);
    elGestion.value = '';
    return;
  }

  await loadOptions(`${BASE_URL}/cruds/servidor_filtros.php?tipo=colas&id_area=${encodeURIComponent(idArea)}`, elCola, 'id_cola', 'nombre_cola');
  await loadOptions(`${BASE_URL}/cruds/servidor_filtros.php?tipo=secciones&id_area=${encodeURIComponent(idArea)}`, elSeccion, 'id_seccion', 'nombre_seccion');

  resetSelect(elGestionSelect);
  const opt = document.createElement('option');
  opt.value = '__OTRA__';
  opt.textContent = 'Otra...';
  elGestionSelect.appendChild(opt);

  elGestion.value = '';
}

async function cargarGestiones(idCola) {
  resetSelect(elGestionSelect);
  if (!idCola) {
    const opt = document.createElement('option');
    opt.value = '__OTRA__';
    opt.textContent = 'Otra...';
    elGestionSelect.appendChild(opt);
    return;
  }

  await loadOptions(`${BASE_URL}/cruds/servidor_filtros.php?tipo=gestiones&id_cola=${encodeURIComponent(idCola)}`, elGestionSelect, 'gestion', 'gestion');

  const opt = document.createElement('option');
  opt.value = '__OTRA__';
  opt.textContent = 'Otra...';
  elGestionSelect.appendChild(opt);
}

if (elArea && !elArea.disabled) {
  elArea.addEventListener('change', async () => {
    await cargarColasYSecciones(elArea.value || '');
  });
}

if (elCola) {
  elCola.addEventListener('change', async () => {
    await cargarGestiones(elCola.value || '');
    generarGestionAutomatica(); // en vez de limpiar
  });
}

if (elGestionSelect && elGestion) {
  elGestionSelect.addEventListener('change', () => {
    const v = (elGestionSelect.value || '').trim();
    if (!v) return;

    if (v === '__OTRA__') {
      elGestion.focus();
      elGestion.select();
      return;
    }
    elGestion.value = v;
  });
}

document.getElementById('frmPregunta')?.addEventListener('submit', (e) => {
  const preg = (document.getElementById('pregunta')?.value || '').trim();
  const peso = parseFloat((document.getElementById('peso')?.value || '0').toString());
  const gestion = (document.getElementById('gestion')?.value || '').trim();

  if (preg.length < 10) {
    alert('❌ La pregunta debe tener mínimo 10 caracteres.');
    e.preventDefault();
    return;
  }
  if (!(peso > 0)) {
    alert('❌ El peso debe ser mayor a 0.');
    e.preventDefault();
    return;
  }
  if (!gestion) {
    alert('❌ Gestión es obligatoria.');
    e.preventDefault();
    return;
  }
});
</script>
<?php
$PAGE_SCRIPTS = ob_get_clean();
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';

?>
