<?php
/**
 * ARCHIVO: /includes_partes_fijas/seguridad.php
 * =========================================================
 * SEGURIDAD CENTRALIZADA DEL SISTEMA – PRODUCCIÓN AZURE
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
if (!defined('BASE_URL')) {
    throw new RuntimeException('BASE_URL no está definido.');
}

/* =========================================================
 * 3) ROLES DEL SISTEMA
 * ========================================================= */
const ROLE_ADMIN        = 1;
const ROLE_COORD_QA     = 2;
const ROLE_COORD_CX     = 3;
const ROLE_SUPERVISOR   = 4;
const ROLE_AGENTE_QA    = 5;
const ROLE_GERENTE      = 6;
const ROLE_SIN_ACCESO   = 7;

/* =========================================================
 * 3.1) PERMISOS DINÁMICOS
 * ========================================================= */

function has_permission(string $codigo): bool {

    if (empty($_SESSION['permisos']) || !is_array($_SESSION['permisos'])) {
        return false;
    }

    return in_array($codigo, $_SESSION['permisos'], true);
}

function require_permission(string $codigo): void {

    if (!has_permission($codigo)) {
        $_SESSION['flash_err'] = "No tienes permisos para acceder a este módulo.";
        header("Location: " . BASE_URL . "/vistas_pantallas/menu.php");
        exit;
    }
}

/* =========================================================
 * 4) MATRIZ DE PERMISOS
 * ========================================================= */

function role_can_see_all_areas(): bool {

    if (has_permission('ver_todas_areas')) {
        return true;
    }

    $rol = (int)($_SESSION['id_rol'] ?? 0);

    return in_array($rol, [
        ROLE_ADMIN,
        ROLE_COORD_QA,
        ROLE_AGENTE_QA,
        ROLE_GERENTE
    ], true);
}

function role_can_create(): bool {

    if (has_permission('crear_monitoreo')) {
        return true;
    }

    return role_can_see_all_areas();
}

function role_can_correct(): bool {
    return has_permission('corregir_monitoreo');
}

function role_is_readonly(): bool {

    if (has_permission('solo_lectura')) {
        return true;
    }

    $rol = (int)($_SESSION['id_rol'] ?? 0);

    return in_array($rol, [
        ROLE_SUPERVISOR,
        ROLE_COORD_CX
    ], true);
}

/* =========================================================
 * 5) AUTENTICACIÓN
 * ========================================================= */

function require_login(): void {

    if (empty($_SESSION['id_usuario'])) {
        header('Location: ' . BASE_URL . '/');
        exit;
    }

    if ((int)($_SESSION['id_rol'] ?? 0) === ROLE_SIN_ACCESO) {
        http_response_code(403);
        exit('403 - Su rol no tiene acceso al sistema.');
    }
}

/* =========================================================
 * 6) FORZAR CAMBIO DE CONTRASEÑA
 * ========================================================= */

function force_password_change(): void {

    if (!empty($_SESSION['debe_cambiar_password'])) {

        if (strpos($_SERVER['PHP_SELF'], 'cambiar_password.php') === false) {

            header('Location: ' . BASE_URL . '/vistas_pantallas/cambiar_password.php');
            exit;
        }
    }
}

/* =========================================================
 * 7) FILTRO DE ÁREA PARA SQL
 * ========================================================= */

function area_filter_sql(string $campoArea = 'id_area'): array {

    /* usuarios que ven todo */
    if (role_can_see_all_areas()) {
        return ['', []];
    }

    /* NUEVO: múltiples áreas */
    $areas = $_SESSION['areas'] ?? [];

    if (is_array($areas) && count($areas) > 0) {

        $placeholders = [];
        $params = [];

        foreach ($areas as $i => $area) {

            $key = ":area{$i}";
            $placeholders[] = $key;
            $params[$key] = (int)$area;

        }

        return [
            " AND {$campoArea} IN (" . implode(',', $placeholders) . ") ",
            $params
        ];
    }

    /* fallback: sistema antiguo */
    $idArea = (int)($_SESSION['id_area'] ?? 0);

    if ($idArea <= 0) {
        return [' AND 1 = 0 ', []];
    }

    return [
        " AND {$campoArea} = :id_area ",
        [':id_area' => $idArea]
    ];
}

/* =========================================================
 * 8) HELPERS
 * ========================================================= */

function can_create(): bool { return role_can_create(); }
function can_correct(): bool { return role_can_correct(); }
function is_readonly(): bool { return role_is_readonly(); }
function can_see_all_areas(): bool { return role_can_see_all_areas(); }