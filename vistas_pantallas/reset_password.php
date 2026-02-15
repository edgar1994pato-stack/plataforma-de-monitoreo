<?php
/**
 * ARCHIVO: /vistas_pantallas/reset_password.php
 * =========================================================
 * VISTA: RESTABLECER CONTRASEÑA (PASO FINAL DEL FLUJO)
 *
 * SE UTILIZA CUANDO:
 *  - El usuario abre un enlace con ?token=...
 *  - El token fue generado por proceso_recuperar_password.php
 *
 * QUÉ HACE:
 *  1) Lee el token desde la URL
 *  2) Valida en BD que:
 *     - el token exista
 *     - no esté expirado
 *     - el usuario esté activo
 *  3) Muestra formulario para ingresar nueva contraseña
 *  4) Envía POST a /cruds/proceso_reset_password.php
 *
 * SEGURIDAD:
 *  - No revela datos del usuario
 *  - Token no se muestra (solo hidden)
 *  - Si el token es inválido/expirado → mensaje neutro
 */

session_start();

require_once '../config_ajustes/conectar_db.php';

$BASE_URL = "/plataforma_de_monitoreo";

/* =========================================================
 * 1) LEER TOKEN DE LA URL
 * ========================================================= */
$token = trim($_GET['token'] ?? '');

// Mensaje opcional (desde proceso_reset_password.php)
$msg = $_SESSION['reset_msg'] ?? '';
unset($_SESSION['reset_msg']);

// Variables para el header visual
$PAGE_TITLE       = "🔐 Restablecer contraseña";
$PAGE_SUBTITLE    = "Cree una nueva contraseña para recuperar el acceso.";
$PAGE_ACTION_HTML = "";

/* =========================================================
 * 2) VALIDACIÓN BÁSICA DEL TOKEN
 * ========================================================= */
if ($token === '' || strlen($token) < 20) {
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
    ?>
    <div class="alert alert-danger small">
      <i class="bi bi-exclamation-triangle me-1"></i>
      Enlace inválido. Solicite nuevamente la recuperación.
    </div>
    <a class="btn btn-outline-primary btn-sm"
       href="<?= $BASE_URL ?>/vistas_pantallas/recuperar_password.php">
      Volver
    </a>
    <?php
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

    exit;
}

/* =========================================================
 * 3) VALIDAR TOKEN EN BD
 * ========================================================= */
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
        require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
        ?>
        <div class="alert alert-danger small">
          <i class="bi bi-exclamation-triangle me-1"></i>
          El enlace es inválido o ha expirado. Solicite uno nuevo.
        </div>
        <a class="btn btn-outline-primary btn-sm"
           href="<?= $BASE_URL ?>/vistas_pantallas/recuperar_password.php">
          Solicitar nuevo enlace
        </a>
        <?php
        require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

        exit;
    }

} catch (Throwable $e) {
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
    ?>
    <div class="alert alert-danger small">
      <i class="bi bi-exclamation-triangle me-1"></i>
      Error al validar el enlace. Intente nuevamente.
    </div>
    <?php
    require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

    exit;
}

/* =========================================================
 * 4) MOSTRAR FORMULARIO (TOKEN VÁLIDO)
 * ========================================================= */
require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-7 col-lg-5">

    <div class="card card-soft shadow-sm">
      <div class="card-header card-header-soft py-3">
        <div class="fw-bold">
          <i class="bi bi-shield-lock me-2"></i>Nueva contraseña
        </div>
        <div class="help-mini mt-1">
          Ingrese una nueva contraseña segura.
        </div>
      </div>

      <div class="card-body p-4">

        <?php if (!empty($msg)): ?>
          <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <?= htmlspecialchars($msg) ?>
          </div>
        <?php endif; ?>

        <form method="POST"
              action="<?= $BASE_URL ?>/cruds/proceso_reset_password.php"
              novalidate>

          <!-- Token oculto -->
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">
              NUEVA CONTRASEÑA
            </label>
            <input
              type="password"
              name="new_password"
              class="form-control form-control-sm"
              placeholder="••••••••"
              autocomplete="new-password"
              required>
            <div class="help-mini mt-1">
              Mínimo 10 caracteres, con mayúscula, minúscula y número.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">
              CONFIRMAR CONTRASEÑA
            </label>
            <input
              type="password"
              name="new_password_confirm"
              class="form-control form-control-sm"
              placeholder="••••••••"
              autocomplete="new-password"
              required>
          </div>

          <button type="submit"
                  class="btn btn-primary w-100 fw-bold shadow-sm">
            Guardar nueva contraseña
          </button>

          <div class="text-center mt-3">
            <a class="small text-decoration-none"
               href="<?= $BASE_URL ?>/vistas_pantallas/login.php">
              Volver al login
            </a>
          </div>

        </form>

      </div>
    </div>

  </div>
</div>

<?php
require_once BASE_PATH . '/includes_partes_fijas/diseno_arriba.php';

?>
