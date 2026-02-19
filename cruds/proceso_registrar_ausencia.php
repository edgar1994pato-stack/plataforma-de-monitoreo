<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();

$BASE_URL = BASE_URL;

$soloLectura = is_readonly();
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);

if ($soloLectura) {
    header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $BASE_URL/vistas_pantallas/listado_agentes.php");
    exit;
}

/* =============================
   RECIBIR DATOS
============================= */
$idAgente     = (int)($_POST['id_agente'] ?? 0);
$tipoAusencia = trim($_POST['tipo_ausencia'] ?? '');
$fechaInicio  = $_POST['fecha_inicio'] ?? '';
$fechaFin     = $_POST['fecha_fin'] ?? '';
$observacion  = trim($_POST['observacion'] ?? '');

if ($idAgente <= 0 || empty($tipoAusencia) || empty($fechaInicio) || empty($fechaFin)) {
    header("Location: $BASE_URL/vistas_pantallas/gestionar_agente.php?id=$idAgente");
    exit;
}

try {

    $stmt = $conexion->prepare("
        EXEC dbo.PR_REGISTRAR_AUSENCIA_AGENTE
            ?, ?, ?, ?, ?, ?
    ");

    $stmt->execute([
        $idAgente,
        $tipoAusencia,
        $fechaInicio,
        $fechaFin,
        $observacion,
        $idUsuarioSesion
    ]);

    header("Location: $BASE_URL/vistas_pantallas/gestionar_agente.php?id=$idAgente");
    exit;

} catch (Throwable $e) {

    // Puedes luego mejorar esto con sesión flash
    die("Error al registrar ausencia: " . $e->getMessage());
}
