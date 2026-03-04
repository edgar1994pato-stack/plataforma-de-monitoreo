<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Validaciones iniciales */
$clientId     = getenv('MS_CLIENT_ID');
$tenantId     = getenv('MS_TENANT_ID');
$clientSecret = getenv('MS_CLIENT_SECRET');

if (!$clientId || !$tenantId || !$clientSecret) {
    http_response_code(500);
    exit('❌ Faltan variables MS_CLIENT_ID / MS_TENANT_ID / MS_CLIENT_SECRET.');
}

$redirectUri = BASE_URL . '/vistas_pantallas/callback_microsoft.php';

/* 1) Validar state (anti-CSRF) */
$state = $_GET['state'] ?? '';
if (empty($_SESSION['ms_state']) || !hash_equals($_SESSION['ms_state'], $state)) {
    http_response_code(400);
    exit('❌ State inválido. Intenta nuevamente.');
}
unset($_SESSION['ms_state']);

/* 2) Recibir code */
$code = $_GET['code'] ?? '';
if ($code === '') {
    $err = $_GET['error_description'] ?? ($_GET['error'] ?? 'No se recibió code.');
    http_response_code(400);
    exit('❌ Error de Microsoft: ' . h($err));
}

/* 3) Intercambiar code por tokens */
$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

$postData = [
    'client_id'     => $clientId,
    'scope'         => 'openid profile email',
    'code'          => $code,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
    'client_secret' => $clientSecret,
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postData),
    CURLOPT_TIMEOUT        => 20,
]);
$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    exit('❌ Error CURL: ' . h($curlErr));
}

$data = json_decode($response, true);
if ($httpCode !== 200 || !is_array($data)) {
    http_response_code(500);
    exit('❌ Respuesta inválida del token endpoint.');
}

/* 4) Leer id_token (JWT) */
$idToken = $data['id_token'] ?? '';
if ($idToken === '') {
    http_response_code(500);
    exit('❌ No se recibió id_token. Revisa scopes/redirect URI.');
}

/* Decodificar payload del JWT */
$parts = explode('.', $idToken);
if (count($parts) < 2) {
    http_response_code(500);
    exit('❌ id_token inválido.');
}

$payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
$payload = json_decode($payloadJson, true);

$correo = $payload['preferred_username'] ?? ($payload['email'] ?? '');
$nombre = $payload['name'] ?? '';

if ($correo === '') {
    http_response_code(500);
    exit('❌ No se pudo obtener el correo del token.');
}

/* 5) Validar en tu BD (AUTORIZACIÓN) */
$stmt = $conexion->prepare("
    SELECT id_usuario, correo_corporativo, nombre_completo, id_rol, id_area, activo, debe_cambiar_password
    FROM dbo.USUARIOS
    WHERE correo_corporativo = ?
      AND activo = 1
");
$stmt->execute([$correo]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    http_response_code(403);
    exit('⛔ Usuario no autorizado en la plataforma (no existe o está inactivo).');
}

/* 6) Crear sesión con tus keys reales */
$_SESSION['id_usuario']       = (int)$usuario['id_usuario'];
$_SESSION['correo_corporativo']= (string)$usuario['correo_corporativo'];
$_SESSION['nombre_completo']  = (string)$usuario['nombre_completo'];
$_SESSION['id_rol']           = (int)($usuario['id_rol'] ?? 0);
$_SESSION['id_area']          = (int)($usuario['id_area'] ?? 0);

/* Opcional: si tu sistema usa esta bandera */
$_SESSION['debe_cambiar_password'] = (int)($usuario['debe_cambiar_password'] ?? 0);

/* IMPORTANTE:
   Aquí NO tocamos tu lógica actual.
   Tu sistema seguirá cargando permisos en sesión como ya lo hace.
*/

header('Location: ' . BASE_URL . '/vistas_pantallas/menu.php');
exit;