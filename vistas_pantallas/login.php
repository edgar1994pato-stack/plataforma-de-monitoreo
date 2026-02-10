<?php
/**
 * ARCHIVO: /vistas_pantallas/login.php
 * =========================================================
 * VISTA DE LOGIN (FRONTEND) - PRODUCCIÓN + LOCALHOST
 * FIX: enviar POST a /index.php para evitar 404 de nginx en POST /
 */

$esLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
$BASE_URL = $esLocalhost ? '/plataforma_de_monitoreo' : '';

if (!empty($_SESSION['id_usuario'])) {
    header("Location: {$BASE_URL}/vistas_pantallas/menu.php");
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$PAGE_TITLE       = "🔐 Iniciar Sesión";
$PAGE_SUBTITLE    = "Plataforma de Monitoreo · Alfanet";
$PAGE_ACTION_HTML = "";

require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-6 col-lg-4">

    <div class="card card-soft shadow-sm">
      <div class="card-header card-header-soft py-3">
        <div class="fw-bold">
          <i class="bi bi-shield-lock me-2"></i>ACCESO
        </div>
        <div class="help-mini mt-1">
          Ingrese su correo corporativo y contraseña.
        </div>
      </div>

      <div class="card-body p-4">

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger py-2 small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <!-- ✅ FIX REAL: POST A /index.php (archivo real) -->
        <form method="POST" action="<?= $BASE_URL ?>/index.php" novalidate>

          <!-- ✅ requerido por tu index.php -->
          <input type="hidden" name="accion" value="login">

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">
              CORREO CORPORATIVO
            </label>
            <input
              type="email"
              name="correo"
              class="form-control form-control-sm"
              placeholder="usuario@alfanet..."
              autocomplete="username"
              required>
          </div>

          <div class="mb-2">
            <label class="form-label small fw-bold text-muted">
              CONTRASEÑA
            </label>
            <div class="input-group input-group-sm">
              <input
                type="password"
                name="password"
                class="form-control"
                placeholder="••••••••"
                autocomplete="current-password"
                required>
              <button class="btn btn-outline-secondary" type="button" id="btnTogglePassword">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="help-mini">Puede mostrar/ocultar la contraseña.</div>
          </div>

          <div class="text-end mb-3">
            <a href="<?= $BASE_URL ?>/vistas_pantallas/recuperar_password.php"
               class="small text-decoration-none">
              ¿Olvidaste tu contraseña?
            </a>
          </div>

          <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
            Ingresar
          </button>

          <div class="text-center mt-3">
            <span class="help-mini">
              Si no puede acceder, comuníquese con el administrador.
            </span>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<script>
document.getElementById('btnTogglePassword')?.addEventListener('click', function () {
  const input = document.querySelector('input[name="password"]');
  const icon  = this.querySelector('i');
  if (!input) return;

  const mostrar = (input.type === 'password');
  input.type = mostrar ? 'text' : 'password';
  if (icon) icon.className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_abajo.php';
?>

