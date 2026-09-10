<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = preg_replace("(^https?://)", "", escapeSql($_POST['name']));
$description = escapeSql($_POST['description']);
$post_client_id = intval($_POST['client_id'] ?? 0);
if ($post_client_id === 0 && isset($_POST['domain_id'])) {
    $post_client_id = intval(getFieldById('domains', intval($_POST['domain_id']), 'domain_client_id'));
}
$registrar = resolveDomainVendor($_POST['registrar'] ?? 0, $post_client_id, 'Domain Registrar');
$dnshost = resolveDomainVendor($_POST['dnshost'] ?? 0, $post_client_id, 'DNS Host');
$webhost = resolveDomainVendor($_POST['webhost'] ?? 0, $post_client_id, 'Web Host');
$mailhost = resolveDomainVendor($_POST['mailhost'] ?? 0, $post_client_id, 'Mail Host');
$expire = escapeSql($_POST['expire']);
$notes = escapeSql($_POST['notes']);
