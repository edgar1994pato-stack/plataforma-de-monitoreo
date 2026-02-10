<?php
declare(strict_types=1);

/*
 |=================================================
 | ARCHIVO: conectar_db.php
 | OBJETIVO:
 |   - Conectarse a la base de datos del proyecto
 |   - Funcionar tanto en LOCAL (XAMPP / Windows)
 |     como en AZURE App Service Linux
 |
 | NO MODIFICAR:
 |   - No colocar usuarios ni contraseñas aquí
 |   - Azure usa VARIABLES DE ENTORNO
 |=================================================
*/

// -------------------------------------------------
// 1. Detectar si estamos en Azure
// -------------------------------------------------
// Azure App Service siempre define la variable
// WEBSITE_SITE_NAME, en local NO existe
$isAzure = getenv('WEBSITE_SITE_NAME') !== false;

// -------------------------------------------------
// 2. Intentar conexión
// -------------------------------------------------
try {

    // =============================================
    // ===== CONEXIÓN EN AZURE ======================
    // =============================================
    if ($isAzure) {

  

        $server   = getenv('DB_HOST');
        $database = getenv('DB_NAME');
        $user     = getenv('DB_USER');
        $password = getenv('DB_PASS');

        // Validación mínima por seguridad
        if (!$server || !$database || !$user || !$password) {
            throw new Exception('Variables de entorno de BD no configuradas en Azure.');
        }

        // Cadena de conexión para Azure SQL
        $conexion = new PDO(
            "sqlsrv:server=$server;Database=$database;Encrypt=yes;TrustServerCertificate=no",
            $user,
            $password,
            [
                // Lanzar excepciones en errores
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                // Forzar UTF-8 (acentos, ñ, etc.)
                PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
            ]
        );

    // =============================================
    // ===== CONEXIÓN LOCAL (XAMPP / WINDOWS) =======
    // =============================================
    } else {

        /*
         | En local se usa:
         | - SQL Server Express
         | - Autenticación de Windows
         | - NO se requiere usuario ni contraseña
         */

        $server   = "localhost\\SQLEXPRESS";
        $database = "BD_MONITOREOS";

        $conexion = new PDO(
            "sqlsrv:server=$server;Database=$database",
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
            ]
        );
    }

} catch (Throwable $e) {

    /*
     | IMPORTANTE:
     | - No mostrar mensajes sensibles en producción
     | - No usar die($e->getMessage())
     | - Azure registra el error internamente
     */

    http_response_code(500);
    exit('❌ Error de conexión a la base de datos.');
}
