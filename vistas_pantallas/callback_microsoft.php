<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';

/* =====================================================
 * CONFIGURACIÓN DE COOKIE SEGURA PARA OAUTH MICROSOFT
 * =====================================================
 * Necesario para que Azure / Microsoft puedan redirigir
 * correctamente la sesión después del login.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* =====================================================
 * VARIABLES DE ENTORNO DE MICROSOFT ENTRA ID
 * ===================================================== */
$clientId     = getenv('MS_CLIENT_ID');
$clientSecret = getenv('MS_CLIENT_SECRET');

/* Tenant permitido (organizations permite cuentas corporativas) */
$tenantId = "organizations";

if (!$clientId || !$clientSecret) {
    http_response_code(500);
    exit('❌ Faltan variables MS_CLIENT_ID o MS_CLIENT_SECRET.');
}

$redirectUri = BASE_URL . '/vistas_pantallas/callback_microsoft.php';

/* =====================================================
 * VALIDACIÓN DE SEGURIDAD CSRF (STATE)
 * ===================================================== */
$state = $_GET['state'] ?? '';

if (
    empty($_SESSION['ms_state']) ||
    !hash_equals($_SESSION['ms_state'], $state)
) {
    http_response_code(400);
    exit('❌ State inválido.');
}

unset($_SESSION['ms_state']);
unset($_SESSION['ms_state_time']);

/* =====================================================
 * RECIBIR CODE DE MICROSOFT
 * ===================================================== */
$code = $_GET['code'] ?? '';

if (!$code) {
    $err = $_GET['error_description'] ?? ($_GET['error'] ?? 'No se recibió code.');
    exit('❌ Error Microsoft: ' . h($err));
}

/* =====================================================
 * INTERCAMBIAR CODE POR TOKEN (OAUTH2)
 * ===================================================== */
$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

$postData = [
    'client_id'     => $clientId,
    'scope'         => 'openid profile email',
    'code'          => $code,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
    'client_secret' => $clientSecret
];

$ch = curl_init($tokenUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode !== 200 || !isset($data['id_token'])) {
    exit('❌ Error obteniendo token.');
}

/* =====================================================
 * LEER INFORMACIÓN DEL USUARIO DESDE EL TOKEN
 * ===================================================== */
$idToken = $data['id_token'];

$parts = explode('.', $idToken);

$payload = json_decode(
    base64_decode(strtr($parts[1], '-_', '+/')),
    true
);

$correo = $payload['preferred_username'] ?? '';
$nombre = $payload['name'] ?? '';

if (!$correo) {
    exit('❌ No se pudo obtener el correo.');
}

/* =====================================================
 * SEGURIDAD: SOLO CORREOS CORPORATIVOS
 * ===================================================== */
if (!str_ends_with($correo, '@alfanet.net.ec')) {
    exit('⛔ Solo cuentas corporativas permitidas.');
}

/* =====================================================
 * VALIDAR QUE EL USUARIO EXISTA EN LA BASE DE DATOS
 * ===================================================== */
$stmt = $conexion->prepare("
    SELECT id_usuario,
           correo_corporativo,
           nombre_completo,
           id_rol,
           id_area,
           activo,
           debe_cambiar_password
    FROM dbo.USUARIOS
    WHERE correo_corporativo = ?
    AND activo = 1
");

$stmt->execute([$correo]);

$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    exit('⛔ Usuario no autorizado en la plataforma.');
}

/* =====================================================
 * CREAR SESIÓN DEL USUARIO
 * =====================================================
 * Aquí se guardan los datos principales del usuario
 * que utilizará el sistema durante la sesión.
 */
$_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
$_SESSION['correo_corporativo'] = $usuario['correo_corporativo'];
$_SESSION['nombre_completo'] = $usuario['nombre_completo'];
$_SESSION['id_rol'] = (int)$usuario['id_rol'];
$_SESSION['id_area'] = (int)$usuario['id_area'];
$_SESSION['debe_cambiar_password'] = (int)$usuario['debe_cambiar_password'];

/* =====================================================
 * CARGAR PERMISOS DEL ROL DEL USUARIO
 * =====================================================
 * Este bloque replica exactamente la lógica que utiliza
 * el login tradicional del sistema.
 *
 * Se consultan los permisos asociados al rol del usuario
 * y se guardan en la sesión para que funciones como
 * has_permission() puedan validar accesos en el sistema.
 *
 * Sin este bloque, el menú queda vacío porque
 * has_permission() devuelve FALSE.
 */
$_SESSION['permisos'] = [];

$stmtPerm = $conexion->prepare("
    SELECT p.codigo
    FROM dbo.ROL_PERMISO rp
    INNER JOIN dbo.PERMISOS p ON rp.id_permiso = p.id_permiso
    WHERE rp.id_rol = :id_rol
");

$stmtPerm->execute([
    ':id_rol' => $_SESSION['id_rol']
]);

while ($rowPerm = $stmtPerm->fetch(PDO::FETCH_ASSOC)) {
    $_SESSION['permisos'][] = $rowPerm['codigo'];
}

$stmtPerm->closeCursor();

/* =====================================================
 * REDIRECCIÓN FINAL AL MENÚ PRINCIPAL
 * ===================================================== */
header('Location: ' . BASE_URL . '/vistas_pantallas/menu.php');
exit;