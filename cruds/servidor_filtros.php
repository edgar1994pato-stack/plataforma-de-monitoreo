<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';


require_login();
force_password_change();

header('Content-Type: application/json; charset=utf-8');

$tipo = strtolower(trim((string)($_GET['tipo'] ?? '')));

/* =========================================================
   HELPERS
========================================================= */
function json_ok($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'mensaje' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ✅ Helper escape HTML */
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   CONTEXTO DE SEGURIDAD
========================================================= */
$veTodo = can_see_all_areas();
$idAreaSesion = (int)($_SESSION['id_area'] ?? 0);

if (!$veTodo && $idAreaSesion <= 0) {
    json_error('Acceso denegado: usuario sin área asignada.', 403);
}

/**
 * Devuelve el id_area efectivo:
 * - Si ve todo: toma el GET
 * - Si NO ve todo: ignora GET y toma sesión
 */
function area_effective_from_get(string $key = 'id_area'): int {
    global $veTodo, $idAreaSesion;
    $idAreaGet = (int)($_GET[$key] ?? 0);
    return $veTodo ? $idAreaGet : $idAreaSesion;
}

/**
 * Valida que una cola pertenezca al área efectiva cuando NO ve todo
 */
function enforce_cola_in_area(int $idCola): void {
    global $conexion, $veTodo, $idAreaSesion;

    if ($veTodo) return;

    $st = $conexion->prepare("
        SELECT TOP 1 id_area
        FROM dbo.COLAS
        WHERE id_cola = ?
          AND fecha_fin IS NULL
    ");
    $st->execute([$idCola]);
    $idAreaDb = (int)($st->fetchColumn() ?: 0);

    if ($idAreaDb <= 0 || $idAreaDb !== $idAreaSesion) {
        json_error('Acceso denegado: la cola no pertenece a tu área.', 403);
    }
}

/* ===========================
   1) FILTRAR AGENTES (por área)
   GET: ?tipo=agentes&id_area=#
=========================== */
if ($tipo === 'agentes') {
    $id_area = area_effective_from_get('id_area');
    if ($id_area <= 0) json_ok([]);

    $sql = "
        SELECT A.id_agente_int, A.nombre_agente
        FROM dbo.AGENTES A
        WHERE A.id_area = ?
          AND ISNULL(A.estado,1) = 1
          AND NOT EXISTS (
                SELECT 1
                FROM dbo.AGENTE_AUSENCIAS X
                WHERE X.id_agente = A.id_agente_int
                  AND X.estado = 1
                  AND CAST(GETDATE() AS DATE) 
                      BETWEEN X.fecha_inicio AND X.fecha_fin
          )
        ORDER BY A.nombre_agente
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id_area]);

    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}


/* ===========================
   2) FILTRAR COLAS (por área)
   GET: ?tipo=colas&id_area=#
=========================== */
if ($tipo === 'colas') {
    $id_area = area_effective_from_get('id_area');
    if ($id_area <= 0) json_ok([]);

    $sql = "
        SELECT id_cola, nombre_cola
        FROM dbo.COLAS
        WHERE id_area = ?
          AND fecha_fin IS NULL
        ORDER BY nombre_cola
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id_area]);

    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ===========================
   3) FILTRAR SECCIONES (por área)
   GET: ?tipo=secciones&id_area=#
=========================== */
if ($tipo === 'secciones') {
    $id_area = area_effective_from_get('id_area');
    if ($id_area <= 0) json_ok([]);

    $sql = "
        SELECT id_seccion, nombre_seccion
        FROM dbo.SECCIONES
        WHERE id_area = ?
          AND ISNULL(estado,1) = 1
        ORDER BY nombre_seccion
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id_area]);

    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ===========================
   4) ASPECTOS (desde PREGUNTAS)
   GET: ?tipo=aspectos
=========================== */
if ($tipo === 'aspectos') {
    if ($veTodo) {
        $sql = "
            SELECT DISTINCT LTRIM(RTRIM(P.ASPECTO)) AS aspecto
            FROM dbo.PREGUNTAS P
            WHERE ISNULL(P.estado,1) = 1
              AND P.ASPECTO IS NOT NULL
              AND LTRIM(RTRIM(P.ASPECTO)) <> ''
            ORDER BY LTRIM(RTRIM(P.ASPECTO))
        ";
        $stmt = $conexion->query($sql);
        json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $sql = "
        SELECT DISTINCT LTRIM(RTRIM(P.ASPECTO)) AS aspecto
        FROM dbo.PREGUNTAS P
        WHERE ISNULL(P.estado,1) = 1
          AND P.id_area = ?
          AND P.ASPECTO IS NOT NULL
          AND LTRIM(RTRIM(P.ASPECTO)) <> ''
        ORDER BY LTRIM(RTRIM(P.ASPECTO))
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$idAreaSesion]);
    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ===========================
   5) DIRECCIONES (desde PREGUNTAS)
   GET: ?tipo=direcciones
