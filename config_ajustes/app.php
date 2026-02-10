<?php
declare(strict_types=1);

/*
 |=================================================
 | ARCHIVO: app.php
 | CONFIGURACIÓN GENERAL
 |=================================================
 | ✔ Compatible con Azure App Service Linux
 | ✔ No provoca errores 404
 | ✔ No acopla el código a una URL fija
 | ✔ Portátil (local, staging, producción)
 */

// En Azure la aplicación vive en la raíz del dominio.
// NO usar BASE_URL para redirecciones.
$BASE_URL = '';

// Zona horaria (recomendado)
date_default_timezone_set('America/Guayaquil');
