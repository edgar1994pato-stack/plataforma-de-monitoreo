<?php
declare(strict_types=1);

/**
 * /cruds/proceso_cargar_planificacion.php
 * Carga real de planificación QA:
 * - Lee Excel
 * - Inserta en staging
 * - Homologa id_agente
 * - Limpia tabla final por período cargado
 * - Inserta registros válidos en PLANIFICACION_MONITOREO
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

$insertadasStaging = 0;
$insertadasFinal = 0;
$pendientes = [];
$periodosCargados = [];

try {
    $spreadsheet = IOFactory::load($tmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $filas = $sheet->toArray(null, true, true, true);

    if (count($filas) < 2) {
        throw new Exception('El Excel no contiene datos.');
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

    // 1. Limpiar staging
    $conexion->exec("TRUNCATE TABLE dbo.PLANIFICACION_CARGA_EXCEL");

    // 2. Insertar Excel en staging
    $stmtInsertStaging = $conexion->prepare("
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
    ");

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

        if (!is_numeric($cantidad) || (int)$cantidad <= 0) {
            throw new Exception("Fila {$numeroFila}: cantidad inválida.");
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new Exception("Fila {$numeroFila}: periodo inválido. Formato esperado YYYY-MM.");
        }

        $stmtInsertStaging->execute([
            ':monitor'  => $monitor,
            ':asesor'   => $asesor,
            ':area'     => $area,
            ':sucursal' => $sucursal,
            ':tipo'     => $tipo,
            ':cantidad' => (int)$cantidad,
            ':periodo'  => $periodo,
        ]);

        $periodosCargados[$periodo] = true;
        $insertadasStaging++;
    }

    if ($insertadasStaging === 0) {
        throw new Exception('No se insertó ninguna fila válida en staging.');
    }

    // 3. Homologar id_agente
    $conexion->exec("
        UPDATE p
        SET p.id_agente = a.id_agente_int
        FROM dbo.PLANIFICACION_CARGA_EXCEL p
        INNER JOIN dbo.AGENTES a
            ON UPPER(LTRIM(RTRIM(a.nombre_agente))) COLLATE Latin1_General_CI_AI
             = UPPER(LTRIM(RTRIM(p.asesor))) COLLATE Latin1_General_CI_AI
    ");

    // 4. Obtener pendientes sin id_agente
    $stmtPend = $conexion->query("
        SELECT DISTINCT asesor, area, sucursal
        FROM dbo.PLANIFICACION_CARGA_EXCEL
        WHERE id_agente IS NULL
        ORDER BY area, asesor
    ");

    $pendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);

    // 5. Limpiar tabla final SOLO de los períodos cargados
    $periodos = array_keys($periodosCargados);
    $placeholders = implode(',', array_fill(0, count($periodos), '?'));

    $stmtDeleteFinal = $conexion->prepare("
        DELETE FROM dbo.PLANIFICACION_MONITOREO
        WHERE periodo IN ($placeholders)
    ");
    $stmtDeleteFinal->execute($periodos);

    // 6. Insertar tabla final solo registros con id_agente
    $stmtInsertFinal = $conexion->prepare("
        INSERT INTO dbo.PLANIFICACION_MONITOREO
        (
            periodo,
            id_agente,
            monitor,
            asesor,
            area,
            sucursal,
            tipo,
            cantidad,
            estado,
            fecha_creacion
        )
        SELECT
            periodo,
            id_agente,
            monitor,
            asesor,
            area,
            sucursal,
            tipo,
            cantidad,
            'ACTIVO',
            GETDATE()
        FROM dbo.PLANIFICACION_CARGA_EXCEL
        WHERE id_agente IS NOT NULL
    ");

    $stmtInsertFinal->execute();
    $insertadasFinal = $stmtInsertFinal->rowCount();

    $conexion->commit();

} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    exit('❌ Error en carga de planificación: ' . h($e->getMessage()));
}

$PAGE_TITLE = "📅 Planificación QA";
$PAGE_SUBTITLE = "Resultado de carga automática";

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
        <i class="bi bi-database-check me-2"></i>Resultado de carga planificación
    </div>

    <div class="card-body">

        <div class="alert alert-success">
            ✅ Archivo procesado correctamente:
            <b><?= h($nombreOriginal) ?></b>
        </div>

        <p>
            Filas cargadas en staging:
            <span class="badge bg-primary"><?= (int)$insertadasStaging ?></span>
        </p>

        <p>
            Filas insertadas en planificación final:
            <span class="badge bg-success"><?= (int)$insertadasFinal ?></span>
        </p>

        <?php if (!empty($pendientes)): ?>
            <div class="alert alert-warning">
                ⚠️ Registros no insertados en tabla final porque no tienen agente homologado.
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Asesor</th>
                            <th>Área</th>
                            <th>Sucursal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendientes as $p): ?>
                            <tr>
                                <td><?= h($p['asesor'] ?? '') ?></td>
                                <td><?= h($p['area'] ?? '') ?></td>
                                <td><?= h($p['sucursal'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                ✅ Todos los registros fueron homologados correctamente.
            </div>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/vistas_pantallas/cargar_planificacion.php"
           class="btn btn-outline-primary btn-sm">
            Volver
        </a>

    </div>
</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>