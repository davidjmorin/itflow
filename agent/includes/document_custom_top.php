<?php

if (!isset($_GET['client_id']) && isset($_GET['document_id'])) {
    require_once dirname(__DIR__, 2) . '/config.php';
    $doc_lookup_id = intval($_GET['document_id']);
    $sql_doc_lookup = mysqli_query($mysqli, "SELECT document_client_id FROM documents WHERE document_id = $doc_lookup_id LIMIT 1");
    if ($sql_doc_lookup && mysqli_num_rows($sql_doc_lookup) > 0) {
        $_GET['client_id'] = mysqli_fetch_assoc($sql_doc_lookup)['document_client_id'];
    }
}
