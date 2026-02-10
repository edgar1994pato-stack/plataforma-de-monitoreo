<?php
/**
 * ARCHIVO: /includes_partes_fijas/seguridad.php
 * =========================================================
 * SEGURIDAD CENTRALIZADA DEL SISTEMA (FINAL)
 *
 * ✔ Autenticación (login)
 * ✔ Autorización por ROL
 * ✔ Autorización por ÁREA
 * ✔ Forzar cambio de contraseña
 *
 * DISEÑADO PARA:
 * - Escalar roles sin tocar vistas ni SP
 * - Mantener lógica de negocio intacta
 */

/* =========================================================
 * 1) SESIÓN SEGURA
 * ========================================================= */
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
 * 2) BASE URL
 * ========================================================= */
$BASE_URL = $BASE_URL ?? '/plataforma_de_monitoreo';

/* =========================================================
 * 3) ROLES DEL SISTEMA (IDs OFICIALES)
 * ========================================================= */
const ROLE_ADMIN        = 1; // ADMINISTRADOR DEL SISTEMA
const ROLE_COORD_QA     = 2; // COORDINADOR DE ATC MONITOREO Y CALIDAD
const ROLE_COORD_CX     = 3; // COORDINADOR DE EXPERIENCIA AL CLIENTE
const ROLE_SUPERVISOR   = 4; // SUPERVISOR DE OMNICANALIDAD
const ROLE_AGENTE_QA    = 5; // AGENTE DE MONITOREO
const ROLE_GERENTE      = 6; // GERENTE DE PROYECTOS, PROCESOS Y MEJORA CONTINUA
const ROLE_SIN_ACCESO   = 7; // ASESOR (SIN ACCESO)

/* =========================================================
 * 4) MATRIZ DE PERMISOS (ÚNICA FUENTE DE VERDAD)
 * ========================================================= */

/**
 * ROLES QUE PUEDEN VER TODAS LAS ÁREAS
 */
function role_can_see_all_areas(): bool {
    $rol = (int)($_SESSION['id_rol'] ?? 0);
    return in_array($rol, [
        ROLE_ADMIN,
        ROLE_COORD_QA,
        ROLE_AGENTE_QA,
        ROLE_GERENTE,
    ], true);
}

/**
 * ROLES QUE PUEDEN CREAR MONITOREOS
 */
function role_can_create(): bool {
    $rol = (int)($_SESSION['id_rol'] ?? 0);
    return in_array($rol, [
        ROLE_ADMIN,
        ROLE_COORD_QA,
        ROLE_AGENTE_QA,
        ROLE_GERENTE,
    ], true);
}

/**
 * ROLES QUE PUEDEN CORREGIR MONITOREOS
 */
function role_can_correct(): bool {
    $rol = (int)($_SESSION['id_rol'] ?? 0);
    return in_array($rol, [
        ROLE_ADMIN,
        ROLE_COORD_QA,
        ROLE_AGENTE_QA,
        ROLE_GERENTE,
    ], true);
}

/**
 * ROLES SOLO LECTURA
 */
function role_is_readonly(): bool {
    $rol = (int)($_SESSION['id_rol'] ?? 0);
    return in_array($rol, [
        ROLE_SUPERVISOR,
        ROLE_COORD_CX,
    ], true);
}

/* =========================================================
 * 5) AUTENTICACIÓN
 * ========================================================= */
function require_login(): void {
    global $BASE_URL;

    if (empty($_SESSION['id_usuario'])) {
        header("Location: {$BASE_URL}/vistas_pantallas/login.php");
        exit;
    }

    // Rol sin acceso bloqueado aquí mismo
    if ((int)($_SESSION['id_rol'] ?? 0) === ROLE_SIN_ACCESO) {
        http_response_code(403);
        exit('403 - Su rol no tiene acceso al sistema.');
    }
}

/* =========================================================
 * 6) FORZAR CAMBIO DE CONTRASEÑA
 * ========================================================= */
function force_password_change(): void {
    global $BASE_URL;

    if (!empty($_SESSION['debe_cambiar_password'])) {
        if (strpos($_SERVER['PHP_SELF'], 'cambiar_password.php') === false) {
            header("Location: {$BASE_URL}/vistas_pantallas/cambiar_password.php");
            exit;
        }
    }
}

/* =========================================================
 * 7) FILTRO DE ÁREA PARA SQL (BACKEND)
 * ========================================================= */
function area_filter_sql(string $campoArea = 'id_area'): array {

    $idArea = (int)($_SESSION['id_area'] ?? 0);

    if (role_can_see_all_areas()) {
        return ['', []]; // ve todo
    }

    if ($idArea <= 0) {
        return [' AND 1 = 0 ', []]; // no ve nada
    }

    return [
        " AND {$campoArea} = :id_area ",
        [':id_area' => $idArea]
    ];
}

/* =========================================================
 * 8) HELPERS DE CONVENIENCIA (FRONTEND)
 * ========================================================= */
function can_create(): bool {
    return role_can_create();
}

function can_correct(): bool {
    return role_can_correct();
}

function is_readonly(): bool {
    return role_is_readonly();
}

function can_see_all_areas(): bool {
    return role_can_see_all_areas();
}
