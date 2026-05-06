<?php
declare(strict_types=1);

/**
 * /cruds/proceso_cargar_planificacion.php
 * =========================================================
 * BLOQUE 1:
 * - Recibe Excel
 * - Valida columnas
 * - Limpia staging
 * - Inserta en PLANIFICACION_CARGA_EXCEL
 * - NO toca monitoreos reales
 * - NO toca tabla final todavía
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';

require_login();
force_password_change();
require_permission('cargar_planificacion');

function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/vistas_pantallas/cargar_planificacion.php');
    exit;
}

if (
    empty($_FILES['archivo_excel']) ||
    !isset($_FILES['archivo_excel']['error']) ||
    $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK
) {
    exit('❌ No se recibió un archivo válido.');
}

$archivo = $_FILES['archivo_excel'];
$nombreOriginal = (string)$archivo['name'];
$tmpPath = (string)$archivo['tmp_name'];
$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if ($extension !== 'xlsx') {
    exit('❌ El archivo debe ser formato .xlsx');
}

$autoloadPath = BASE_PATH . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    exit('❌ No se encontró vendor/autoload.php.');
}

require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load($tmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $filas = $sheet->toArray(null, true, true, true);

    if (count($filas) < 2) {
        throw new Exception('El Excel no contiene datos para cargar.');
    }

    $encabezadosEsperados = [
        'A' => 'Monitor',
        'B' => 'Asesor',
        'C' => 'Area',
        'D' => 'Sucursal',
        'E' => 'Tipo',
        'F' => 'Cantidad',
        'G' => 'Periodo',
    ];

    foreach ($encabezadosEsperados as $columna => $esperado) {
        $actual = trim((string)($filas[1][$columna] ?? ''));

        if (mb_strtoupper($actual, 'UTF-8') !== mb_strtoupper($esperado, 'UTF-8')) {
            throw new Exception("Columna inválida en {$columna}. Se esperaba '{$esperado}' y llegó '{$actual}'.");
        }
    }

    $conexion->beginTransaction();

    $conexion->exec("TRUNCATE TABLE dbo.PLANIFICACION_CARGA_EXCEL");

    $sqlInsert = "
        INSERT INTO dbo.PLANIFICACION_CARGA_EXCEL
        (
            monitor,
            asesor,
            area,
            sucursal,
            tipo,
            cantidad,
            periodo
        )
        VALUES
        (
            :monitor,
            :asesor,
            :area,
            :sucursal,
            :tipo,
            :cantidad,
            :periodo
        )
    ";

    $stmtInsert = $conexion->prepare($sqlInsert);

    $insertadas = 0;
    $errores = [];

    foreach ($filas as $numeroFila => $fila) {

        if ($numeroFila === 1) {
            continue;
        }

        $monitor  = trim((string)($fila['A'] ?? ''));
        $asesor   = trim((string)($fila['B'] ?? ''));
        $area     = trim((string)($fila['C'] ?? ''));
        $sucursal = trim((string)($fila['D'] ?? ''));
        $tipo     = trim((string)($fila['E'] ?? ''));
        $cantidad = trim((string)($fila['F'] ?? ''));
        $periodo  = trim((string)($fila['G'] ?? ''));

        if (
            $monitor === '' &&
            $asesor === '' &&
            $area === '' &&
            $sucursal === '' &&
            $tipo === '' &&
            $cantidad === '' &&
            $periodo === ''
        ) {
            continue;
        }

        if (!is_numeric($cantidad)) {
            $errores[] = "Fila {$numeroFila}: cantidad inválida.";
            continue;
        }

        $stmtInsert->execute([
            ':monitor'  => $monitor,
            ':asesor'   => $asesor,
            ':area'     => $area,
            ':sucursal' => $sucursal,
            ':tipo'     => $tipo,
            ':cantidad' => (int)$cantidad,
            ':periodo'  => $periodo,
        ]);

        $insertadas++;
    }

    if (!empty($errores)) {
        $conexion->rollBack();
        throw new Exception(implode(' | ', $errores));
    }

    $conexion->commit();

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    exit('❌ Error en carga staging: ' . h($e->getMessage()));
}

$PAGE_TITLE = "📅 Planificación QA";
$PAGE_SUBTITLE = "Carga inicial a staging";

$PAGE_ACTION_HTML = '
<a class="btn btn-outline-primary btn-sm shadow-sm"
   href="'.BASE_URL.'/vistas_pantallas/cargar_planificacion.php">
    <i class="bi bi-arrow-left"></i> Volver
</a>
';

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="card card-soft mb-4">
    <div class="card-header card-header-dark py-2 small">
        <i class="bi bi-database-check me-2"></i>Resultado de carga staging
    </div>

    <div class="card-body">

        <div class="alert alert-success">
            ✅ Archivo cargado correctamente en staging:
            <b><?= h($nombreOriginal) ?></b>
        </div>

        <p class="mb-2">
            Filas insertadas en <b>PLANIFICACION_CARGA_EXCEL</b>:
            <span class="badge bg-success"><?= (int)$insertadas ?></span>
        </p>

        <div class="alert alert-warning small">
            Esta carga todavía <b>NO pasó a la tabla final</b>.
            Solo se insertó en staging para validaciones.
        </div>

        <a href="<?= BASE_URL ?>/vistas_pantallas/cargar_planificacion.php"
           class="btn btn-outline-primary btn-sm">
            Volver
        </a>

    </div>
</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>