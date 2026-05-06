<?php
declare(strict_types=1);

/**
 * =========================================================
 * /cruds/proceso_cargar_planificacion.php
 * =========================================================
 *
 * PROCESO PRINCIPAL DE CARGA DE PLANIFICACIÓN QA
 *
 * FUNCIONES:
 * 1. Recibe archivo Excel
 * 2. Valida formato .xlsx
 * 3. Lee Excel con PhpSpreadsheet
 * 4. Inserta staging temporal
 * 5. Homologa agentes automáticamente
 * 6. Valida duplicados por período
 * 7. Inserta planificación final
 * 8. Muestra pendientes sin homologar
 *
 * IMPORTANTE:
 * - NO elimina planificación automáticamente
 * - Protege información en producción
 * - Usa transacciones + rollback
 *
 * =========================================================
 */


/* =========================================================
 * BLOQUE 1
 * CONFIGURACIÓN Y SEGURIDAD
 * ========================================================= */

require_once __DIR__ . '/../config_ajustes/app.php';

require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_once BASE_PATH . '/config_ajustes/conectar_db.php';

/*
|--------------------------------------------------------------------------
| VALIDACIONES DE SEGURIDAD
|--------------------------------------------------------------------------
|
| require_login()
|   → obliga usuario autenticado
|
| force_password_change()
|   → obliga cambio de contraseña si aplica
|
| require_permission()
|   → valida permiso específico del módulo
|
*/

require_login();

force_password_change();

require_permission('cargar_planificacion');


/* =========================================================
 * BLOQUE 2
 * FUNCIÓN SEGURA PARA HTML
 * ========================================================= */

function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}


/* =========================================================
 * BLOQUE 3
 * VALIDAR REQUEST POST
 * ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: ' .
        BASE_URL .
        '/vistas_pantallas/cargar_planificacion.php'
    );

    exit;
}


/* =========================================================
 * BLOQUE 4
 * VALIDAR ARCHIVO RECIBIDO
 * ========================================================= */

if (
    empty($_FILES['archivo_excel']) ||
    !isset($_FILES['archivo_excel']['error']) ||
    $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK
) {

    exit('❌ No se recibió un archivo válido.');
}


/* =========================================================
 * BLOQUE 5
 * OBTENER INFORMACIÓN DEL ARCHIVO
 * ========================================================= */

$archivo = $_FILES['archivo_excel'];

$nombreOriginal = (string)$archivo['name'];

$tmpPath = (string)$archivo['tmp_name'];

$extension = strtolower(
    pathinfo($nombreOriginal, PATHINFO_EXTENSION)
);


/* =========================================================
 * BLOQUE 6
 * VALIDAR EXTENSIÓN .XLSX
 * ========================================================= */

if ($extension !== 'xlsx') {

    exit('❌ El archivo debe ser formato .xlsx');
}


/* =========================================================
 * BLOQUE 7
 * VALIDAR COMPOSER / AUTOLOAD
 * ========================================================= */

$autoloadPath = BASE_PATH . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {

    exit('❌ No se encontró vendor/autoload.php.');
}

require_once $autoloadPath;


/* =========================================================
 * BLOQUE 8
 * IMPORTAR PHPSPREADSHEET
 * ========================================================= */

use PhpOffice\PhpSpreadsheet\IOFactory;


/* =========================================================
 * BLOQUE 9
 * VARIABLES GENERALES
 * ========================================================= */

$insertadasStaging = 0;

$insertadasFinal = 0;

$pendientes = [];

$periodosCargados = [];


/* =========================================================
 * BLOQUE 10
 * INICIO DEL TRY PRINCIPAL
 * ========================================================= */

