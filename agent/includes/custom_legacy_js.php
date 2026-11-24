<?php
if (empty($GLOBALS['itflow_custom_legacy_js_loaded'])) {
    $GLOBALS['itflow_custom_legacy_js_loaded'] = true;
    $custom_root = dirname(__DIR__, 2);
?>
<style>
.modal-header .close { background: transparent; border: 0; font-size: 1.4rem; line-height: 1; opacity: 0.85; margin-left: auto; padding: 0 0.25rem; cursor: pointer; }
.modal-header .close:hover { opacity: 1; }
.btn-block { display: block; width: 100%; }
.badge-primary { background-color: #0d6efd; color: #fff; }
.badge-secondary { background-color: #6c757d; color: #fff; }
.badge-success { background-color: #198754; color: #fff; }
.badge-danger { background-color: #dc3545; color: #fff; }
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-info { background-color: #0dcaf0; color: #212529; }
.badge-light { background-color: #f8f9fa; color: #212529; }
.badge-dark { background-color: #212529; color: #fff; }
.text-right { text-align: right !important; }
.text-left { text-align: left !important; }
.mr-1 { margin-right: 0.25rem !important; }
.mr-2 { margin-right: 0.5rem !important; }
.ml-1 { margin-left: 0.25rem !important; }
.ml-2 { margin-left: 0.5rem !important; }
.ml-auto { margin-left: auto !important; }
.font-weight-bold { font-weight: 700 !important; }
.form-group { margin-bottom: 1rem; }
.custom-control { position: relative; padding-left: 1.5rem; }
.custom-control-input { position: absolute; left: 0; top: 0.25rem; }
.custom-control-label { margin-bottom: 0; }
</style>
<script data-cfasync="false" src="/libs/custom/jquery.min.js?v=<?= filemtime($custom_root . '/libs/custom/jquery.min.js') ?>"></script>
<script data-cfasync="false" src="/js/custom_bs5_compat.js?v=<?= filemtime($custom_root . '/js/custom_bs5_compat.js') ?>"></script>
<?php
}
