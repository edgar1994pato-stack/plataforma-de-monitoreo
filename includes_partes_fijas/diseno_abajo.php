<?php
// /includes/diseno_abajo.php
if (!isset($PAGE_SCRIPTS)) $PAGE_SCRIPTS = "";
?>
    </div> <!-- /container-fluid -->

    <footer class="app-footer">
      <div class="container-fluid px-4">
        <div class="py-3 small text-muted d-flex justify-content-between align-items-center">
          <span><?= date('Y') ?></span>
          <span class="opacity-75"></span>
        </div>
      </div>
    </footer>

  </div> <!-- /app-main -->
</div> <!-- /dashboard-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?= $PAGE_SCRIPTS ?>

</body>
</html>