try {

    /*
    |--------------------------------------------------------------------------
    | LEER EXCEL
    |--------------------------------------------------------------------------
    */

    $spreadsheet = IOFactory::load($tmpPath);

    $sheet = $spreadsheet->getActiveSheet();

    $filas = $sheet->toArray(null, true, true, true);


    /*
    |--------------------------------------------------------------------------
    | VALIDAR QUE EXISTA CONTENIDO
    |--------------------------------------------------------------------------
    */

    if (count($filas) < 2) {

        throw new Exception(
            'El Excel no contiene datos.'
        );
    }


    /* =========================================================
     * BLOQUE 11
     * VALIDAR ENCABEZADOS
     * ========================================================= */

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

        $actual = trim(
            (string)($filas[1][$columna] ?? '')
        );

        if (
            mb_strtoupper($actual, 'UTF-8')
            !==
            mb_strtoupper($esperado, 'UTF-8')
        ) {

            throw new Exception(
                "Columna inválida en {$columna}."
            );
        }
    }


    /* =========================================================
     * BLOQUE 12
     * INICIAR TRANSACCIÓN SQL
     * ========================================================= */

    $conexion->beginTransaction();


    /* =========================================================
     * BLOQUE 13
     * LIMPIAR STAGING TEMPORAL
     * =========================================================
     *
     * IMPORTANTE:
     * SOLO se limpia staging.
     * NO se elimina información final.
     *
     */

    $conexion->exec("
        TRUNCATE TABLE dbo.PLANIFICACION_CARGA_EXCEL
    ");


    /* =========================================================
     * BLOQUE 14
     * PREPARAR INSERT STAGING
     * ========================================================= */

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


    /* =========================================================
     * BLOQUE 15
     * RECORRER FILAS DEL EXCEL
     * ========================================================= */

    foreach ($filas as $numeroFila => $fila) {

        /*
        |--------------------------------------------------------------------------
        | SALTAR ENCABEZADO
        |--------------------------------------------------------------------------
        */

        if ($numeroFila === 1) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | OBTENER COLUMNAS
        |--------------------------------------------------------------------------
        */

        $monitor  = trim((string)($fila['A'] ?? ''));

        $asesor   = trim((string)($fila['B'] ?? ''));

        $area     = trim((string)($fila['C'] ?? ''));

        $sucursal = trim((string)($fila['D'] ?? ''));

        $tipo     = trim((string)($fila['E'] ?? ''));

        $cantidad = trim((string)($fila['F'] ?? ''));

        $periodo  = trim((string)($fila['G'] ?? ''));


        /*
        |--------------------------------------------------------------------------
        | IGNORAR FILAS VACÍAS
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | VALIDAR CANTIDAD
        |--------------------------------------------------------------------------
        */

        if (
            !is_numeric($cantidad)
            ||
            (int)$cantidad <= 0
        ) {

            throw new Exception(
                "Fila {$numeroFila}: cantidad inválida."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDAR PERÍODO YYYY-MM
        |--------------------------------------------------------------------------
        */

        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {

            throw new Exception(
                "Fila {$numeroFila}: período inválido."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | INSERTAR STAGING
        |--------------------------------------------------------------------------
        */

        $stmtInsertStaging->execute([

            ':monitor'  => $monitor,
            ':asesor'   => $asesor,
            ':area'     => $area,
            ':sucursal' => $sucursal,
            ':tipo'     => $tipo,
            ':cantidad' => (int)$cantidad,
            ':periodo'  => $periodo,
        ]);


        /*
        |--------------------------------------------------------------------------
        | GUARDAR PERÍODOS CARGADOS
        |--------------------------------------------------------------------------
        */

        $periodosCargados[$periodo] = true;

        $insertadasStaging++;
    }


    /* =========================================================
     * BLOQUE 16
     * VALIDAR STAGING
     * ========================================================= */

    if ($insertadasStaging === 0) {

        throw new Exception(
            'No se insertó ninguna fila válida.'
        );
    }


    /* =========================================================
     * BLOQUE 17
     * HOMOLOGACIÓN AUTOMÁTICA
     * ========================================================= */

    $conexion->exec("

        UPDATE p

        SET p.id_agente = a.id_agente_int

        FROM dbo.PLANIFICACION_CARGA_EXCEL p

        INNER JOIN dbo.AGENTES a

            ON UPPER(LTRIM(RTRIM(a.nombre_agente)))
               COLLATE Latin1_General_CI_AI

             =

               UPPER(LTRIM(RTRIM(p.asesor)))
               COLLATE Latin1_General_CI_AI

    ");


    /* =========================================================
     * BLOQUE 18
     * VALIDAR DUPLICADOS
     * =========================================================
     *
     * IMPORTANTE:
     * YA NO SE ELIMINA INFORMACIÓN.
     * SOLO SE DETIENE LA CARGA
     * SI YA EXISTE EL PERÍODO.
     *
     */

    $periodos = array_keys($periodosCargados);

    $placeholders = implode(
        ',',
        array_fill(0, count($periodos), '?')
    );

    $stmtExiste = $conexion->prepare("

        SELECT COUNT(*)

        FROM dbo.PLANIFICACION_MONITOREO

        WHERE periodo IN ($placeholders)
          AND estado = 'ACTIVO'

    ");

    $stmtExiste->execute($periodos);

    $yaExiste = (int)$stmtExiste->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | DETENER SI YA EXISTE PLANIFICACIÓN
    |--------------------------------------------------------------------------
    */

    if ($yaExiste > 0) {

        throw new Exception(

            'Ya existe planificación activa para el período cargado. '

            . 'No se insertó información para evitar duplicados.'

        );
    }


    /* =========================================================
     * BLOQUE 19
     * OBTENER PENDIENTES
     * ========================================================= */

    $stmtPend = $conexion->query("

        SELECT DISTINCT asesor, area, sucursal

        FROM dbo.PLANIFICACION_CARGA_EXCEL

        WHERE id_agente IS NULL

        ORDER BY area, asesor

    ");

    $pendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);


    /* =========================================================
     * BLOQUE 20
     * INSERTAR PLANIFICACIÓN FINAL
     * ========================================================= */

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


    /* =========================================================
     * BLOQUE 21
     * CONFIRMAR TRANSACCIÓN
     * ========================================================= */

    $conexion->commit();

} catch (Throwable $e) {


    /* =========================================================
     * BLOQUE 22
     * ROLLBACK SI ALGO FALLA
     * ========================================================= */

    if ($conexion->inTransaction()) {

        $conexion->rollBack();
    }

    exit(
        '❌ Error en carga de planificación: '
        . h($e->getMessage())
    );
}