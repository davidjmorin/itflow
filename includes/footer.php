<?php
require_once "inc_confirm_modal.php";
?>

<?php
if (basename(dirname($_SERVER['REQUEST_URI'])) === 'admin') { ?>
    <p class="text-right font-weight-light"><?= escapeHtml($session_company_name) ?>  <?= APP_VERSION ?> &nbsp; · &nbsp; <a target="_blank" href="https://docs.itflow.org">Docs</a> &nbsp; · &nbsp; <a target="_blank" href="https://forum.itflow.org">Forum</a> &nbsp; · &nbsp; <a target="_blank" href="https://services.itflow.org">Services</a></p>
    <br>
<?php } ?>
<?php
if (basename(dirname($_SERVER['REQUEST_URI'])) === 'guest') { ?>
<p class="text-center">
    <?php
        echo escapeHtml($session_company_name);
        echo '<br><small class="text-muted">Powered by ' . escapeHtml($session_company_name) . '</small>';
    ?>
</p>
<?php } ?>

</div><!-- /.container-fluid -->
</div> <!-- /.content -->
</div> <!-- /.content-wrapper -->
</div> <!-- ./wrapper -->

<!-- Set the browser window title to the clients name -->
<script>document.title = <?= json_encode("$tab_title - $page_title") ?>;</script>

<!-- REQUIRED SCRIPTS -->

<!-- Bootstrap 4 -->
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom js-->
<script src="/libs/moment/moment.min.js"></script>
<script src="/libs/chart.js/chart.umd.min.js"></script>
<script src="/libs/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="/libs/daterangepicker/daterangepicker.js"></script>
<script src="/libs/select2/js/select2.min.js"></script>
<script src="/libs/inputmask/jquery.inputmask.min.js"></script>
<script src="/libs/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/libs/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<script src="/libs/clipboardjs/clipboard.min.js"></script>
<script src="/js/keepalive.js"></script>
<script src="/libs/DataTables/datatables.min.js"></script>
<script src="/libs/intl-tel-input/js/intlTelInput.min.js"></script>

<!-- AdminLTE App -->
<script src="/libs/adminlte/js/adminlte.min.js"></script>
<script src="/js/app.js"></script>
<script src="/js/ajax_modal.js"></script>
<script src="/js/confirm_modal.js"></script>
<script src="/js/date_filter.js"></script>

<?php if (!empty($config_google_places_api_key)) { ?>
<!-- Google Places Address Autocomplete (Places API New) -->
<script src="/js/address_autocomplete.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/address_autocomplete.js') ?>"></script>
<?php } ?>

</body>
</html>

<?php

// Calculate Execution time Uncomment for test

//$time_end = microtime(true);
//$execution_time = ($time_end - $time_start);
//echo '<h2>Total Execution Time: '.number_format((float) $execution_time, 10) .' seconds</h2>';
