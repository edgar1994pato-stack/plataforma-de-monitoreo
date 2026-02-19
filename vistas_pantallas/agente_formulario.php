<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

function h($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
$BASE_URL = BASE_URL;

/* =============================
   CONFIG SP
============================= */
$SP_OBTENER = 'PR_OBTENER_AGENTE';

/* =============================
   SESIÓN Y PERMISOS
============================= */
$idAreaSesion = (int)($_SESSION['id_area'] ?? 0);
$veTodo       = can_see_all_areas();
$soloLectura  = is_readonly();
$puedeCrear   = can_create();
$puedeEditar  = function_exists('can_edit') ? can_edit() : true;

if ($soloLectura) {
  http_response_code(403);
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Modo solo lectura.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
  exit;
}

/* =============================
   MODO (crear / editar)
============================= */
$idAgente  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$esEdicion = $idAgente > 0;

if ($esEdicion && !$puedeEditar) {
  header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
  exit;
}

if (!$esEdicion && !$puedeCrear) {
  header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
  exit;
}

/* =============================
   CARGAR ÁREAS Y SUCURSALES
============================= */
$areas = [];
$sucursales = [];

try {

  // ÁREAS
  if ($veTodo) {
    $st = $conexion->query("
      SELECT id_area, nombre_area
      FROM dbo.AREAS
      WHERE estado = 1
      ORDER BY nombre_area
    ");
  } else {
    $st = $conexion->prepare("
      SELECT id_area, nombre_area
      FROM dbo.AREAS
      WHERE id_area = ?
    ");
    $st->execute([$idAreaSesion]);
  }

  $areas = $st->fetchAll(PDO::FETCH_ASSOC);

  // SUCURSALES
  $st = $conexion->query("
    SELECT id_sucursal, nombre_sucursal
    FROM dbo.SUCURSALES
    WHERE activo = 1
    ORDER BY nombre_sucursal
  ");

  $sucursales = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  $_SESSION['flash_error'] = "Error cargando datos.";
}

/* =============================
   DATOS AGENTE
============================= */
$agente = [
  'id_agente_int' => $idAgente,
  'nombre_agente' => '',
  'email' => '',
  'celular' => '',
  'id_area' => ($veTodo ? 0 : $idAreaSesion),
  'id_sucursal' => 0,
  'estado' => 1
];

$error = '';

if ($esEdicion) {
  try {
    $stmt = $conexion->prepare("EXEC dbo.$SP_OBTENER ?");
    $stmt->execute([$idAgente]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
      exit;
    }

    $agente = array_merge($agente, $row);

  } catch(Throwable $e){
    $_SESSION['flash_error'] = "Error cargando agente.";
  }
}

$PAGE_TITLE = $esEdicion ? "✏️ Editar Agente" : "➕ Nuevo Agente";
$PAGE_SUBTITLE = $esEdicion ? h($agente['nombre_agente']) : "";

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="mb-3">
  <a href="<?= h($BASE_URL) ?>/vistas_pantallas/listado_agentes.php" class="btn btn-soft btn-sm shadow-sm">
    ← Volver
  </a>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger shadow-sm">
    <?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
  </div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success shadow-sm">
    <?= h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
  </div>
<?php endif; ?>

<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small">
    <?= $esEdicion ? 'Información del agente' : 'Registrar nuevo agente' ?>
  </div>

  <div class="card-body">
    <form method="POST" action="<?= h($BASE_URL) ?>/cruds/proceso_guardar_agente.php" class="row g-3">

      <input type="hidden" name="id_agente" value="<?= (int)$agente['id_agente_int'] ?>">

      <!-- Nombre -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">NOMBRE DEL AGENTE *</label>
        <input type="text" name="nombre_agente"
               class="form-control form-control-sm"
               maxlength="150" required
               pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,}$"
               value="<?= h($agente['nombre_agente']) ?>">
      </div>

      <!-- Email -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">EMAIL</label>
        <input type="email" name="email"
               class="form-control form-control-sm"
               maxlength="255"
               pattern="^[a-zA-Z0-9._%+-]+@alfanet\.net\.ec$"
               value="<?= h($agente['email']) ?>">
      </div>

      <!-- Celular -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">CELULAR</label>
        <input type="text" name="celular"
               class="form-control form-control-sm"
               maxlength="10"
               pattern="^09[0-9]{8}$"
               value="<?= h($agente['celular']) ?>">
      </div>

      <!-- Área -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">ÁREA *</label>
        <select name="id_area" class="form-select form-select-sm" required <?= $veTodo ? '' : 'disabled' ?>>
          <option value="0">Seleccione...</option>
          <?php foreach($areas as $a): ?>
            <option value="<?= (int)$a['id_area'] ?>"
              <?= (int)$agente['id_area'] === (int)$a['id_area'] ? 'selected' : '' ?>>
              <?= h($a['nombre_area']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if(!$veTodo): ?>
          <input type="hidden" name="id_area" value="<?= (int)$agente['id_area'] ?>">
        <?php endif; ?>
      </div>

      <!-- Sucursal -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">SUCURSAL *</label>
        <select name="id_sucursal" class="form-select form-select-sm" required>
          <option value="0">Seleccione...</option>
          <?php foreach($sucursales as $s): ?>
            <option value="<?= (int)$s['id_sucursal'] ?>"
              <?= (int)$agente['id_sucursal'] === (int)$s['id_sucursal'] ? 'selected' : '' ?>>
              <?= h($s['nombre_sucursal']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm shadow-sm">
          Guardar
        </button>
        <a href="<?= h($BASE_URL) ?>/vistas_pantallas/listado_agentes.php" class="btn btn-soft btn-sm shadow-sm">
          Cancelar
        </a>
      </div>

    </form>
  </div>
</div>

<?php require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php'; ?>
