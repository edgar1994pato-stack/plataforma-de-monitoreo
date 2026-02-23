<?php
require_once __DIR__ . '/../config_ajustes/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/includes_partes_fijas/seguridad.php';
require_login();

$PAGE_TITLE = "Cambiar contraseña";
$PAGE_SUBTITLE = "";
$PAGE_ACTION_HTML = "";

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">

    <div class="card shadow-sm">
      <div class="card-body">

        <h5 class="fw-bold mb-3">Cambiar contraseña</h5>

        <form method="POST" action="<?= BASE_URL ?>/cruds/proceso_cambiar_password.php">

          <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="password_nueva" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" name="password_confirmar" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            Actualizar contraseña
          </button>

        </form>

      </div>
    </div>

  </div>
</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';