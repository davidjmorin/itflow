<script src="/js/app.js"></script>
<script src="/libs/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<?php if (!empty($config_google_places_api_key)) { ?>
<script src="/js/address_autocomplete.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/address_autocomplete.js') ?>"></script>
<?php } ?>

<?php
    $content = ob_get_clean();

    // Return the title and content as a JSON response
    echo json_encode(['content' => $content]);
    exit();
?>