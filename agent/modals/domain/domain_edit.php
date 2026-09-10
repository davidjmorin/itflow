<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$domain_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT domain_archived_at, domain_client_id, domain_created_at, domain_description,
    domain_dnshost, domain_expire, domain_ip, domain_mail_servers, domain_mailhost,
    domain_name, domain_name_servers, domain_notes, domain_raw_whois, domain_registrar,
    domain_txt, domain_webhost FROM domains WHERE domain_id = $domain_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$domain_name = escapeHtml($row['domain_name']);
$domain_description = escapeHtml($row['domain_description']);
$domain_expire = escapeHtml($row['domain_expire']);
$domain_registrar = intval($row['domain_registrar']);
$domain_webhost = intval($row['domain_webhost']);
$domain_dnshost = intval($row['domain_dnshost']);
$domain_mailhost = intval($row['domain_mailhost']);
$domain_ip = escapeHtml($row['domain_ip']);
$domain_name_servers = escapeHtml($row['domain_name_servers']);
$domain_mail_servers = escapeHtml($row['domain_mail_servers']);
$domain_txt = escapeHtml($row['domain_txt']);
$domain_raw_whois = escapeHtml($row['domain_raw_whois']);
$domain_notes = escapeHtml($row['domain_notes']);
$domain_created_at = escapeHtml($row['domain_created_at']);
$domain_archived_at = escapeHtml($row['domain_archived_at']);
$client_id = intval($row['domain_client_id']);

$history_sql = mysqli_query($mysqli, "SELECT domain_history_column, domain_history_modified_at, domain_history_new_value,
    domain_history_old_value FROM domain_history WHERE domain_history_domain_id = $domain_id");

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-globe mr-2"></i>Editing domain: <span class="text-bold"><?= $domain_name ?></span></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="domain_id" value="<?= $domain_id ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-overview<?= $domain_id ?>">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-records<?= $domain_id ?>">Records</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditNotes<?= $domain_id ?>">Notes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditHistory<?= $domain_id ?>">History</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-overview<?= $domain_id ?>">

                <div class="form-group">
                    <label>Domain Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" id="domain_name_edit<?= $domain_id ?>" placeholder="Domain name example.com" maxlength="200" value="<?= $domain_name ?>" required>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="checkDomainLookupEdit<?= $domain_id ?>()"><i class="fas fa-sync-alt mr-1"></i>Lookup WHOIS</button>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-info" id="domain_check_info_edit<?= $domain_id ?>"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Short Description" value="<?= $domain_description ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Domain Registrar</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" data-placeholder="- Select Vendor -" name="registrar" id="domain_registrar_edit<?= $domain_id ?>">
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE (vendor_client_id = $client_id OR vendor_client_id = 0) AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_assoc($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_registrar == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Webhost</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" data-placeholder="- Select Vendor -" name="webhost" id="domain_webhost_edit<?= $domain_id ?>">
                            <option value="">- Select Vendor -</option>
                            <?php
                            if (isset($vendor_sql)) {
                                mysqli_data_seek($vendor_sql, 0);
                                while ($row = mysqli_fetch_assoc($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_webhost == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>DNS Host</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" data-placeholder="- Select Vendor -" name="dnshost" id="domain_dnshost_edit<?= $domain_id ?>">
                            <option value="">- Select Vendor -</option>
                            <?php
                            if (isset($vendor_sql)) {
                                mysqli_data_seek($vendor_sql, 0);
                                while ($row = mysqli_fetch_assoc($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_dnshost == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mail Host</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" data-placeholder="- Select Vendor -" name="mailhost" id="domain_mailhost_edit<?= $domain_id ?>">
                            <option value="">- Select Vendor -</option>
                            <?php
                            if (isset($vendor_sql)) {
                                mysqli_data_seek($vendor_sql, 0);
                                while ($row = mysqli_fetch_assoc($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_mailhost == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Expire Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                        </div>
                        <input type="date" class="form-control" name="expire" id="domain_expire_edit<?= $domain_id ?>" max="2999-12-31" value="<?= $domain_expire ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-records<?= $domain_id ?>">

                <div class="form-group">
                    <label>Domain IP(s)</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                        </div>
                        <textarea class="form-control" rows="1" name="domain_ip" disabled><?= $domain_ip ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>Name Servers</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-crown"></i></span>
                        </div>
                        <textarea class="form-control" rows="1" name="name_servers" disabled><?= $domain_name_servers ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>MX Records</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-mail-bulk"></i></span>
                        </div>
                        <textarea class="form-control" rows="1" name="mail_servers" disabled><?= $domain_mail_servers ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>TXT Records</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-check-double"></i></span>
                        </div>
                        <textarea class="form-control" rows="1" name="txt_records" disabled><?= $domain_txt ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>Raw WHOIS</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-search-plus"></i></span>
                        </div>
                        <textarea class="form-control" rows="6" name="raw_whois" disabled><?= $domain_raw_whois ?></textarea>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditNotes<?= $domain_id ?>">
                <div class="form-group">
                    <textarea class="form-control" name="notes" rows="12" placeholder="Enter some notes"><?= $domain_notes ?></textarea>
                </div>
            </div>

            <div class="tab-pane fade" id="pillsEditHistory<?= $domain_id ?>">
                <div class="table-responsive">
                    <table class='table table-sm table-striped border table-hover'>
                        <thead class='thead-dark'>
                            <tr>
                                <th>Date</th>
                                <th>Field</th>
                                <th>Before</th>
                                <th>After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                while ($row = mysqli_fetch_assoc($history_sql)) {
                                $domain_modified_at = escapeHtml($row['domain_history_modified_at']);
                                $domain_field = escapeHtml($row['domain_history_column']);
                                $domain_before_value = escapeHtml($row['domain_history_old_value']);
                                $domain_after_value = escapeHtml($row['domain_history_new_value']);
                            ?>
                            <tr>
                                <td><?= $domain_modified_at ?></td>
                                <td><?= $domain_field ?></td>
                                <td><?= $domain_before_value ?></td>
                                <td><?= $domain_after_value ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_domain" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
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
