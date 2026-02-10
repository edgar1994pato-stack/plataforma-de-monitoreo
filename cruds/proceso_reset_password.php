<?php
/**
 * ARCHIVO: /cruds/proceso_reset_password.php
 * =========================================================
 * BACKEND: GUARDAR NUEVA CONTRASEÑA (PASO 4 - DEFINITIVO)
 *
 * SE UTILIZA EN:
 *  - Recibe el POST desde:
 *      /vistas_pantallas/reset_password.php
 *
 * QUÉ HACE:
 *  1) Lee token + nueva contraseña + confirmación
 *  2) Valida token en BD:
 *      - exista
 *      - no esté expirado
 *      - usuario activo
 *  3) Valida política mínima de contraseña
 *  4) Genera hash seguro con password_hash()
 *  5) Actualiza password mediante SP oficial (blindado):
 *      dbo.PR_USUARIO_CAMBIAR_PASSWORD
 *  6) Limpia token_recuperacion y expiracion_token (para evitar reutilización)
 *  7) Redirige al login con mensaje de éxito
 *
 * NORMATIVA DE SEGURIDAD:
 *  - No guarda contraseñas en texto plano
 *  - Token no reutilizable (se limpia)
 *  - Mensajes neutros en fallos de token
 *  - Validación backend obligatoria
 */

ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

require_once '../config_ajustes/conectar_db.php';

$BASE_URL = "/plataforma_de_monitoreo";


/* =========================================================
 * 1) LEER POST (token + passwords)
 * =========================================================
 * DE DÓNDE VIENE:
 *  - reset_password.php (form POST)
 */
$token   = trim($_POST['token'] ?? '');
$pass1   = (string)($_POST['new_password'] ?? '');
$pass2   = (string)($_POST['new_password_confirm'] ?? '');


/* =========================================================
 * 2) VALIDACIONES BÁSICAS
 * =========================================================
 * NORMATIVA:
 *  - No continuar si token o passwords faltan
 */
if ($token === '' || strlen($token) < 20) {
    $_SESSION['reset_msg'] = 'Enlace inválido o expirado. Solicite uno nuevo.';
    header("Location: {$BASE_URL}/vistas_pantallas/recuperar_password.php");
    exit;
}

if ($pass1 === '' || $pass2 === '') {
    $_SESSION['reset_msg'] = 'Complete la nueva contraseña y su confirmación.';
    header("Location: {$BASE_URL}/vistas_pantallas/reset_password.php?token=" . urlencode($token));
    exit;
}

if ($pass1 !== $pass2) {
    $_SESSION['reset_msg'] = 'Las contraseñas no coinciden.';
    header("Location: {$BASE_URL}/vistas_pantallas/reset_password.php?token=" . urlencode($token));
    exit;
}


/* =========================================================
 * 3) POLÍTICA MÍNIMA DE CONTRASEÑA (RECOMENDADA)
 * =========================================================
 * NORMATIVA:
 *  - Evitar contraseñas débiles
 *  - Debe ser fácil de aplicar (sin complicar a usuarios)
 *
 * REGLAS:
 *  - mínimo 10 caracteres
 *  - al menos 1 mayúscula
 *  - al menos 1 minúscula
 *  - al menos 1 número
 */
$minLen = 10;

$tieneMayus = preg_match('/[A-Z]/', $pass1);
$tieneMinus = preg_match('/[a-z]/', $pass1);
$tieneNum   = preg_match('/[0-9]/', $pass1);

if (strlen($pass1) < $minLen || !$tieneMayus || !$tieneMinus || !$tieneNum) {
    $_SESSION['reset_msg'] = "Contraseña débil. Use mínimo {$minLen} caracteres, con mayúscula, minúscula y número.";
    header("Location: {$BASE_URL}/vistas_pantallas/reset_password.php?token=" . urlencode($token));
    exit;
}


/* =========================================================
 * 4) VALIDAR TOKEN EN BD (EXISTE Y NO EXPIRADO)
 * =========================================================
 * NORMATIVA:
 *  - El token expira y solo sirve 1 vez
 *  - Si falla: mensaje neutro
 */
try {

    $stmt = $conexion->prepare("
        SELECT TOP 1
            id_usuario
        FROM dbo.USUARIOS
        WHERE token_recuperacion = :token
          AND expiracion_token IS NOT NULL
          AND expiracion_token >= GETDATE()
          AND activo = 1
    ");

    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$user) {
        $_SESSION['reset_msg'] = 'Enlace inválido o expirado. Solicite uno nuevo.';
        header("Location: {$BASE_URL}/vistas_pantallas/recuperar_password.php");
        exit;
    }

    $idUsuario = (int)$user['id_usuario'];


    /* =====================================================
     * 5) GENERAR HASH SEGURO (DEFINITIVO)
     * =====================================================
     * NORMATIVA:
     *  - Nunca guardar texto plano
     *  - password_hash genera bcrypt/argon2 según PHP
     */
    $nuevoHash = password_hash($pass1, PASSWORD_DEFAULT);

    if (!$nuevoHash) {
        $_SESSION['reset_msg'] = 'No se pudo generar la contraseña. Intente nuevamente.';
        header("Location: {$BASE_URL}/vistas_pantallas/reset_password.php?token=" . urlencode($token));
        exit;
    }


    /* =====================================================
     * 6) ACTUALIZAR CONTRASEÑA MEDIANTE SP OFICIAL (BLINDADO)
     * =====================================================
     * SP:
     *  dbo.PR_USUARIO_CAMBIAR_PASSWORD(@id_usuario, @nuevo_password_hash)
     *
     * NORMATIVA:
     *  - El SP NO acepta texto plano (ya lo blindamos en Paso 1)
     */
    $sp = $conexion->prepare("EXEC dbo.PR_USUARIO_CAMBIAR_PASSWORD :id, :hash");
    $sp->execute([
        ':id'   => $idUsuario,
        ':hash' => $nuevoHash
    ]);

    $res = $sp->fetch(PDO::FETCH_ASSOC);
    $sp->closeCursor();

    $rows = (int)($res['rows_afectadas'] ?? 0);

    if ($rows !== 1) {
        $_SESSION['reset_msg'] = 'No se pudo actualizar la contraseña. Intente nuevamente.';
        header("Location: {$BASE_URL}/vistas_pantallas/reset_password.php?token=" . urlencode($token));
        exit;
    }


    /* =====================================================
     * 7) LIMPIAR TOKEN (NO REUTILIZABLE)
     * =====================================================
     * NORMATIVA:
     *  - Token de recuperación SOLO 1 vez
     *  - Se elimina para impedir reuso
     */
    $clean = $conexion->prepare("
        UPDATE dbo.USUARIOS
        SET token_recuperacion = NULL,
            expiracion_token   = NULL,
            updated_at         = SYSDATETIME()
        WHERE id_usuario = :id
    ");
    $clean->execute([':id' => $idUsuario]);


    /* =====================================================
     * 8) REDIRECCIÓN FINAL A LOGIN (ÉXITO)
     * =====================================================
     */
    $_SESSION['login_error'] = 'Contraseña actualizada. Ya puede iniciar sesión.';
    header("Location: {$BASE_URL}/vistas_pantallas/login.php");
    exit;

} catch (Throwable $e) {

    /* =====================================================
     * 9) ERROR GENERAL (NEUTRO)
     * =====================================================
     */
    $_SESSION['reset_msg'] = 'No se pudo procesar la solicitud. Intente nuevamente.';
    header("Location: {$BASE_URL}/vistas_pantallas/reset_password.php?token=" . urlencode($token));
    exit;
}
