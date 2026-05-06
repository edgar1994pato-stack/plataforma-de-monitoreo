<?php
declare(strict_types=1);

/**
 * /cruds/proceso_cargar_planificacion.php
 * =========================================================
 * PRUEBA INICIAL:
 * - Recibe archivo Excel
 * - Valida extensión .xlsx
 * - Valida autoload de Composer
 * - Valida PhpSpreadsheet
 * - Lee primeras filas
 * - NO inserta nada en SQL todavía
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();
require_permission('cargar_planificacion');

function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
 * 1) Validar método POST
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/vistas_pantallas/cargar_planificacion.php');
    exit;
}

/* =========================================================
 * 2) Validar archivo recibido
 * ========================================================= */
if (
    empty($_FILES['archivo_excel']) ||
    !isset($_FILES['archivo_excel']['error']) ||
    $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK
) {
    exit('❌ No se recibió un archivo válido.');
}

$archivo = $_FILES['archivo_excel'];

$nombreOriginal = (string)$archivo['name'];
$tmpPath        = (string)$archivo['tmp_name'];
$extension      = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if ($extension !== 'xlsx') {
    exit('❌ El archivo debe ser formato .xlsx');
}

/* =========================================================
 * 3) Validar Composer autoload
 * ========================================================= */
$autoloadPath = BASE_PATH . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    exit('❌ No se encontró vendor/autoload.php. Falta Composer/vendor en el servidor.');
}

require_once $autoloadPath;

/* =========================================================
 * 4) Validar PhpSpreadsheet
 * ========================================================= */
if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
    exit('❌ PhpSpreadsheet no está disponible.');
}

use PhpOffice\PhpSpreadsheet\IOFactory;

/* =========================================================
 * 5) Leer Excel de prueba
 * ========================================================= */
try {
    $spreadsheet = IOFactory::load($tmpPath);
    $sheet = $spreadsheet->getActiveSheet();

    $filas = $sheet->toArray(null, true, true, true);

} catch (Throwable $e) {
    exit('❌ Error al leer el Excel: ' . h($e->getMessage()));
}

/* =========================================================
 * 6) Mostrar resultado de prueba
 * ========================================================= */
require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="card card-soft mb-4">
    <div class="card-header card-header-dark py-2 small">
        <i class="bi bi-file-earmark-excel me-2"></i>Prueba de lectura Excel
    </div>

    <div class="card-body">

        <div class="alert alert-success">
            ✅ PhpSpreadsheet leyó correctamente el archivo:
            <b><?= h($nombreOriginal) ?></b>
        </div>

        <p class="small text-muted">
            Vista previa de las primeras 10 filas. Todavía no se insertó nada en SQL.
        </p>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <tbody>
                <?php
                $contador = 0;
                foreach ($filas as $fila):
                    $contador++;
                    if ($contador > 10) break;
                ?>
                    <tr>
                        <?php foreach ($fila as $valor): ?>
                            <td><?= h($valor) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="<?= BASE_URL ?>/vistas_pantallas/cargar_planificacion.php"
           class="btn btn-outline-primary btn-sm">
            Volver
        </a>

    </div>
</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';