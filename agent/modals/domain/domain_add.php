<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-globe mr-2"></i>New Domain</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
            </li>

        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <?php if ($client_id) { ?>
                    <input type="hidden" name="client_id" id="domain_client_id" value="<?= $client_id ?>">
                <?php } else { ?>

                    <div class="form-group">
                        <label>Client <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="client_id" id="domain_client_id" required>
                                <option value="">- Select Client -</option>
                                <?php

                                $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_assoc($sql)) {
                                    $client_id_select = intval($row['client_id']);
                                    $client_name = escapeHtml($row['client_name']); ?>
                                    <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?= $client_id_select ?>"><?= $client_name ?></option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                <?php } ?>

                <div class="form-group">
                    <label>Domain Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" id="domain_name" placeholder="example.com" maxlength="200" required autofocus onfocusout="checkApexDomain()">
                    </div>
                    <div class="mt-2">
                        <span class="text-info" id="domain_check_info"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Short Description">
                    </div>
                </div>

                <div class="form-group">
                    <label>Domain Registrar</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" data-placeholder="- Select Vendor -" name="registrar" id="domain_registrar">
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_filter = $client_id ? "(vendor_client_id = $client_id OR vendor_client_id = 0)" : "vendor_client_id = 0";
                            $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_archived_at IS NULL AND $vendor_filter ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = escapeHtml($row['vendor_name']);
                                ?>
                                <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Webhost</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" data-placeholder="- Select Vendor -" name="webhost" id="domain_webhost">
                            <option value="">- Select Vendor -</option>
                            <?php
                            if (isset($sql)) {
                                mysqli_data_seek($sql, 0);
                                while ($row = mysqli_fetch_assoc($sql)) {
                                    $vendor_id = intval($row['vendor_id']);
                                    $vendor_name = escapeHtml($row['vendor_name']);
                                    ?>
                                    <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                                <?php }
                            } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>DNS Host</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" data-placeholder="- Select Vendor -" name="dnshost" id="domain_dnshost">
                            <option value="">- Select Vendor -</option>
                            <?php
                            if (isset($sql)) {
                                mysqli_data_seek($sql, 0);
                                while ($row = mysqli_fetch_assoc($sql)) {
                                    $vendor_id = intval($row['vendor_id']);
                                    $vendor_name = escapeHtml($row['vendor_name']);
                                    ?>
                                    <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                                <?php }
                            } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mail Host</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" data-placeholder="- Select Vendor -" name="mailhost" id="domain_mailhost">
                            <option value="">- Select Vendor -</option>
                            <?php
                            if (isset($sql)) {
                                mysqli_data_seek($sql, 0);
                                while ($row = mysqli_fetch_assoc($sql)) {
                                    $vendor_id = intval($row['vendor_id']);
                                    $vendor_name = escapeHtml($row['vendor_name']);
                                    ?>
                                    <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                                <?php }
                            } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Expire Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                        </div>
                        <input type="date" class="form-control" name="expire" id="domain_expire" max="2999-12-31">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes">
                <div class="form-group">
                    <textarea class="form-control" rows="12" placeholder="Enter some notes" name="notes"></textarea>
                </div>
            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_domain" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<script>
    jQuery(document).ready(function() {
        jQuery('#domain_client_id').on('change', function() {
            var selectedClientId = jQuery(this).val();
            if (!selectedClientId) return;
            jQuery.getJSON('ajax.php', {get_client_vendors: 'true', client_id: selectedClientId}, function(data) {
                var vendors = (data && data.vendors) ? data.vendors : [];
                ['domain_registrar', 'domain_webhost', 'domain_dnshost', 'domain_mailhost'].forEach(function(id) {
                    var $s = jQuery('#' + id);
                    if (!$s.length) return;
                    var cur = $s.val();
                    $s.find('option:not([value=""])').remove();
                    vendors.forEach(function(v) {
                        $s.append(new Option(v.vendor_name, v.vendor_id));
                    });
                    if (cur) {
                        if ($s.find("option[value='" + cur + "']").length === 0) {
                            $s.append(new Option(cur, cur, true, true));
                        } else {
                            $s.val(cur);
                        }
                    }
                    $s.trigger('change.select2');
                });
            });
        });
    });

    function checkApexDomain() {
        var domainElem = document.getElementById("domain_name");
        if (!domainElem) return;
        var domain = domainElem.value.trim();
        if (domain.length < 3) return;

        var clientElem = document.getElementById("domain_client_id");
        var clientId = clientElem ? clientElem.value : 0;
        var checkInfo = document.getElementById("domain_check_info");

        if (checkInfo) {
            checkInfo.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Checking WHOIS & RDAP records...</span>';
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
                    var expireInput = document.getElementById("domain_expire");
                    if (expireInput) {
                        expireInput.value = data.expire;
                    }
                    feedback.push('Expires: <strong>' + data.expire + '</strong>');
                }

                if (data.registrar) {
                    var regSelect = document.getElementById("domain_registrar");
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
