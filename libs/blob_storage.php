<?php

/* =========================================================
   CARGAR AUTOLOAD DE COMPOSER
   -> Permite usar el SDK de Azure Blob instalado por Composer
========================================================= */
require_once __DIR__ . '/../vendor/autoload.php';


/* =========================================================
   IMPORTAR CLASES DEL SDK AZURE
========================================================= */
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;


/* =========================================================
   FUNCIÓN PRINCIPAL
   -> Sube una imagen a Azure Blob Storage
========================================================= */
function subirImagenAzureBlob(
    string $tmpPath,
    string $nombreOriginal,
    string $carpeta = 'monitoreos'
): array
{

    /* =====================================================
       OBTENER VARIABLES DE ENTORNO
       -> Se leen desde Azure App Service
    ===================================================== */
    $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');

    $container = getenv('AZURE_STORAGE_CONTAINER');


    /* =====================================================
       VALIDAR VARIABLES
    ===================================================== */
    if (!$connectionString || !$container) {
        throw new Exception(
            'Variables de entorno Azure Blob no configuradas.'
        );
    }


    /* =====================================================
       VALIDAR QUE EXISTA EL ARCHIVO TEMPORAL
    ===================================================== */
    if (!file_exists($tmpPath)) {
        throw new Exception(
            'Archivo temporal no encontrado.'
        );
    }


    /* =====================================================
       OBTENER TAMAÑO DEL ARCHIVO
    ===================================================== */
    $tamanoBytes = filesize($tmpPath);


    /* =====================================================
       VALIDAR TAMAÑO MÁXIMO
       -> Máximo 3 MB
    ===================================================== */
    if ($tamanoBytes > 3 * 1024 * 1024) {

        throw new Exception(
            'La imagen supera el máximo permitido de 3 MB.'
        );
    }


    /* =====================================================
       DETECTAR MIME TYPE REAL
    ===================================================== */
    $mime = mime_content_type($tmpPath);


    /* =====================================================
       FORMATOS PERMITIDOS
    ===================================================== */
    $permitidos = [

        'image/jpeg' => 'jpg',

        'image/png'  => 'png',

        'image/webp' => 'webp'
    ];


    /* =====================================================
       VALIDAR FORMATO
    ===================================================== */
    if (!isset($permitidos[$mime])) {

        throw new Exception(
            'Formato no permitido. Solo JPG, PNG o WEBP.'
        );
    }


    /* =====================================================
       OBTENER EXTENSIÓN
    ===================================================== */
    $extension = $permitidos[$mime];


    /* =====================================================
       GENERAR NOMBRE ÚNICO
       -> evita colisiones
       -> evita sobreescribir imágenes
    ===================================================== */
    $nombreSeguro =

        date('Ymd_His')

        . '_'

        . bin2hex(random_bytes(8))

        . '.'

        . $extension;


    /* =====================================================
       DEFINIR RUTA DENTRO DEL CONTAINER
    ===================================================== */
    $blobName =

        trim($carpeta, '/')

        . '/'

        . $nombreSeguro;


    /* =====================================================
       CREAR CLIENTE AZURE BLOB
    ===================================================== */
    $blobClient = BlobRestProxy::createBlobService(
        $connectionString
    );


    /* =====================================================
       ABRIR ARCHIVO TEMPORAL
    ===================================================== */
    $contenido = fopen($tmpPath, 'r');


    /* =====================================================
       OPCIONES DEL BLOB
       -> guardar content-type correcto
    ===================================================== */
    $options = new CreateBlockBlobOptions();

    $options->setContentType($mime);


    /* =====================================================
       SUBIR ARCHIVO A AZURE BLOB
    ===================================================== */
    $blobClient->createBlockBlob(

        $container,

        $blobName,

        $contenido,

        $options
    );


    /* =====================================================
       GENERAR URL PÚBLICA FINAL
    ===================================================== */
    $url = generarUrlPublicaBlob(

        $connectionString,

        $container,

        $blobName
    );


    /* =====================================================
       RETORNAR METADATA
       -> esto luego irá a SQL
    ===================================================== */
    return [

        'nombre_archivo' => $nombreSeguro,

        'url_archivo'   => $url,

        'tipo_archivo'  => $mime,

        'tamano_bytes'  => $tamanoBytes
    ];
}


/* =========================================================
   GENERAR URL PÚBLICA DEL BLOB
========================================================= */
function generarUrlPublicaBlob(

    string $connectionString,

    string $container,

    string $blobName

): string
{

    /* =====================================================
       EXTRAER DATOS DEL CONNECTION STRING
    ===================================================== */
    preg_match(
        '/AccountName=([^;]+)/',
        $connectionString,
        $accountMatch
    );

    preg_match(
        '/DefaultEndpointsProtocol=([^;]+)/',
        $connectionString,
        $protocolMatch
    );

    preg_match(
        '/EndpointSuffix=([^;]+)/',
        $connectionString,
        $suffixMatch
    );


    /* =====================================================
       OBTENER VALORES
    ===================================================== */
    $accountName = $accountMatch[1] ?? null;

    $protocol = $protocolMatch[1] ?? 'https';

    $suffix = $suffixMatch[1] ?? 'core.windows.net';


    /* =====================================================
       VALIDAR ACCOUNT NAME
    ===================================================== */
    if (!$accountName) {

        throw new Exception(
            'No se pudo obtener AccountName.'
        );
    }


    /* =====================================================
       ENCODE URL SEGURA
    ===================================================== */
    $blobEncoded = implode(
        '/',
        array_map(
            'rawurlencode',
            explode('/', $blobName)
        )
    );


    /* =====================================================
       RETORNAR URL FINAL
    ===================================================== */
    return

        "{$protocol}://"

        . "{$accountName}.blob."

        . "{$suffix}/"

        . "{$container}/"

        . "{$blobEncoded}";
}