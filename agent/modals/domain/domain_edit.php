<?php

/*
 * Edit a domain - its name, what it is for, the star, the expiry and
 * auto-renew, the registrar and hosts, tags and notes. Saving doesn't look
 * the domain up again unless its name changed; Look up now does that.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$domain_id = intval($_GET['id'] ?? 0);
$domain = domainRow($domain_id);
if (!$domain) {
    echo json_encode(['error' => 'That domain no longer exists.']);
    exit;
}
$client_id = intval($domain['domain_client_id']);
enforceClientAccess();

$domain_form_mode = 'edit';
$domain_name = escapeHtml($domain['domain_name']);
$domain_description = escapeHtml($domain['domain_description']);
$domain_expire = escapeHtml($domain['domain_expire']);
$domain_notes = escapeHtml($domain['domain_notes']);
$domain_favorite = intval($domain['domain_favorite']);
$domain_auto_renew = intval($domain['domain_auto_renew']);
$domain_tag_ids = array_map('intval', array_column(tagsForRecord('domain', $domain_id), 'tag_id'));
$domain_vendor_ids = [];
foreach (DOMAIN_ROLES as $role => $info) {
    $domain_vendor_ids[$role] = intval($domain[$info['column']]);
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-globe me-2"></i>Edit Domain <span class="fw-normal text-white-50 ms-1"><?= $domain_name ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="domain_id" value="<?= $domain_id ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">
        <?php require 'domain_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_domain" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    function checkDomainLookupEdit<?= $domain_id ?>() {
        var domainElem = document.getElementById("domain_name_edit<?= $domain_id ?>");
        if (!domainElem) return;
        var domain = domainElem.value.trim();
        if (domain.length < 3) return;

        var clientId = <?= $client_id ?>;
        var checkInfo = document.getElementById("domain_check_info_edit<?= $domain_id ?>");

        if (checkInfo) {
            checkInfo.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Looking up WHOIS & RDAP records...</span>';
        }

        jQuery.getJSON(
            "ajax.php",
            {domain_lookup: 'true', domain: domain, client_id: clientId},
            function(data) {
                if (!checkInfo) return;
                if (!data.success) {
                    checkInfo.innerHTML = data.message || '';
                    return;
                }

                var feedback = [];
                if (data.expire) {
                    var expireInput = document.getElementById("domain_expire_edit<?= $domain_id ?>");
                    if (expireInput) {
                        expireInput.value = data.expire;
                    }
                    feedback.push('Expires: <strong>' + data.expire + '</strong>');
                }

                if (data.registrar) {
                    var regSelect = document.getElementById("domain_registrar_edit<?= $domain_id ?>");
                    if (regSelect) {
                        if (data.matched_vendor_id > 0) {
                            jQuery(regSelect).val(data.matched_vendor_id).trigger('change');
                            feedback.push('Registrar: <strong>' + data.registrar + '</strong> (Matched: ' + data.matched_vendor_name + ')');
                        } else {
                            if (jQuery(regSelect).find("option[value='" + data.registrar + "']").length === 0) {
                                var newOption = new Option(data.registrar, data.registrar, true, true);
                                jQuery(regSelect).append(newOption).trigger('change');
                            } else {
                                jQuery(regSelect).val(data.registrar).trigger('change');
                            }
                            feedback.push('Registrar: <strong>' + data.registrar + '</strong>');
                        }
                    }
                }

                if (feedback.length > 0) {
                    checkInfo.innerHTML = '<span class="text-success"><i class="fas fa-check-circle mr-1"></i>Auto-detected: ' + feedback.join(' &bull; ') + '</span>';
                } else {
                    checkInfo.innerHTML = '<span class="text-success"><i class="fas fa-check-circle mr-1"></i>Domain is valid</span>';
                }
            }
        ).fail(function() {
            if (checkInfo) checkInfo.innerHTML = '';
        });
    }
</script>

<?php
require_once '../../../includes/modal_footer.php';
