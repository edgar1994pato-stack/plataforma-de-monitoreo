<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

function h($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$BASE_URL = BASE_URL;

$idAgente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idAgente <= 0) {
    header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
    exit;
}

$soloLectura = is_readonly();
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);

/* =============================
   CAMBIAR ESTADO (POST)
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$soloLectura) {

    try {
        $stmt = $conexion->prepare("EXEC dbo.PR_CAMBIAR_ESTADO_AGENTE ?, ?");
        $stmt->execute([$idAgente, $idUsuarioSesion]);

        header("Location: $BASE_URL/vistas_pantallas/gestionar_agente.php?id=$idAgente");
        exit;

    } catch(Throwable $e) {
        $errorAccion = $e->getMessage();
    }
}

/* =============================
   OBTENER DETALLE
============================= */
try {
    $stmt = $conexion->prepare("EXEC dbo.PR_OBTENER_DETALLE_AGENTE ?");
    $stmt->execute([$idAgente]);

    $detalle = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->nextRowset();
    $ausenciaActiva = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->nextRowset();
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e){
    die("Error: " . $e->getMessage());
}

$PAGE_TITLE = "⚙ Gestionar Agente";
$PAGE_SUBTITLE = h($detalle['nombre_agente'] ?? '');

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="mb-3">
  <a href="<?= h($BASE_URL) ?>/vistas_pantallas/listado_agentes.php"
     class="btn btn-soft btn-sm">
     ← Volver
  </a>
</div>

<?php if(!empty($errorAccion)): ?>
<div class="alert alert-danger">
  <?= h($errorAccion) ?>
</div>
<?php endif; ?>

<!-- =============================
     ESTADO LABORAL
============================= -->
<div class="card card-soft mb-4">
  <div class="card-header card-header-dark py-2 small">
    Estado Laboral
  </div>
  <div class="card-body">

    <p>
      Estado actual:
      <strong><?= h($detalle['estado_operativo']) ?></strong>
    </p>

    <?php if(!$soloLectura): ?>
      <form method="POST">
        <button type="submit" class="btn btn-primary btn-sm">
          <?= $detalle['estado'] == 1 ? 'Desactivar Agente' : 'Activar Agente' ?>
        </button>
      </form>
    <?php endif; ?>

  </div>
</div>

<!-- =============================
     AUSENCIA ACTIVA
============================= -->
<div class="card card-soft mb-4">
  <div class="card-header card-header-dark py-2 small">
    Disponibilidad Operativa
  </div>
  <div class="card-body">

    <?php if($ausenciaActiva): ?>
      <p><strong>Ausencia Activa:</strong></p>
      <p>Tipo: <?= h($ausenciaActiva['tipo_ausencia']) ?></p>
      <p>Desde: <?= h($ausenciaActiva['fecha_inicio']) ?></p>
      <p>Hasta: <?= h($ausenciaActiva['fecha_fin']) ?></p>
      <p>Observación: <?= h($ausenciaActiva['observacion']) ?></p>

    <?php else: ?>
      <p>Sin ausencia activa.</p>
      <?php if(!$soloLectura): ?>
        <a href="<?= h($BASE_URL) ?>/vistas_pantallas/registrar_ausencia.php?id=<?= $idAgente ?>"
           class="btn btn-soft btn-sm">
           Registrar ausencia
        </a>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<!-- =============================
     HISTORIAL
============================= -->
<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small">
    Historial de Ausencias
  </div>
  <div class="card-body p-0">

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
        <?php if(empty($historial)): ?>
          <tr>
            <td colspan="4" class="text-center text-muted py-3">
              Sin registros.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach($historial as $h): ?>
            <tr>
              <td><?= h($h['tipo_ausencia']) ?></td>
              <td><?= h($h['fecha_inicio']) ?></td>
              <td><?= h($h['fecha_fin']) ?></td>
              <td><?= $h['estado'] == 1 ? 'ACTIVA' : 'CERRADA' ?></td>
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
