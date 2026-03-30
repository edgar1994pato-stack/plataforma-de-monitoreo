<?php
/**
 * /vistas_pantallas/listado_monitoreos.php
 * =========================================================
 * LISTADO OFICIAL DE MONITOREOS
 *
 * ✅ Diseño unificado (diseño_arriba / diseño_abajo)
 * ✅ Protegido por sesión (seguridad.php)
 * ✅ Filtros GET: fi, ff, area, cola, agente, sv
 *
 * ✅ Seguridad backend por rol (CENTRALIZADA):
 *    - can_see_all_areas(): si ve todo, puede filtrar por área
 *    - si NO ve todo: SIEMPRE se fuerza el área de sesión
 *
 * ✅ Acciones:
 *    - PDF: siempre disponible
 *    - Modificar: solo si can_correct() (seguridad.php)
 *
 * ✅ Importante:
 * - SP utilizado: dbo.PR_LISTAR_MONITOREOS
 * - Se usa EXEC con placeholders (?) por compatibilidad SQL Server + PDO
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';


/* =========================================================
 * 1) SEGURIDAD CENTRALIZADA
 * ========================================================= */
require_login();
force_password_change();
require_permission('ver_modulo_monitoreos');

/* =========================================================
 * 2) HELPERS
 * ========================================================= */
function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* =========================================================
 * 3) BASE URL
 * ========================================================= */

$BASE_URL = BASE_URL;
/* =========================================================
 * 4) SESIÓN
 * ========================================================= */
$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
$idRol     = (int)($_SESSION['id_rol'] ?? 0);


$areasSesion = $_SESSION['areas'] ?? [];
$idAreaSes = (!empty($areasSesion)) ? implode(',', $areasSesion) : null;

/* =========================================================
 * 5) PERMISOS (VIENEN SOLO DE seguridad.php)
 * ========================================================= */
$esRolVeTodo    = can_see_all_areas(); // centralizado
$puedeModificar = can_correct();       // centralizado

/* Si NO ve todo, debe tener área asignada */
if (!$esRolVeTodo && empty($areasSesion)) {
    http_response_code(403);
    die('Tu usuario no tiene un área asignada. Contacta al administrador.');
}

/* =========================================================
 * 6) NOMBRE COMPLETO SESIÓN
 * ========================================================= */
$nombreUsuarioSesion = (string)($_SESSION['nombre_completo'] ?? '');
if ($nombreUsuarioSesion === '') {
    try {
        $qU = $conexion->prepare("SELECT TOP 1 nombre_completo FROM dbo.USUARIOS WHERE id_usuario = ?");
        $qU->execute([$idUsuario]);
        $nombreUsuarioSesion = (string)($qU->fetchColumn() ?: 'Usuario');
        $_SESSION['nombre_completo'] = $nombreUsuarioSesion;
    } catch (Throwable $e) {
        $nombreUsuarioSesion = 'Usuario';
    }
}

/* =========================================================
 * 7) HEADER (DISEÑO)
 * ========================================================= */
$PAGE_TITLE    = "📄 Listado de Monitoreos";
$PAGE_SUBTITLE = "";

$PAGE_ACTION_HTML = '
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a class="btn btn-outline-secondary btn-sm shadow-sm" href="'.h(BASE_URL).'/vistas_pantallas/menu.php">
      <i class="bi bi-house"></i> Inicio
    </a>

    <a class="btn btn-outline-danger btn-sm shadow-sm" href="'.h(BASE_URL).'/cruds/logout.php">
      <i class="bi bi-box-arrow-right"></i> Salir
    </a>
  </div>
';


/* =========================================================
 * 8) FILTROS (GET)
 * ========================================================= */
$hoy          = date('Y-m-d');
$primerDiaMes = date('Y-m-01');
$ultimoDiaMes = date('Y-m-t');

$fechaInicio = trim((string)($_GET['fi'] ?? $primerDiaMes));
$fechaFin = trim((string)($_GET['ff'] ?? $hoy));


$idAreaGet   = isset($_GET['area']) ? (int)$_GET['area'] : 0;
$idColaGet   = isset($_GET['cola']) ? (int)$_GET['cola'] : 0;
$idAgenteGet = isset($_GET['agente']) ? (int)$_GET['agente'] : 0;
$idMonitoreoGet = isset($_GET['mon']) ? (int)$_GET['mon'] : 0; //
$gestorGet = trim((string)($_GET['gestor'] ?? ''));


/* sv: 0 = solo vigente, 1 = incluye sin vigente */
$incluirSinVigente = isset($_GET['sv']) ? (int)$_GET['sv'] : 0;

