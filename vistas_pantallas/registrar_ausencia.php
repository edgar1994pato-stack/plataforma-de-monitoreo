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

/* =============================
   OBTENER NOMBRE DEL AGENTE
============================= */
try {
    $stmt = $conexion->prepare("
        SELECT nombre_agente 
        FROM dbo.AGENTES 
        WHERE id_agente_int = ?
    ");
    $stmt->execute([$idAgente]);
    $agente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agente) {
        header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
        exit;
    }

} catch(Throwable $e){
    die("Error: " . $e->getMessage());
}

$PAGE_TITLE = "🗓 Registrar Ausencia";
$PAGE_SUBTITLE = h($agente['nombre_agente']);

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="mb-3">
  <a href="<?= h($BASE_URL) ?>/vistas_pantallas/gestionar_agente.php?id=<?= $idAgente ?>"
     class="btn btn-soft btn-sm">
     ← Volver
  </a>
</div>

<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small">
    Datos de la ausencia
  </div>

  <div class="card-body">

    <?php if(!$soloLectura): ?>

    <form method="POST"
          action="<?= h($BASE_URL) ?>/cruds/proceso_registrar_ausencia.php">

        <input type="hidden" name="id_agente" value="<?= $idAgente ?>">

        <!-- Tipo -->
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">
                Tipo de ausencia
            </label>
            <select name="tipo_ausencia" 
                    class="form-select form-select-sm" 
                    required>
                <option value="">Seleccione...</option>
                <option value="VACACIONES">Vacaciones</option>
                <option value="PERMISO">Permiso</option>
                <option value="LICENCIA">Licencia</option>
            </select>
        </div>

        <!-- Fecha inicio -->
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">
                Fecha inicio
            </label>
            <input type="date"
                   name="fecha_inicio"
                   class="form-control form-control-sm"
                   required>
        </div>

        <!-- Fecha fin -->
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">
                Fecha fin
            </label>
            <input type="date"
                   name="fecha_fin"
                   class="form-control form-control-sm"
                   required>
        </div>

        <!-- Observación -->
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">
                Observación (opcional)
            </label>
            <textarea name="observacion"
                      class="form-control form-control-sm"
                      rows="3"></textarea>
        </div>

        <button type="submit"
                class="btn btn-primary btn-sm">
            Guardar ausencia
        </button>

    </form>

    <?php else: ?>

        <p class="text-muted">
            Usuario en modo solo lectura.
        </p>

    <?php endif; ?>

  </div>
</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