=========================== */
if ($tipo === 'direcciones') {
    if ($veTodo) {
        $sql = "
            SELECT DISTINCT LTRIM(RTRIM(P.DIRECCION)) AS direccion
            FROM dbo.PREGUNTAS P
            WHERE ISNULL(P.estado,1) = 1
              AND P.DIRECCION IS NOT NULL
              AND LTRIM(RTRIM(P.DIRECCION)) <> ''
            ORDER BY LTRIM(RTRIM(P.DIRECCION))
        ";
        $stmt = $conexion->query($sql);
        json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $sql = "
        SELECT DISTINCT LTRIM(RTRIM(P.DIRECCION)) AS direccion
        FROM dbo.PREGUNTAS P
        WHERE ISNULL(P.estado,1) = 1
          AND P.id_area = ?
          AND P.DIRECCION IS NOT NULL
          AND LTRIM(RTRIM(P.DIRECCION)) <> ''
        ORDER BY LTRIM(RTRIM(P.DIRECCION))
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$idAreaSesion]);
    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ===========================
   6) TIPOS (desde PREGUNTAS)
   GET: ?tipo=tipos
=========================== */
if ($tipo === 'tipos') {
    if ($veTodo) {
        $sql = "
            SELECT DISTINCT UPPER(LTRIM(RTRIM(P.TIPO))) AS tipo
            FROM dbo.PREGUNTAS P
            WHERE ISNULL(P.estado,1) = 1
              AND P.TIPO IS NOT NULL
              AND LTRIM(RTRIM(P.TIPO)) <> ''
            ORDER BY UPPER(LTRIM(RTRIM(P.TIPO)))
        ";
        $stmt = $conexion->query($sql);
        json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $sql = "
        SELECT DISTINCT UPPER(LTRIM(RTRIM(P.TIPO))) AS tipo
        FROM dbo.PREGUNTAS P
        WHERE ISNULL(P.estado,1) = 1
          AND P.id_area = ?
          AND P.TIPO IS NOT NULL
          AND LTRIM(RTRIM(P.TIPO)) <> ''
        ORDER BY UPPER(LTRIM(RTRIM(P.TIPO)))
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$idAreaSesion]);
    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ===========================
   7) GESTIONES (desde PONDERACION por cola)
   GET: ?tipo=gestiones&id_cola=#
=========================== */
if ($tipo === 'gestiones') {
    $id_cola = (int)($_GET['id_cola'] ?? 0);
    if ($id_cola <= 0) json_ok([]);

    enforce_cola_in_area($id_cola);

    $sql = "
        SELECT DISTINCT LTRIM(RTRIM(GESTION)) AS gestion
        FROM dbo.PONDERACION
        WHERE ID_COLA = ?
          AND ISNULL(ACTIVO,0) = 1
          AND GESTION IS NOT NULL
          AND LTRIM(RTRIM(GESTION)) <> ''
        ORDER BY LTRIM(RTRIM(GESTION))
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id_cola]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // fallback: si no hay activas, trae histórico
    if (!$rows) {
        $sql2 = "
            SELECT DISTINCT LTRIM(RTRIM(GESTION)) AS gestion
            FROM dbo.PONDERACION
            WHERE ID_COLA = ?
              AND GESTION IS NOT NULL
              AND LTRIM(RTRIM(GESTION)) <> ''
            ORDER BY LTRIM(RTRIM(GESTION))
        ";
        $st2 = $conexion->prepare($sql2);
        $st2->execute([$id_cola]);
        $rows = $st2->fetchAll(PDO::FETCH_ASSOC);
    }

    json_ok($rows);
}

/* ===========================
   8) OBTENER UMBRAL VIGENTE
   GET: ?tipo=umbral&id_cola=#
=========================== */
if ($tipo === 'umbral') {
    $id_cola = (int)($_GET['id_cola'] ?? 0);
    if ($id_cola <= 0) json_error('id_cola inválido.');

    enforce_cola_in_area($id_cola);

    $sql = "
        SELECT TOP 1 porcentaje_minimo
        FROM dbo.UMBRAL_APROBACION
        WHERE activo = 1
          AND id_cola = ?
          AND CONVERT(date, GETDATE()) BETWEEN fecha_inicio AND fecha_fin
        ORDER BY fecha_inicio DESC
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id_cola]);

    $umbral = $stmt->fetchColumn();
    $umbral = ($umbral === false || $umbral === null) ? 60 : (float)$umbral;

    json_ok([
        'id_cola' => $id_cola,
        'umbral'  => $umbral
    ]);
}

