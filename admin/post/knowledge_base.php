<?php
define('FROM_POST_HANDLER', true);
require_once "../../config.php";
require_once "../../functions.php";
require_once "../../includes/check_login.php";

if (isset($_POST['add_kb'])) {
    validateCSRFToken();
    $title = escapeSql($_POST['kb_title']);
    $content = escapeSql($_POST['kb_content']);
    $status = intval($_POST['kb_status']);
    mysqli_query($mysqli, "INSERT INTO knowledge_base SET kb_title = '$title', kb_content = '$content', kb_status = $status");
    logAudit("Knowledge Base", "Create", "$session_name created KB article $title");
    flashAlert("Article created.");
    redirect('/admin/knowledge_base.php');
}

if (isset($_POST['edit_kb'])) {
    validateCSRFToken();
    $id = intval($_POST['kb_id']);
    $title = escapeSql($_POST['kb_title']);
    $content = escapeSql($_POST['kb_content']);
    $status = intval($_POST['kb_status']);
    mysqli_query($mysqli, "UPDATE knowledge_base SET kb_title = '$title', kb_content = '$content', kb_status = $status WHERE kb_id = $id");
    logAudit("Knowledge Base", "Update", "$session_name updated KB article $title");
    flashAlert("Article updated.");
    redirect('/admin/knowledge_base.php');
}

if (isset($_POST['delete_kb'])) {
    validateCSRFToken();
    $id = intval($_POST['kb_id']);
    mysqli_query($mysqli, "DELETE FROM knowledge_base WHERE kb_id = $id");
    logAudit("Knowledge Base", "Delete", "$session_name deleted KB article");
    flashAlert("Article deleted.");
    redirect('/admin/knowledge_base.php');
}
