<?php
/**
 * ARCHIVO: /vistas_pantallas/recuperar_password.php
 * =========================================================
 * VISTA: RECUPERACIÓN DE CONTRASEÑA (PASO 1)
 *
 * SE UTILIZA CUANDO:
 *  - El usuario hace clic en “¿Olvidaste tu contraseña?” desde login.php
 *  - El sistema obliga a restablecer contraseña (password en texto plano)
 *
 * FLUJO GENERAL:
 *  1) Usuario ingresa su correo corporativo
 *  2) Se envía POST a:
 *        /cruds/proceso_recuperar_password.php
 *  3) El backend genera token y muestra mensaje neutro
 *
 * NORMATIVA DE SEGURIDAD:
 *  - NO se revela si el correo existe o no
 *  - Mensaje siempre neutro
 *  - No se expone información interna
 */

session_start();

$BASE_URL = "/plataforma_de_monitoreo";

/**
 * Mensaje informativo (si el backend lo envió)
 * Ejemplo:
 *  “Si el correo está registrado, recibirá instrucciones…”
 */
$msg = $_SESSION['recover_msg'] ?? '';
unset($_SESSION['recover_msg']);

/**
 * Variables para el diseño (usadas por diseno_arriba.php)
 */
$PAGE_TITLE       = "🔑 Recuperar contraseña";
$PAGE_SUBTITLE    = "Ingrese su correo corporativo para restablecer el acceso.";
$PAGE_ACTION_HTML = "";

/**
 * Incluir encabezado visual
 * Ruta:
 *  - Este archivo está en /vistas_pantallas
 *  - diseno_arriba.php está en /includes_partes_fijas
 */
require_once '../includes_partes_fijas/diseno_arriba.php';
?>

<!-- =======================================================
     CONTENIDO PRINCIPAL
     ======================================================= -->
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-7 col-lg-5">

    <div class="card card-soft shadow-sm">
      <div class="card-header card-header-soft py-3">
        <div class="fw-bold">
          <i class="bi bi-key me-2"></i>Recuperación de contraseña
        </div>
        <div class="help-mini mt-1">
          Por seguridad, si el correo está registrado, recibirá instrucciones.
        </div>
      </div>

      <div class="card-body p-4">

        <!-- MENSAJE NEUTRO (SI EXISTE) -->
        <?php if (!empty($msg)): ?>
          <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <?= htmlspecialchars($msg) ?>
          </div>
        <?php endif; ?>

        <!-- ===================================================
             FORMULARIO DE RECUPERACIÓN
             ===================================================
             ENVÍA A:
               /cruds/proceso_recuperar_password.php
             MÉTODO:
               POST
             CAMPO:
               correo
        -->
        <form method="POST"
              action="<?= $BASE_URL ?>/cruds/proceso_recuperar_password.php"
              novalidate>

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
            <div class="help-mini mt-1">
              Se enviarán instrucciones para restablecer la contraseña.
            </div>
          </div>

          <button type="submit"
                  class="btn btn-primary w-100 fw-bold shadow-sm">
            Enviar instrucciones
          </button>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <a class="small text-decoration-none"
               href="<?= $BASE_URL ?>/vistas_pantallas/login.php">
              <i class="bi bi-arrow-left me-1"></i>Volver al login
            </a>

            <span class="help-mini">
              Si tiene problemas, contacte al administrador.
            </span>
          </div>

        </form>

        <!-- ===================================================
             AYUDA SOLO EN LOCALHOST (PARA PRUEBAS)
             =================================================== -->
        <?php
        $esLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
        if ($esLocalhost && !empty($_SESSION['recover_dev_link'])):
        ?>
          <hr>
          <div class="small">
            <strong>Modo local (pruebas):</strong>
            <div class="mt-1">
              <code><?= htmlspecialchars($_SESSION['recover_dev_link']) ?></code>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>

  </div>
</div>

<?php
/**
 * Incluir pie de página visual
 */
require_once '../includes_partes_fijas/diseno_abajo.php';
?>