/* ===========================
   9) CARGAR PREGUNTAS (HTML)
   GET: ?tipo=preguntas&id_cola=#
   ✅ Mejorado para búsqueda sin romper lógica
=========================== */
if ($tipo === 'preguntas') {

    $id_cola = (int)($_GET['id_cola'] ?? 0);
    if ($id_cola <= 0) json_error('id_cola inválido.');

    enforce_cola_in_area($id_cola);

    /* ---- UMBRAL VIGENTE ---- */
    $sqlUmbral = "
        SELECT TOP 1 porcentaje_minimo
        FROM dbo.UMBRAL_APROBACION
        WHERE activo = 1
          AND id_cola = ?
          AND CONVERT(date, GETDATE()) BETWEEN fecha_inicio AND fecha_fin
        ORDER BY fecha_inicio DESC
    ";
    $stmtU = $conexion->prepare($sqlUmbral);
    $stmtU->execute([$id_cola]);
    $umbral = $stmtU->fetchColumn();
    $umbral = ($umbral === false || $umbral === null) ? 60 : (float)$umbral;

    /* ---- PREGUNTAS ---- */
    $sql = "
        SELECT
            S.id_seccion,
            S.nombre_seccion,
            P.id_pregunta,
            P.pregunta,
            UPPER(ISNULL(P.TIPO,'NORMAL')) AS TIPO,
            CAST(ISNULL(PON.PESO,0) AS DECIMAL(10,2)) AS PESO,
            PON.GESTION
        FROM dbo.PONDERACION PON
        INNER JOIN dbo.PREGUNTAS P ON PON.ID_PREGUNTA = P.id_pregunta
        INNER JOIN dbo.SECCIONES S ON P.id_seccion = S.id_seccion
        WHERE PON.ID_COLA = ?
          AND ISNULL(PON.ACTIVO,0) = 1
          AND ISNULL(P.estado,1) = 1
        ORDER BY S.id_seccion, P.id_pregunta
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id_cola]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        $emptyHtml = '
            <div class="text-center py-5 text-muted bg-white rounded border">
                <i class="bi bi-exclamation-triangle fs-1 opacity-25"></i>
                <p class="mt-2 mb-0">No hay preguntas activas para esta cola.</p>
                <div class="small">Verifica PONDERACION.ACTIVO=1 y que existan preguntas asociadas.</div>
            </div>
        ';
        json_ok([
            'html'   => $emptyHtml,
            'umbral' => $umbral,
            'meta'   => ['total' => 0, 'critico' => 0, 'impulsor' => 0, 'normal' => 0],
        ]);
    }

    /* --- AGRUPAR POR SECCIÓN --- */
    $secciones = [];
    $meta = ['total' => 0, 'critico' => 0, 'impulsor' => 0, 'normal' => 0];

    foreach ($rows as $r) {
        $idSec = (int)$r['id_seccion'];
        if (!isset($secciones[$idSec])) {
            $secciones[$idSec] = [
                'nombre' => $r['nombre_seccion'],
                'preguntas' => []
            ];
        }
        $secciones[$idSec]['preguntas'][] = $r;

        $meta['total']++;
        $t = strtoupper($r['TIPO'] ?? 'NORMAL');
        if ($t === 'CRITICO') $meta['critico']++;
        elseif ($t === 'IMPULSOR') $meta['impulsor']++;
        else $meta['normal']++;
    }

    /* --- HTML --- */
    ob_start();

    echo '<div class="row mt-4">';

    /* =========================
       MENÚ SECCIONES (izquierda)
    ========================= */
    echo '<div class="col-md-3">';
    echo '  <div class="list-group shadow-sm sticky-top" style="top:20px">';
    $first = true;
    foreach ($secciones as $idSec => $sec) {
        $active = $first ? 'active' : '';
        $count = count($sec['preguntas']);

        echo "
            <a class='list-group-item list-group-item-action {$active} fw-semibold'
               data-bs-toggle='list'
               href='#sec{$idSec}'>
                " . h($sec['nombre']) . "
                <span class='badge bg-primary rounded-pill float-end'
                      data-badge-seccion='" . h($sec['nombre']) . "'>{$count}</span>
            </a>
        ";

        $first = false;
    }
    echo '  </div>';
    echo '</div>';

    /* =========================
       TAB CONTENT (derecha)
    ========================= */
    echo '<div class="col-md-9">';
    echo '  <div class="tab-content bg-white rounded shadow-sm p-4">';

    $first = true;
    foreach ($secciones as $idSec => $sec) {
        $active = $first ? 'show active' : '';

        echo "<div class='tab-pane fade {$active}' id='sec{$idSec}' data-seccion='" . h($sec['nombre']) . "'>";

        echo "<div class='d-flex justify-content-between align-items-center border-bottom pb-2 mb-4'>";
        echo "  <h5 class='text-primary m-0'>" . h($sec['nombre']) . "</h5>";
        echo "  <span class='badge bg-light text-dark border'>Umbral: " . h($umbral) . "%</span>";
        echo "</div>";

        foreach ($sec['preguntas'] as $p) {
            $tipoP = strtoupper($p['TIPO'] ?? 'NORMAL');
            $peso = isset($p['PESO']) ? (float)$p['PESO'] : 0.0;

            $badgeTipo = '';
            if ($tipoP === 'CRITICO') {
                $badgeTipo = "<span class='badge bg-danger ms-2' style='font-size:.65rem'>⚠ CRÍTICO</span>";
            } elseif ($tipoP === 'IMPULSOR') {
                $badgeTipo = "<span class='badge bg-primary ms-2' style='font-size:.65rem'>⚡ IMPULSOR</span>";
            } else {
                $badgeTipo = "<span class='badge bg-secondary ms-2' style='font-size:.65rem'>NORMAL</span>";
            }

            $gestion = isset($p['GESTION']) && trim((string)$p['GESTION']) !== '' ? trim((string)$p['GESTION']) : null;
            $badgeGestion = $gestion
                ? "<span class='badge bg-light text-dark border ms-2' style='font-size:.65rem'>GESTIÓN: " . h($gestion) . "</span>"
                : "";

            $idPregunta = (int)$p['id_pregunta'];

            echo "
            <div class='card mb-3 pregunta-card estado-neutro'
                 data-seccion='" . h($sec['nombre']) . "'>
                <div class='card-body py-3'>
                    <div class='d-flex justify-content-between mb-2'>
                        <div class='fw-semibold'>
                            <span class='pregunta-texto'>" . h($p['pregunta']) . "</span>
                            {$badgeTipo}
                            {$badgeGestion}
                            <span class='badge bg-light text-dark border ms-2' style='font-size:.65rem'>
                                PESO: " . h(number_format($peso, 2)) . "
                            </span>
                        </div>

                        <button type='button'
                                class='btn btn-link btn-sm text-muted limpiar-btn'
                                data-target='{$idPregunta}'>
                            ✕ Limpiar
                        </button>
                    </div>

                    <div class='btn-group btn-group-sm' role='group'>
                        <input type='radio' class='btn-check respuesta'
                               data-estado='cumple'
                               data-peso='{$peso}'
                               data-tipo='{$tipoP}'
                               name='respuestas[{$idPregunta}]'
                               id='si{$idPregunta}'
                               value='SI'>
                        <label class='btn btn-outline-success px-4'
                               for='si{$idPregunta}'>Cumple</label>

                        <input type='radio' class='btn-check respuesta'
                               data-estado='falla'
                               data-peso='{$peso}'
                               data-tipo='{$tipoP}'
                               name='respuestas[{$idPregunta}]'
                               id='no{$idPregunta}'
                               value='NO'>
                        <label class='btn btn-outline-danger px-4'
                               for='no{$idPregunta}'>Falla</label>

                        <input type='radio' class='btn-check respuesta'
                               data-estado='na'
                               data-peso='{$peso}'
                               data-tipo='{$tipoP}'
                               name='respuestas[{$idPregunta}]'
                               id='na{$idPregunta}'
                               value='NO_APLICA'>
                        <label class='btn btn-outline-secondary px-3'
                               for='na{$idPregunta}'>N/A</label>
                    </div>
                </div>
            </div>
            ";
        }

        echo "</div>";
        $first = false;
    }

    echo '  </div>';
    echo '</div>';

    echo '</div>';

    $html = ob_get_clean();

    json_ok([
        'html'   => $html,
        'umbral' => $umbral,
        'meta'   => $meta
    ]);
}

/* ===========================
   DEFAULT
=========================== */
json_error('Tipo no soportado.', 400);
