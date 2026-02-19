<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

function h($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$BASE_URL = BASE_URL;

/* =============================
   SESIÓN Y PERMISOS
============================= */
$idAreaSesion = (int)($_SESSION['id_area'] ?? 0);

$veTodo      = can_see_all_areas();
$puedeCrear  = can_create();
$soloLectura = is_readonly();

/* =============================
   FILTROS GET
============================= */
$idAreaGet  = isset($_GET['area']) ? (int)$_GET['area'] : 0;
$idColaGet  = isset($_GET['cola']) ? (int)$_GET['cola'] : 0;
$estadoGet  = strtoupper(trim($_GET['estado'] ?? 'ACTIVOS'));

$estadoParam = null;
if ($estadoGet === 'ACTIVOS') {
    $estadoParam = 1;
} elseif ($estadoGet === 'INACTIVOS') {
    $estadoParam = 0;
}

/* Área backend */
$idAreaParam = $veTodo
    ? ($idAreaGet > 0 ? $idAreaGet : null)
    : ($idAreaSesion > 0 ? $idAreaSesion : null);

$idColaParam = $idColaGet > 0 ? $idColaGet : null;

/* =============================
   CARGAR ÁREAS
============================= */
$areas = [];
try {
    if ($veTodo) {
        $st = $conexion->query("SELECT id_area, nombre_area FROM dbo.AREAS WHERE estado=1 ORDER BY nombre_area");
        $areas = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $conexion->prepare("SELECT id_area, nombre_area FROM dbo.AREAS WHERE id_area=?");
        $st->execute([$idAreaSesion]);
        $areas = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Throwable $e){}

/* =============================
   EJECUTAR SP
============================= */
$rows = [];
$errorListado = '';

try {
    $stmt = $conexion->prepare("EXEC dbo.PR_LISTAR_AGENTES ?, ?, ?");
    $stmt->execute([$idAreaParam, $idColaParam, $estadoParam]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){
    $errorListado = $e->getMessage();
}

/* =============================
   HEADER
============================= */
$PAGE_TITLE = "👥 Módulo de Agentes";
$PAGE_SUBTITLE = "";

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<!-- ===================== NAVEGACIÓN ===================== -->
<div class="mb-3 d-flex justify-content-between align-items-center">

  <div>
    <a href="<?= h($BASE_URL) ?>/vistas_pantallas/menu.php"
       class="btn btn-soft btn-sm shadow-sm">
       <i class="bi bi-house-door"></i> Menú principal
    </a>
  </div>

  <div>
    <?php if($puedeCrear && !$soloLectura): ?>
      <a href="<?= h($BASE_URL) ?>/vistas_pantallas/agente_formulario.php"
         class="btn btn-primary btn-sm shadow-sm">
         <i class="bi bi-plus-circle"></i> Nuevo agente
      </a>
    <?php endif; ?>
  </div>

</div>


<!-- ===================== FILTROS ===================== -->
<div class="card card-soft mb-3">
  <div class="card-header card-header-dark py-2 small d-flex justify-content-between align-items-center">
    <div><i class="bi bi-funnel me-2"></i> Filtros</div>
    <div class="small opacity-75">Resultados: <b><?= count($rows) ?></b></div>
  </div>

  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">

      <div class="col-md-4">
        <label class="form-label small fw-bold text-muted">ÁREA</label>
        <select class="form-select form-select-sm" name="area" <?= $veTodo ? '' : 'disabled' ?>>
          <option value="0">Todas</option>
          <?php foreach($areas as $a): ?>
            <option value="<?= (int)$a['id_area'] ?>"
              <?= $idAreaGet == $a['id_area'] ? 'selected' : '' ?>>
              <?= h($a['nombre_area']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if(!$veTodo): ?>
          <input type="hidden" name="area" value="<?= $idAreaSesion ?>">
        <?php endif; ?>
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-bold text-muted">ESTADO</label>
        <select class="form-select form-select-sm" name="estado">
          <option value="TODOS" <?= $estadoGet === 'TODOS' ? 'selected' : '' ?>>TODOS</option>
          <option value="ACTIVOS" <?= $estadoGet === 'ACTIVOS' ? 'selected' : '' ?>>ACTIVOS</option>
          <option value="INACTIVOS" <?= $estadoGet === 'INACTIVOS' ? 'selected' : '' ?>>INACTIVOS</option>
        </select>
      </div>

      <div class="col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm">
          <i class="bi bi-search"></i> Buscar
        </button>

        <a class="btn btn-soft btn-sm w-100 shadow-sm"
           href="<?= h($BASE_URL) ?>/vistas_pantallas/listado_agentes.php">
           <i class="bi bi-x-circle"></i> Limpiar
        </a>
      </div>

    </form>
  </div>
</div>

<?php if($errorListado !== ''): ?>
<div class="alert alert-danger">
  <?= h($errorListado) ?>
</div>
<?php endif; ?>

<!-- ===================== TABLA ===================== -->
<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small">
    <i class="bi bi-table me-2"></i> Listado de agentes
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead>
          <tr class="small text-muted">
            <th>Nombre</th>
            <th>Área</th>
            <th>Cola</th>
            <th>Supervisor</th>
            <th>Estado</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>

        <tbody>
        <?php if(count($rows) === 0): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">
              No hay registros.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><?= h($r['nombre_agente']) ?></td>
            <td><?= h($r['nombre_area']) ?></td>
            <td><?= h($r['nombre_cola']) ?></td>
            <td><?= h($r['nombre_supervisor']) ?></td>

            <td>
              <?php
                $estadoOp = strtoupper($r['estado_operativo'] ?? 'ACTIVO');

                if ($estadoOp === 'ACTIVO'):
              ?>
                <span class="badge-estado badge-activo">ACTIVO</span>

              <?php elseif ($estadoOp === 'EN VACACIONES'): ?>
                <span class="badge-estado badge-vacaciones">EN VACACIONES</span>

              <?php else: ?>
                <span class="badge-estado badge-inactivo">INACTIVO</span>
              <?php endif; ?>
            </td>

            <td class="text-center">
              <div class="d-flex justify-content-center gap-2">

 

                <?php if(!$soloLectura): ?>
                  <a href="<?= h($BASE_URL) ?>/vistas_pantallas/gestionar_agente.php?id=<?= (int)$r['id_agente_int'] ?>"
                     class="btn btn-primary btn-sm">
                     Gestionar
                  </a>
                <?php endif; ?>

              </div>
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
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
