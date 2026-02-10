<?php

$server   = getenv('DB_HOST');
$database = getenv('DB_NAME');
$user     = getenv('DB_USER');
$password = getenv('DB_PASS');

$connectionInfo = [
    "Database" => $database,
    "UID" => $user,
    "PWD" => $password,
    "Encrypt" => true,
    "TrustServerCertificate" => false,
];

$conn = sqlsrv_connect($server, $connectionInfo);

if ($conn) {
    echo "✅ CONEXIÓN EXITOSA A SQL SERVER";
} else {
    echo "❌ ERROR DE CONEXIÓN<br><br>";
    print_r(sqlsrv_errors());
}
