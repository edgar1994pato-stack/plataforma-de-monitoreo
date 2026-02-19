<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

function h($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
$BASE_URL = BASE_URL;

/* =============================
   CONFIG SP (ajusta si tu SP tiene otro nombre)
============================= */
$SP_OBTENER = 'PR_OBTENER_AGENTE';     // <-- si tu SP se llama diferente, cámbialo aquí
// El guardado se hace desde cruds/proceso_guardar_agente.php

/* =============================
   SESIÓN Y PERMISOS
============================= */
$idAreaSesion = (int)($_SESSION['id_area'] ?? 0);
$veTodo       = can_see_all_areas();
$soloLectura  = is_readonly();
$puedeCrear   = can_create();
$puedeEditar  = function_exists('can_edit') ? can_edit() : true; // fallback

if ($soloLectura) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "Modo solo lectura.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Acceso denegado: usuario en modo solo lectura.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
  exit;
}

/* =============================
   MODO (crear / editar)
============================= */
$idAgente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$esEdicion = $idAgente > 0;

if ($esEdicion && !$puedeEditar) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "No tiene permisos para editar.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Acceso denegado.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
  exit;
}

if (!$esEdicion && !$puedeCrear) {
  http_response_code(403);
  $PAGE_TITLE = "⛔ Acceso denegado";
  $PAGE_SUBTITLE = "No tiene permisos para crear.";
  require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
  echo '<div class="alert alert-danger">Acceso denegado.</div>';
  require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
  exit;
}

/* =============================
   CATALOGOS (Áreas, Colas, Supervisores)
============================= */
$areas = $colas = $supervisores = [];

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

try {
  $st = $conexion->query("SELECT id_cola, nombre_cola FROM dbo.COLAS WHERE estado=1 ORDER BY nombre_cola");
  $colas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

try {
  // Ajusta si tu tabla/columna difiere
  $st = $conexion->query("SELECT id_usuario, nombre_completo FROM dbo.USUARIOS WHERE estado=1 ORDER BY nombre_completo");
  $supervisores = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

/* =============================
   CARGAR AGENTE (si edita)
============================= */
$agente = [
  'id_agente_int' => $idAgente,
  'nombre_agente' => '',
  'id_area' => ($veTodo ? 0 : $idAreaSesion),
  'id_cola' => 0,
  'id_supervisor_usuario' => 0,
  'estado' => 1,
];

$error = '';

if ($esEdicion) {
  try {
    // Recomendado: que tengas un SP que devuelva el agente por ID
    $stmt = $conexion->prepare("EXEC dbo.$SP_OBTENER ?");
    $stmt->execute([$idAgente]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
      exit;
    }

    // Mapeo (ajusta nombres si tu SP devuelve otros campos)
    $agente['id_agente_int'] = (int)$row['id_agente_int'];
    $agente['nombre_agente'] = (string)$row['nombre_agente'];
    $agente['id_area'] = (int)($row['id_area'] ?? $agente['id_area']);
    $agente['id_cola'] = (int)($row['id_cola'] ?? 0);
    $agente['id_supervisor_usuario'] = (int)($row['id_supervisor_usuario'] ?? 0);
    $agente['estado'] = (int)($row['estado'] ?? 1);

  } catch(Throwable $e){
    $error = $e->getMessage();
  }
}

$PAGE_TITLE = $esEdicion ? "✏️ Editar Agente" : "➕ Nuevo Agente";
$PAGE_SUBTITLE = $esEdicion ? h($agente['nombre_agente']) : "";

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
  <a href="<?= h($BASE_URL) ?>/vistas_pantallas/listado_agentes.php" class="btn btn-soft btn-sm shadow-sm">
    ← Volver
  </a>
</div>

<?php if($error !== ''): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small">
    <?= $esEdicion ? 'Datos del agente' : 'Registrar nuevo agente' ?>
  </div>

  <div class="card-body">
    <form method="POST" action="<?= h($BASE_URL) ?>/cruds/proceso_guardar_agente.php" class="row g-3">

      <input type="hidden" name="id_agente" value="<?= (int)$agente['id_agente_int'] ?>">

      <!-- Nombre -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">NOMBRE DEL AGENTE</label>
        <input type="text" name="nombre_agente" class="form-control form-control-sm"
               maxlength="150" required value="<?= h($agente['nombre_agente']) ?>">
      </div>

      <!-- Área -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">ÁREA</label>
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

      <!-- Cola -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">COLA</label>
        <select name="id_cola" class="form-select form-select-sm">
          <option value="0">--- SIN COLA ---</option>
          <?php foreach($colas as $c): ?>
            <option value="<?= (int)$c['id_cola'] ?>"
              <?= (int)$agente['id_cola'] === (int)$c['id_cola'] ? 'selected' : '' ?>>
              <?= h($c['nombre_cola']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Supervisor -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">SUPERVISOR</label>
        <select name="id_supervisor_usuario" class="form-select form-select-sm">
          <option value="0">--- SIN SUPERVISOR ---</option>
          <?php foreach($supervisores as $u): ?>
            <option value="<?= (int)$u['id_usuario'] ?>"
              <?= (int)$agente['id_supervisor_usuario'] === (int)$u['id_usuario'] ? 'selected' : '' ?>>
              <?= h($u['nombre_completo']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Estado -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">ESTADO</label>
        <select name="estado" class="form-select form-select-sm" required>
          <option value="1" <?= (int)$agente['estado'] === 1 ? 'selected' : '' ?>>ACTIVO</option>
          <option value="0" <?= (int)$agente['estado'] === 0 ? 'selected' : '' ?>>INACTIVO</option>
        </select>
      </div>

      <!-- Botones -->
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm shadow-sm">
          <i class="bi bi-save"></i> Guardar
        </button>
        <a href="<?= h($BASE_URL) ?>/vistas_pantallas/listado_agentes.php" class="btn btn-soft btn-sm shadow-sm">
          Cancelar
        </a>
      </div>

    </form>
  </div>
</div>

<?php require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php'; ?>
