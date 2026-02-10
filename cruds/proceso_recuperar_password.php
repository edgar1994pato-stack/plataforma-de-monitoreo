<?php
/**
 * ARCHIVO: /cruds/proceso_recuperar_password.php
 * =========================================================
 * BACKEND: RECUPERAR CONTRASEÑA (PASO 2)
 *
 * SE UTILIZA EN:
 *  - Recibe el POST desde:
 *      /vistas_pantallas/recuperar_password.php
 *
 * FLUJO:
 *  1) Usuario escribe su correo
 *  2) Este archivo:
 *      - valida formato
 *      - busca usuario activo (sin revelar si existe)
 *      - genera token seguro
 *      - guarda token y expiración en dbo.USUARIOS
 *      - redirige con mensaje neutro
 *
 * NORMATIVA DE SEGURIDAD:
 *  - Mensaje neutro: NO revela si el correo existe
 *  - Token fuerte y con expiración
 *  - En producción se enviaría por correo
 *  - En localhost se muestra token para pruebas (modo dev)
 */

ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

require_once '../config_ajustes/conectar_db.php';

$BASE_URL = "/plataforma_de_monitoreo";

// Detectar localhost para permitir flujo sin correo real
$esLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);


/* =========================================================
 * 1) LEER Y VALIDAR CORREO
 * =========================================================
 * DE DÓNDE VIENE:
 *  - input name="correo" en recuperar_password.php
 */
$correo = strtolower(trim($_POST['correo'] ?? ''));

// Mensaje neutro único (no revela existencia)
$mensajeNeutro = 'Si el correo está registrado, recibirá instrucciones para restablecer su contraseña.';

if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    // Mensaje neutro, siempre igual
    $_SESSION['recover_msg'] = $mensajeNeutro;
    header("Location: {$BASE_URL}/vistas_pantallas/recuperar_password.php");
    exit;
}


/* =========================================================
 * 2) LÓGICA PRINCIPAL
 * ========================================================= */
try {

    /* =====================================================
     * 2.1) BUSCAR USUARIO ACTIVO (SIN REVELAR)
     * =====================================================
     * NORMATIVA:
     *  - Aunque no exista, respondemos igual (mensaje neutro)
     */
    $stmt = $conexion->prepare("
        SELECT TOP 1
            id_usuario,
            correo_corporativo,
            activo
        FROM dbo.USUARIOS
        WHERE LOWER(LTRIM(RTRIM(correo_corporativo))) = :correo
          AND activo = 1
    ");
    $stmt->execute([':correo' => $correo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Si no existe usuario activo → responder neutro igualmente
    if (!$user) {
        $_SESSION['recover_msg'] = $mensajeNeutro;
        header("Location: {$BASE_URL}/vistas_pantallas/recuperar_password.php");
        exit;
    }


    /* =====================================================
     * 2.2) GENERAR TOKEN SEGURO + EXPIRACIÓN
     * =====================================================
     * NORMATIVA:
     *  - Token aleatorio, largo, no adivinable
     *  - Expira (ej: 30 minutos)
     *
     * IMPORTANTE:
     *  - Guardamos el token en BD (como tú ya tienes campo token_recuperacion)
     *  - En producción, ideal es guardar HASH del token, pero por tu modelo actual
     *    lo guardaremos en texto y lo endurecemos luego si quieres.
     */
    $token = bin2hex(random_bytes(32)); // 64 caracteres hex
    $expiraEnMinutos = 30;

    // Fecha expiración en formato compatible
    // SQL tiene USUARIOS.expiracion_token datetime
    $expiracion = (new DateTime())->add(new DateInterval('PT' . $expiraEnMinutos . 'M'))->format('Y-m-d H:i:s');


    /* =====================================================
     * 2.3) GUARDAR TOKEN EN BD
     * =====================================================
     * DÓNDE SE USA:
     *  - Luego en reset_password.php se valida token + expiración
     */
    $up = $conexion->prepare("
        UPDATE dbo.USUARIOS
        SET token_recuperacion = :token,
            expiracion_token   = :exp,
            updated_at         = SYSDATETIME(),
            debe_cambiar_password = 1
        WHERE id_usuario = :id
    ");

    $up->execute([
        ':token' => $token,
        ':exp'   => $expiracion,
        ':id'    => (int)$user['id_usuario']
    ]);


    /* =====================================================
     * 2.4) EN PRODUCCIÓN → ENVIAR EMAIL (PENDIENTE)
     * =====================================================
     * AQUÍ IRÍA:
     *  - mail() / PHPMailer / SMTP corporativo
     *  - con un link como:
     *      https://tu-dominio.com/plataforma_de_monitoreo/vistas_pantallas/reset_password.php?token=...
     *
     * PARA PRUEBAS EN LOCALHOST:
     *  - Guardamos en sesión un "link de prueba"
     *  - Así puedes continuar el flujo sin correo.
     */
    if ($esLocalhost) {
        $_SESSION['recover_dev_link'] =
            "{$BASE_URL}/vistas_pantallas/reset_password.php?token={$token}";
    } else {
        // Producción (pendiente implementar envío real)
        // Aquí solo dejamos el sistema listo.
    }


    /* =====================================================
     * 2.5) RESPUESTA NEUTRA
     * =====================================================
     * NORMATIVA:
     *  - Siempre el mismo mensaje
     */
    $_SESSION['recover_msg'] = $mensajeNeutro;

    header("Location: {$BASE_URL}/vistas_pantallas/recuperar_password.php");
    exit;

} catch (Throwable $e) {

    /* =====================================================
     * 3) MANEJO DE ERROR (NEUTRO)
     * =====================================================
     * NORMATIVA:
     *  - No exponer errores internos
     */
    $_SESSION['recover_msg'] = $mensajeNeutro;
    header("Location: {$BASE_URL}/vistas_pantallas/recuperar_password.php");
    exit;
}
