<?php
/**
 * /vistas_pantallas/cargar_planificacion.php
 * =========================================================
 * MÓDULO: Carga de Planificación QA
 * OBJETIVO:
 * - Pantalla inicial para cargar planificación mensual desde Excel
 * - No procesa archivo todavía
 * 
 */

require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();
require_permission('ver_modulo_planificacion');

$PAGE_TITLE = "📅 Planificación QA";
$PAGE_SUBTITLE = "Carga mensual de planificación de monitoreos";

$PAGE_ACTION_HTML = '
<a class="btn btn-outline-primary btn-sm shadow-sm"
   href="'.BASE_URL.'/vistas_pantallas/menu.php">
    <i class="bi bi-house-door"></i> Volver al menú
</a>
';

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="card card-soft mb-4">
    <div class="card-header card-header-dark py-2 small">
        <i class="bi bi-upload me-2"></i>Cargar archivo de planificación
    </div>

    <div class="card-body">

        <div class="alert alert-info small">
            El archivo debe estar en formato <b>.xlsx</b> y contener las columnas:
            <b>Monitor, Asesor, Area, Sucursal, Tipo, Cantidad, Periodo</b>.
        </div>

        <form method="POST"
              action="<?= BASE_URL ?>/sql_base_de_datos/proceso_cargar_planificacion.php"
              enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label fw-bold">Archivo Excel</label>
                <input type="file"
                       name="archivo_excel"
                       class="form-control"
                       accept=".xlsx"
                       required>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-cloud-arrow-up"></i> Cargar planificación
            </button>

        </form>

    </div>
</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>