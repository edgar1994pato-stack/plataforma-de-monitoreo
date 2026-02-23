<?php declare(strict_types=1);

/* |=================================================
   | ARCHIVO: app.php
   | CONFIGURACIÓN GLOBAL – PRODUCCIÓN AZURE
   |=================================================
   | ✔ Funciona entrando por index.php o directo a vistas
   | ✔ Define BASE_PATH y BASE_URL una sola vez
   | ✔ Evita errores 404 en nginx
   | ✔ No toca lógica de negocio ni diseño
*/

// =================================================
// BASE PATH ABSOLUTO DEL PROYECTO
// =================================================

// Raíz del proyecto (ej: /home/site/wwwroot)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// =================================================
// BASE URL PÚBLICA (desde Azure App Settings)
// =================================================

// APP_URL = https://plataforma-monitoreo-web-produccion.azurewebsites.net
define('BASE_URL', rtrim(getenv('APP_URL'), '/'));

// =================================================
// ZONA HORARIA
// =================================================

date_default_timezone_set('America/Guayaquil');