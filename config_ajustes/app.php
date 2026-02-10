<?php
declare(strict_types=1);

/*
 |=================================================
 | app.php – CONFIGURACIÓN GENERAL (PRODUCCIÓN)
 |=================================================
 | ✔ Azure App Service Linux
 | ✔ nginx
 | ✔ PHP 8.2
 | ✔ Variables desde Application Settings
 */

// URL base desde Azure
define('BASE_URL', rtrim(getenv('APP_URL'), '/'));

// Zona horaria
date_default_timezone_set('America/Guayaquil');
