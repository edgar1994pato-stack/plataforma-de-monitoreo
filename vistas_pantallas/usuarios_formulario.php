<?php
require_once __DIR__ . '/../config_ajustes/app.php';
require_once BASE_PATH . '/config_ajustes/conectar_db.php';
require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';

require_login();
force_password_change();
require_permission('ver_modulo_roles');

function h($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
$BASE_URL = BASE_URL;

/* =============================
   CONFIG
============================= */
$roles = [];
$areas = [];

/* =============================
   CARGAR CATÁLOGOS
============================= */
try {

  $st = $conexion->query("
    SELECT id_rol, nombre_rol
    FROM dbo.ROLES
    WHERE fecha_fin IS NULL
    ORDER BY nombre_rol
  ");
  $roles = $st->fetchAll(PDO::FETCH_ASSOC);

  $st = $conexion->query("
    SELECT id_area, nombre_area
    FROM dbo.AREAS
    WHERE estado = 1
    ORDER BY nombre_area
  ");
  $areas = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  $roles = [];
  $areas = [];
}

$PAGE_TITLE = "➕ Nuevo Usuario";
$PAGE_SUBTITLE = "Registro de usuarios del sistema";

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="mb-3">
  <a href="<?= h($BASE_URL) ?>/vistas_pantallas/roles.php" class="btn btn-soft btn-sm shadow-sm">
    ← Volver
  </a>
</div>

<div class="card card-soft mb-5">
  <div class="card-header card-header-dark py-2 small">
    Registrar nuevo usuario
  </div>

  <div class="card-body">
    <form id="formUsuario"
          method="POST"
          action="<?= h($BASE_URL) ?>/cruds/proceso_guardar_usuario.php"
          class="row g-3">

      <!-- Nombre completo -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">
          NOMBRE COMPLETO *
        </label>
        <input type="text"
               name="nombre_completo"
               class="form-control form-control-sm"
               maxlength="300"
               required>
      </div>

      <!-- Correo corporativo -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">
          CORREO CORPORATIVO *
        </label>
        <input type="email"
               name="correo_corporativo"
               class="form-control form-control-sm"
               maxlength="300"
               placeholder="usuario@alfanet.net.ec"
               required>
      </div>

      <!-- Rol -->
      <div class="col-md-6">
        <label class="form-label small fw-bold text-muted">
          ROL *
        </label>
        <select name="id_rol"
                id="id_rol"
                class="form-select form-select-sm"
                required>
          <option value="0">Seleccione...</option>
          <?php foreach($roles as $r): ?>
            <option value="<?= (int)$r['id_rol'] ?>">
              <?= h($r['nombre_rol']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Área -->
      <div class="col-md-6" id="contenedorArea" style="display:none;">
        <label class="form-label small fw-bold text-muted">
          ÁREA *
        </label>
        <select name="id_area"
                id="id_area"
                class="form-select form-select-sm">
          <option value="0">Seleccione...</option>
          <?php foreach($areas as $a): ?>
            <option value="<?= (int)$a['id_area'] ?>">
              <?= h($a['nombre_area']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit"
                class="btn btn-primary btn-sm shadow-sm">
          Guardar
        </button>

        <a href="<?= h($BASE_URL) ?>/vistas_pantallas/roles.php"
           class="btn btn-soft btn-sm shadow-sm">
          Cancelar
        </a>
      </div>

    </form>
  </div>
</div>

<?php ob_start(); ?>
<script>
(function() {
  const form = document.getElementById('formUsuario');
  const rol = document.getElementById('id_rol');
  const area = document.getElementById('id_area');
  const contenedorArea = document.getElementById('contenedorArea');
  const correo = document.querySelector('input[name="correo_corporativo"]');
  const nombre = document.querySelector('input[name="nombre_completo"]');

  function esSupervisorSeleccionado() {
    if (!rol || rol.value === "0") return false;

    const texto = rol.options[rol.selectedIndex].text.trim().toLowerCase();
    return texto.includes('supervisor');
  }

  function toggleArea() {
    if (esSupervisorSeleccionado()) {
      contenedorArea.style.display = 'block';
      area.setAttribute('required', 'required');
    } else {
      contenedorArea.style.display = 'none';
      area.removeAttribute('required');
      area.value = "0";
    }
  }

  if (rol) {
    rol.addEventListener('change', toggleArea);
    toggleArea();
  }

  if (correo) {
    correo.addEventListener('blur', function() {
      this.value = this.value.trim().toLowerCase();
    });
  }

  form.addEventListener('submit', function(e) {
    const nombreVal = nombre.value.trim();
    const correoVal = correo.value.trim().toLowerCase();
    const rolVal = rol.value;

    if (nombreVal.length < 3) {
      alert('❌ El nombre completo debe tener mínimo 3 caracteres.');
      e.preventDefault();
      nombre.focus();
      return;
    }

    if (rolVal === "0") {
      alert('❌ Debe seleccionar un rol.');
      e.preventDefault();
      rol.focus();
      return;
    }

    const regexCorreo = /^[a-zA-Z0-9._%+\-]+@alfanet\.net\.ec$/;
    if (!regexCorreo.test(correoVal)) {
      alert('❌ El correo debe ser corporativo @alfanet.net.ec');
      e.preventDefault();
      correo.focus();
      return;
    }

    if (esSupervisorSeleccionado() && area.value === "0") {
      alert('❌ Debe seleccionar un área para el rol Supervisor.');
      e.preventDefault();
      area.focus();
      return;
    }

    correo.value = correoVal;
    nombre.value = nombreVal;
  });
})();
</script>
<?php
$PAGE_SCRIPTS = ob_get_clean();
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>