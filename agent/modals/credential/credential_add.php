<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$contact_id = intval($_GET['contact_id'] ?? 0);
$asset_id = intval($_GET['asset_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-key mr-2"></i>New Credential</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body" style="max-height: calc(85vh - 120px); overflow-y: auto;">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-credential-details">Details</a>
            </li>
            <?php if ($client_id) { ?>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-credential-relation">Relation</a>
            </li>
            <?php } ?>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-credential-notes">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-credential-details">

                <?php if ($client_id) { ?>
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                    <div class="form-group">
                        <label>Type <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                            </div>
                            <select class="form-control select2" name="type" id="credential_type">
                                <option value="Standard" selected>Standard Login</option>
                                <option value="Wi-Fi">Wi-Fi Network</option>
                            </select>
                        </div>
                    </div>
                <?php } else { ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Client <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="client_id" required>
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
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Type <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                                    </div>
                                    <select class="form-control select2" name="type" id="credential_type">
                                        <option value="Standard" selected>Standard Login</option>
                                        <option value="Wi-Fi">Wi-Fi Network</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php } ?>

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Important?</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key" id="credential_name_icon"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" id="credential_name_input" placeholder="Name of Login" maxlength="200" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <label class="star-toggle mb-0" title="Favorite">
                                    <input type="checkbox" name="favorite" value="1"><i class="far fa-star"></i>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Description" maxlength="500">
                    </div>
                </div>

                <!-- Standard Login Fields -->
                <div id="credential_standard_fields">
                    <div class="form-group">
                        <label>Username / ID</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <input type="text" class="form-control" name="username" placeholder="Username or ID" maxlength="350">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Password / Key <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            </div>
                            <input type="password" class="form-control" data-toggle="password" id="password" name="password" placeholder="Password or Key" required maxlength="350" autocomplete="new-password">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            </div>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-default" onclick="generatePassword('password')"><i class="fa fa-fw fa-magic" title="Generate Password"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>TOTP Seed</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                            </div>
                            <input type="password" class="form-control" data-toggle="password" name="otp_secret" placeholder="Insert secret key" maxlength="200">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>URI</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                            </div>
                            <input type="text" class="form-control" name="uri" placeholder="http://192.168.1.1" maxlength="500">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>URI 2</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                            </div>
                            <input type="text" class="form-control" name="uri_2" placeholder="https://server.company.com:5001" maxlength="500">
                        </div>
                    </div>
                </div>

                <!-- Wi-Fi Network & Router Admin Fields -->
                <div id="credential_wifi_fields" style="display: none;">
                    <div class="card card-outline card-info p-3 mb-3">
                        <h6 class="text-info font-weight-bold mb-3"><i class="fa fa-fw fa-wifi mr-2"></i>Wi-Fi Network Information</h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>SSID / Network Name <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-wifi"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="wifi_ssid" id="wifi_ssid" placeholder="Wi-Fi Network SSID" maxlength="200">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Security / Encryption</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-shield-alt"></i></span>
                                        </div>
                                        <select class="form-control select2" name="wifi_encryption" id="wifi_encryption">
                                            <option value="WPA2-PSK" selected>WPA2-Personal (AES/PSK)</option>
                                            <option value="WPA3-Personal">WPA3-Personal (SAE)</option>
                                            <option value="WPA2/WPA3-Personal">WPA2/WPA3-Personal (Mixed)</option>
                                            <option value="WPA2-Enterprise">WPA2-Enterprise (802.1X)</option>
                                            <option value="WPA3-Enterprise">WPA3-Enterprise (802.1X)</option>
                                            <option value="Open">Open (No Password)</option>
                                            <option value="WEP">WEP (Legacy)</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label>Wi-Fi Passcode / Key</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                </div>
                                <input type="password" class="form-control" data-toggle="password" id="wifi_passcode" name="wifi_passcode" placeholder="Wi-Fi Passcode" maxlength="350" autocomplete="new-password">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                </div>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-default" onclick="generatePassword('wifi_passcode')"><i class="fa fa-fw fa-magic" title="Generate Password"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-outline card-secondary p-3 mb-2">
                        <h6 class="text-secondary font-weight-bold mb-3"><i class="fa fa-fw fa-sliders-h mr-2"></i>Router / Network Admin Login</h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Admin Login URL / IP</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="wifi_admin_uri" id="wifi_admin_uri" placeholder="http://192.168.1.1" maxlength="500">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Admin Username</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="wifi_admin_username" id="wifi_admin_username" placeholder="e.g. admin" maxlength="350">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-7">
                                <div class="form-group mb-0">
                                    <label>Admin Password / Key</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                        </div>
                                        <input type="password" class="form-control" data-toggle="password" id="wifi_admin_password" name="wifi_admin_password" placeholder="Admin Password or Key" maxlength="350" autocomplete="new-password">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                        </div>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-default" onclick="generatePassword('wifi_admin_password')"><i class="fa fa-fw fa-magic" title="Generate Password"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group mb-0">
                                    <label>TOTP Seed</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                                        </div>
                                        <input type="password" class="form-control" data-toggle="password" name="wifi_admin_otp_secret" id="wifi_admin_otp_secret" placeholder="Insert secret key" maxlength="200">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <?php if ($client_id) { ?>
            <div class="tab-pane fade" id="pills-credential-relation">
                <div class="form-group">
                    <label>Contact</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="contact">
                            <option value="">- Select Contact -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name = escapeHtml($row['contact_name']);
                                ?>
                                <option
                                    <?php if ($contact_id == $contact_id_select) { echo "selected"; } ?>
                                    value="<?= $contact_id_select ?>"><?= $contact_name ?>
                                </option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Asset</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <select class="form-control select2" name="asset">
                            <option value="">- Select Asset -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, location_name FROM assets LEFT JOIN locations on asset_location_id = location_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $asset_id_select = intval($row['asset_id']);
                                $asset_name = escapeHtml($row['asset_name']);
                                $asset_location = escapeHtml($row['location_name']);

                                $asset_display_string = $asset_name;
                                if (!empty($asset_location)) {
                                    $asset_display_string = "$asset_name ($asset_location)";
                                }

                                ?>
                                <option <?php if ($asset_id == $asset_id_select) { echo "selected"; } ?>
                                    value="<?= $asset_id_select ?>"><?= $asset_display_string ?></option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php } ?>

            <div class="tab-pane fade" id="pills-credential-notes">

                <div class="form-group">
                    <textarea class="form-control" rows="12" placeholder="Enter some notes" name="note"></textarea>
                </div>

                <div class="form-group">
                    <label>Tags</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        </div>
                        <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                            <?php

                            $sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 4 ORDER BY tag_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                                $tag_id_select = intval($row['tag_id']);
                                $tag_name_select = escapeHtml($row['tag_name']);
                                ?>
                                <option value="<?= $tag_id_select ?>"><?= $tag_name_select ?></option>
                            <?php } ?>

                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/tag/tag_add.php?type=4">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_credential" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/generate_password.js"></script>
<script>
$(document).ready(function() {
    function toggleCredentialType(type) {
        if (type === 'Wi-Fi') {
            $('#credential_standard_fields').hide();
            $('#credential_wifi_fields').slideDown(200);
            $('#password').prop('required', false);
            $('#wifi_ssid').prop('required', true);
            $('#credential_name_icon').removeClass('fa-key').addClass('fa-wifi');
            $('#credential_name_input').attr('placeholder', 'e.g. Office Wi-Fi');
        } else {
            $('#credential_wifi_fields').hide();
            $('#credential_standard_fields').slideDown(200);
            $('#password').prop('required', true);
            $('#wifi_ssid').prop('required', false);
            $('#credential_name_icon').removeClass('fa-wifi').addClass('fa-key');
            $('#credential_name_input').attr('placeholder', 'Name of Login');
        }
    }

    $('#credential_type').on('change', function() {
        toggleCredentialType($(this).val());
    });

    $('#wifi_ssid').on('input', function() {
        var nameInput = $('#credential_name_input');
        if (!nameInput.data('user-customized') || nameInput.val() === '') {
            nameInput.val($(this).val());
        }
    });

    $('#credential_name_input').on('input', function() {
        if ($(this).val() !== '') {
            $(this).data('user-customized', true);
        }
    });
});
</script>

<?php

require_once '../../../includes/modal_footer.php';