if ($fechaInicio === '') $fechaInicio = $primerDiaMes;
if ($fechaFin === '')    $fechaFin    = $ultimoDiaMes;

/* =========================================================
 * 9) CONTROL DE ÁREA POR ROL (SEGURIDAD BACKEND CENTRALIZADA)
 * ========================================================= */
$idAreaParam = null;

// 🔹 Rol global: mantiene comportamiento actual
if ($esRolVeTodo) {

    $idAreaParam = ($idAreaGet > 0) ? $idAreaGet : null;

} else {

    $totalAreas = count($areasSesion);

    // 🔹 Caso 1: usuario con UNA sola área
    if ($totalAreas === 1) {

        $idAreaParam = (int)$areasSesion[0];

    } else {

        // 🔹 Caso 2: usuario con MÚLTIPLES áreas
        if ($idAreaGet > 0 && in_array($idAreaGet, $areasSesion)) {

            // Usa solo el área seleccionada
            $idAreaParam = $idAreaGet;

        } else {

            // Usa todas las áreas asignadas al usuario
            $idAreaParam = implode(',', $areasSesion);

        }
    }
}

// 🔹 Otros filtros (NO TOCAR)
$idColaParam   = ($idColaGet > 0) ? $idColaGet : null;
$idAgenteParam = ($idAgenteGet > 0) ? $idAgenteGet : null;
$svParam       = ($incluirSinVigente === 1) ? 1 : 0;
$idMonitoreoParam = ($idMonitoreoGet > 0) ? $idMonitoreoGet : null;
$gestorParam = ($gestorGet !== '') ? $gestorGet : null;

/* =========================================================
 * 10) CARGA DE ÁREAS (SELECT)
 * ========================================================= */
$areas = [];
try {
    if ($esRolVeTodo) {

        $res = $conexion->query("
            SELECT id_area, nombre_area 
            FROM dbo.AREAS 
            WHERE estado=1 
            ORDER BY nombre_area
        ");
        $areas = $res->fetchAll(PDO::FETCH_ASSOC);

    } else {

        $areasSesion = $_SESSION['areas'] ?? [];

        if (!empty($areasSesion)) {

            $placeholders = implode(',', array_fill(0, count($areasSesion), '?'));

            $stmtA = $conexion->prepare("
                SELECT id_area, nombre_area 
                FROM dbo.AREAS 
                WHERE estado=1 
                AND id_area IN ($placeholders)
                ORDER BY nombre_area
            ");

            $stmtA->execute($areasSesion);
            $areas = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $areas = [];
        }

    }
} catch (Throwable $e) {
    $areas = [];
}
/* =========================================================
 * 11) EJECUTAR SP LISTADO
 * ========================================================= */
$rows = [];
$errorListado = '';

try {
    // SP: dbo.PR_LISTAR_MONITOREOS(@FechaInicio,@FechaFin,@IdArea,@IdCola,@IdAgente,@IncluirSinVigente)
    $sql  = "EXEC dbo.PR_LISTAR_MONITOREOS ?, ?, ?, ?, ?, ?, ?, ?";

    $stmt = $conexion->prepare($sql);

$stmt->execute([
    $fechaInicio,
    $fechaFin,
    $idAreaParam,
    $idColaParam,
    $idAgenteParam,
    $svParam,
    $idMonitoreoParam,
    $gestorParam
]);


    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $errorListado = $e->getMessage();
    $rows = [];
}

/* =========================================================
 * 12) INCLUDE HEADER
 * ========================================================= */
require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';


?>

<!-- =========================================================
     FILTROS
========================================================= -->
<div class="card card-soft mb-3">
  <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
    <div><i class="bi bi-funnel me-2"></i>Filtros</div>
    <div class="small opacity-75">Resultados: <b><?= (int)count($rows) ?></b></div>
  </div>

  <div class="card-body">
    <form method="GET" action="<?= h($BASE_URL) ?>/vistas_pantallas/listado_monitoreos.php" class="row g-2 align-items-end">
         
    <div class="col-12 col-md-3">
    <label class="form-label small fw-bold text-muted">N° MONITOREO</label>
    <input type="number"
           class="form-control form-control-sm"
           name="mon"
           value="<?= $idMonitoreoGet > 0 ? h($idMonitoreoGet) : '' ?>"
           placeholder="Ej: 1025">
  </div>


      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">FECHA INICIO</label>
        <input type="date" class="form-control form-control-sm" name="fi" value="<?= h($fechaInicio) ?>" max="<?= h($hoy) ?>">
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">FECHA FIN</label>
        <input type="date" class="form-control form-control-sm" name="ff" value="<?= h($fechaFin) ?>" max="<?= h($hoy) ?>">
      </div>

<div class="col-12 col-md-3">
  <label class="form-label small fw-bold text-muted">ÁREA</label>

  <?php
  $totalAreas    = count($areasSesion);
  $disableSelect = (!$esRolVeTodo && $totalAreas === 1);
  ?>

  <select class="form-select form-select-sm"
          name="area"
          id="f_area"
          <?= $disableSelect ? 'disabled' : '' ?>>

    <option value="0" <?= ($idAreaGet === 0 ? 'selected' : '') ?>>
      <?= $esRolVeTodo ? 'Todas' : 'Mis áreas' ?>
    </option>

    <?php foreach ($areas as $a): ?>
      <?php
        $idA = (int)$a['id_area'];

        if ($esRolVeTodo) {
            $sel = ($idAreaGet === $idA) ? 'selected' : '';
        } elseif ($totalAreas === 1) {
            $sel = ((int)$areasSesion[0] === $idA) ? 'selected' : '';
        } else {
            $sel = ($idAreaGet === $idA) ? 'selected' : '';
        }
      ?>
      <option value="<?= $idA ?>" <?= $sel ?>>
        <?= h($a['nombre_area']) ?>
      </option>
    <?php endforeach; ?>

  </select>

  <?php if (!$esRolVeTodo && $totalAreas === 1): ?>
    <input type="hidden" name="area" value="<?= (int)$areasSesion[0] ?>">
  <?php endif; ?>
</div>

      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">COLA</label>
        <select class="form-select form-select-sm" name="cola" id="f_cola">
          <option value="0">Todas</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">AGENTE</label>
        <select class="form-select form-select-sm" name="agente" id="f_agente">
          <option value="0">Todos</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
  <label class="form-label small fw-bold text-muted">AGENTE DE MONITOREO</label>
  <select class="form-select form-select-sm" name="gestor">
    <option value="">Todos</option>

    <?php
    try {
        $stmtG = $conexion->query("
            SELECT DISTINCT gestor_monitoreo
            FROM dbo.MONITOREO_VERSION
            WHERE estado_version <> 'ELIMINADO'
            ORDER BY gestor_monitoreo
        ");
        $gestores = $stmtG->fetchAll(PDO::FETCH_COLUMN);

        foreach ($gestores as $g):
            $selected = ($gestorGet === $g) ? 'selected' : '';
    ?>
        <option value="<?= h($g) ?>" <?= $selected ?>>
            <?= h($g) ?>
        </option>
    <?php endforeach; ?>
    <?php } catch (Throwable $e) {} ?>
  </select>
</div>






      <!-- ✅ Checkbox para incluir SIN VIGENTE -->
      <div class="col-12 col-md-3">
        <label class="form-label small fw-bold text-muted">INCLUIR SIN VIGENTE</label>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="sv" value="1" id="sv"
                 <?= ($incluirSinVigente === 1 ? 'checked' : '') ?>>
          <label class="form-check-label small" for="sv">Sí</label>
        </div>
      </div>

      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm">
          <i class="bi bi-search"></i> Buscar
        </button>

        <a class="btn btn-outline-secondary btn-sm w-100 shadow-sm"
           href="<?= h($BASE_URL) ?>/vistas_pantallas/listado_monitoreos.php">
          <i class="bi bi-x-circle"></i> Limpiar
        </a>
      </div>

    </form>
  </div>
</div>

<?php if ($errorListado !== ''): ?>
  <div class="alert alert-danger">
    <b>❌ Error al listar monitoreos:</b>
    <div class="small mt-1"><?= h($errorListado) ?></div>
  </div>
<?php endif; ?>

<!-- =========================================================
     TABLA RESULTADOS
========================================================= -->
<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
    <div><i class="bi bi-table me-2"></i>Resultados</div>
    <div class="small opacity-75">Rango: <b><?= h($fechaInicio) ?></b> → <b><?= h($fechaFin) ?></b></div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr class="small text-muted">
            <th style="width:110px;">Monitoreo</th>

            <th style="width:170px;">Interacción</th>
            <th style="width:170px;">Fecha</th>
            <th>Agente</th>
            <th>Cola</th>
            <th>Agente de Monitoreo</th>
            <th style="width:110px;">Nota</th>
            <th style="width:130px;">Estado</th>
            <th style="width:220px;" class="text-end">Acciones</th>
          </tr>
        </thead>

        <tbody>
        <?php if (count($rows) === 0): ?>
          <tr>
            <td colspan="9" class="text-center text-muted py-4">
              <i class="bi bi-inbox fs-3 opacity-25"></i>
              <div class="mt-2">No hay monitoreos para los filtros seleccionados.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $idAccion = (int)($r['id_version'] ?? 0);

              $ref = trim((string)($r['id_interaccion'] ?? ''));
              if ($ref === '') $ref = '—';

              $nota   = (float)($r['porcentaje_resultado'] ?? 0);
              $estado = strtoupper(trim((string)($r['estado_final'] ?? '')));

              // ✅ CAMBIO CLAVE: badge por ESTADO REAL (umbral dinámico ya aplicado en SP)
              $badgeNota = ($estado === 'APROBADO') ? 'bg-success' : 'bg-danger';

              // indicador simple de corrección
              $numeroVersion = (int)($r['numero_version'] ?? 1);
              $esCorregido   = ($numeroVersion > 1);

              $urlPdf       = $BASE_URL . "/cruds/generar_pdf_monitoreo.php?id=" . $idAccion . "&t=" . time();
              $urlModificar = $BASE_URL . "/vistas_pantallas/corregir_monitoreo.php?id=" . $idAccion;
            ?>
            <tr>
<td class="fw-bold text-primary">
  #<?= (int)($r['id_origen'] ?? 0) ?>

</td>

<td class="fw-bold"><?= h($ref) ?></td>
<td><?= h($r['fecha_registro'] ?? '') ?></td>
<td><?= h($r['nombre_agente'] ?? '-') ?></td>
<td><?= h($r['nombre_cola'] ?? '-') ?></td>
<td><?= h($r['auditor'] ?? '-') ?></td>


              <td>
                <span class="badge <?= $badgeNota ?>"><?= number_format($nota, 1) ?>%</span>
                <?php if ($esCorregido): ?>
                  <span class="badge bg-warning text-dark ms-1">CORREGIDO</span>
                <?php endif; ?>
              </td>

              <td><?= h($estado) ?></td>

              <td class="text-end">
                <a class="btn btn-outline-danger btn-sm"
                   href="<?= h($urlPdf) ?>"
                   target="_blank" rel="noopener">
                  <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>

                <?php if ($puedeModificar): ?>
                  <a class="btn btn-outline-secondary btn-sm"
                     href="<?= h($urlModificar) ?>">
                    <i class="bi bi-pencil-square"></i> Modificar
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
 * 13) SCRIPTS (AJAX: carga colas/agentes)
 * ========================================================= */
ob_start();
?>
<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;

const fArea   = document.getElementById('f_area');
const fCola   = document.getElementById('f_cola');
const fAgente = document.getElementById('f_agente');

const selectedCola   = <?= (int)$idColaGet ?>;
const selectedAgente = <?= (int)$idAgenteGet ?>;

function getAreaActual(){
  const v = parseInt((fArea?.value || '0'), 10);
  return isNaN(v) ? 0 : v;
}

async function cargarColas(idArea){
  fCola.innerHTML = '<option value="0">Todas</option>';
  if(!idArea || idArea <= 0) return;

  try{
    const r = await fetch(`${BASE_URL}/cruds/servidor_filtros.php?tipo=colas&id_area=${idArea}`);
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

async function cargarAgentes(idArea){
  fAgente.innerHTML = '<option value="0">Todos</option>';
  if(!idArea || idArea <= 0) return;

  try{
    const r = await fetch(`${BASE_URL}/cruds/servidor_filtros.php?tipo=agentes&id_area=${idArea}`);
    const data = await r.json();

    data.forEach(a => {
      const opt = document.createElement('option');
      opt.value = a.id_agente_int;
      opt.textContent = a.nombre_agente;
      if(parseInt(opt.value,10) === selectedAgente) opt.selected = true;
      fAgente.appendChild(opt);
    });
  }catch(e){
    console.error('Error cargando agentes:', e);
  }
}

// Inicial
const areaInicial = getAreaActual();
cargarColas(areaInicial);
cargarAgentes(areaInicial);

// Solo si el select está habilitado (rol ve todo) se escucha change
fArea?.addEventListener('change', () => {
  const idArea = getAreaActual();

  const url = new URL(window.location.href);
  url.searchParams.set('area', idArea > 0 ? String(idArea) : '0');
  url.searchParams.set('cola', '0');
  url.searchParams.set('agente', '0');
  window.history.replaceState({}, '', url.toString());

  cargarColas(idArea);
  cargarAgentes(idArea);
});
</script>
<?php
$PAGE_SCRIPTS = ob_get_clean();

require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';

?>
