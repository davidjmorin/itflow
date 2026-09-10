<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_credential', 2);

$credential_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT credential_archived_at, credential_asset_id, credential_client_id, credential_contact_id,
    credential_created_at, credential_description, credential_favorite, credential_name,
    credential_type, credential_wifi_ssid, credential_wifi_passcode, credential_wifi_encryption,
    credential_note, credential_otp_secret, credential_password, credential_uri,
    credential_uri_2, credential_username FROM credentials WHERE credential_id = $credential_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$client_id = intval($row['credential_client_id']);
$credential_name = escapeHtml($row['credential_name']);
$credential_type = escapeHtml($row['credential_type'] ?? 'Standard');
$credential_wifi_ssid = escapeHtml($row['credential_wifi_ssid'] ?? '');
$credential_wifi_passcode = escapeHtml(decryptCredentialEntry($row['credential_wifi_passcode'] ?? ''));
$credential_wifi_encryption = escapeHtml($row['credential_wifi_encryption'] ?? 'WPA2-PSK');
$credential_description = escapeHtml($row['credential_description']);
$credential_uri = escapeHtml($row['credential_uri']);
$credential_uri_2 = escapeHtml($row['credential_uri_2']);
$credential_uri_link = escapeUrl($row['credential_uri']);
$credential_uri_2_link = escapeUrl($row['credential_uri_2']);
$credential_username = escapeHtml(decryptCredentialEntry($row['credential_username'] ?? ''));
$credential_password = escapeHtml(decryptCredentialEntry($row['credential_password'] ?? ''));
$credential_otp_secret = escapeHtml($row['credential_otp_secret']);
$credential_note = escapeHtml($row['credential_note']);
$credential_created_at = escapeHtml($row['credential_created_at']);
$credential_archived_at = escapeHtml($row['credential_archived_at']);
$credential_favorite = intval($row['credential_favorite']);
$credential_contact_id = intval($row['credential_contact_id']);
$credential_asset_id = intval($row['credential_asset_id']);

// Tags
$credential_tag_id_array = array();
$sql_credential_tags = mysqli_query($mysqli, "SELECT tag_id FROM credential_tags WHERE credential_id = $credential_id");
while ($row = mysqli_fetch_assoc($sql_credential_tags)) {
    $credential_tag_id = intval($row['tag_id']);
    $credential_tag_id_array[] = $credential_tag_id;
}

enforceClientAccess();

logAudit("Credential", "View", "$session_name viewed credential $credential_name (edit)", $client_id, $credential_id);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fas fa-fw <?= $credential_type == "Wi-Fi" ? "fa-wifi" : "fa-key" ?> mr-2'></i>Editing credential: <strong><?= $credential_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="credential_id" value="<?= $credential_id ?>">
    <div class="modal-body" style="max-height: calc(85vh - 120px); overflow-y: auto;">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-credential-details<?= $credential_id ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-credential-relation<?= $credential_id ?>">Relation</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-credential-notes<?= $credential_id ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_credential') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-credential-details<?= $credential_id ?>">

                <div class="form-group">
                    <label>Type <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        </div>
                        <select class="form-control select2" name="type" id="credential_type<?= $credential_id ?>">
                            <option value="Standard" <?= $credential_type == 'Standard' ? 'selected' : '' ?>>Standard Login</option>
                            <option value="Wi-Fi" <?= $credential_type == 'Wi-Fi' ? 'selected' : '' ?>>Wi-Fi Network</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Important?</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw <?= $credential_type == 'Wi-Fi' ? 'fa-wifi' : 'fa-key' ?>" id="credential_name_icon<?= $credential_id ?>"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" id="credential_name_input<?= $credential_id ?>" placeholder="Name of Credential" maxlength="200" value="<?= $credential_name ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <label class="star-toggle mb-0" title="Favorite">
                                    <input type="checkbox"
                                            name="favorite"
                                            value="1"
                                            <?php if($credential_favorite) { echo 'checked'; } ?>>
                                    <i class="far fa-star"></i>
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
                        <input type="text" class="form-control" name="description" placeholder="Description" maxlength="500" value="<?= $credential_description ?>">
                    </div>
                </div>

                <!-- Standard Login Fields -->
                <div id="credential_standard_fields<?= $credential_id ?>" style="<?= $credential_type == 'Wi-Fi' ? 'display: none;' : '' ?>">
                    <div class="form-group">
                        <label>Username / ID</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <input type="text" class="form-control" name="username" placeholder="Username or ID" maxlength="350" value="<?= $credential_username ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Password / Key <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            </div>
                            <input type="password" class="form-control" data-toggle="password" id="password<?= $credential_id ?>" name="password" placeholder="Password or Key" maxlength="350" value="<?= $credential_password ?>" <?= $credential_type == 'Standard' ? 'required' : '' ?> autocomplete="new-password">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            </div>
                            <?php if (!empty($credential_password)) { ?>
                            <div class="input-group-append">
                                <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?= $credential_password ?>"><i class="fa fa-fw fa-copy"></i></button>
                            </div>
                            <?php } ?>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-default" onclick="generatePassword('password<?= $credential_id ?>')"><i class="fa fa-fw fa-magic" title="Generate Password"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>OTP</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                            </div>
                            <input type="password" class="form-control" data-toggle="password" name="otp_secret" maxlength="200" value="<?= $credential_otp_secret ?>" placeholder="Insert secret key">
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
                            <input type="text" class="form-control" name="uri" placeholder="ex. http://192.168.1.1" maxlength="500" value="<?= $credential_uri ?>">
                            <div class="input-group-append">
                                <a href="<?= $credential_uri_link ?>" target="_blank" class="input-group-text"><i class="fa fa-fw fa-link"></i></a>
                            </div>
                            <div class="input-group-append">
                                <button class="input-group-text clipboardjs" type="button" data-clipboard-text="<?= $credential_uri ?>"><i class="fa fa-fw fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>URI 2</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                            </div>
                            <input type="text" class="form-control" name="uri_2" placeholder="ex. https://server.company.com:5001" maxlength="500" value="<?= $credential_uri_2 ?>">
                            <div class="input-group-append">
                                <a href="<?= $credential_uri_2_link ?>" target="_blank" class="input-group-text"><i class="fa fa-fw fa-link"></i></a>
                            </div>
                            <div class="input-group-append">
                                <button class="input-group-text clipboardjs" type="button" data-clipboard-text="<?= $credential_uri_2 ?>"><i class="fa fa-fw fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wi-Fi Network & Router Admin Fields -->
                <div id="credential_wifi_fields<?= $credential_id ?>" style="<?= $credential_type == 'Wi-Fi' ? '' : 'display: none;' ?>">
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
                                        <input type="text" class="form-control" name="wifi_ssid" id="wifi_ssid<?= $credential_id ?>" placeholder="Wi-Fi Network SSID" maxlength="200" value="<?= $credential_wifi_ssid ?>">
                                        <?php if (!empty($credential_wifi_ssid)) { ?>
                                        <div class="input-group-append">
                                            <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?= $credential_wifi_ssid ?>"><i class="fa fa-fw fa-copy"></i></button>
                                        </div>
                                        <?php } ?>
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
                                        <select class="form-control select2" name="wifi_encryption" id="wifi_encryption<?= $credential_id ?>">
                                            <option value="WPA2-PSK" <?= $credential_wifi_encryption == 'WPA2-PSK' ? 'selected' : '' ?>>WPA2-Personal (AES/PSK)</option>
                                            <option value="WPA3-Personal" <?= $credential_wifi_encryption == 'WPA3-Personal' ? 'selected' : '' ?>>WPA3-Personal (SAE)</option>
                                            <option value="WPA2/WPA3-Personal" <?= $credential_wifi_encryption == 'WPA2/WPA3-Personal' ? 'selected' : '' ?>>WPA2/WPA3-Personal (Mixed)</option>
                                            <option value="WPA2-Enterprise" <?= $credential_wifi_encryption == 'WPA2-Enterprise' ? 'selected' : '' ?>>WPA2-Enterprise (802.1X)</option>
                                            <option value="WPA3-Enterprise" <?= $credential_wifi_encryption == 'WPA3-Enterprise' ? 'selected' : '' ?>>WPA3-Enterprise (802.1X)</option>
                                            <option value="Open" <?= $credential_wifi_encryption == 'Open' ? 'selected' : '' ?>>Open (No Password)</option>
                                            <option value="WEP" <?= $credential_wifi_encryption == 'WEP' ? 'selected' : '' ?>>WEP (Legacy)</option>
                                            <option value="Other" <?= $credential_wifi_encryption == 'Other' ? 'selected' : '' ?>>Other</option>
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
                                <input type="password" class="form-control" data-toggle="password" id="wifi_passcode<?= $credential_id ?>" name="wifi_passcode" placeholder="Wi-Fi Passcode" maxlength="350" value="<?= $credential_wifi_passcode ?>" autocomplete="new-password">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                </div>
                                <?php if (!empty($credential_wifi_passcode)) { ?>
                                <div class="input-group-append">
                                    <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?= $credential_wifi_passcode ?>"><i class="fa fa-fw fa-copy"></i></button>
                                </div>
                                <?php } ?>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-default" onclick="generatePassword('wifi_passcode<?= $credential_id ?>')"><i class="fa fa-fw fa-magic" title="Generate Password"></i></button>
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
                                        <input type="text" class="form-control" name="wifi_admin_uri" id="wifi_admin_uri<?= $credential_id ?>" placeholder="http://192.168.1.1" maxlength="500" value="<?= $credential_uri ?>">
                                        <?php if (!empty($credential_uri)) { ?>
                                        <div class="input-group-append">
                                            <a href="<?= $credential_uri_link ?>" target="_blank" class="input-group-text"><i class="fa fa-fw fa-link"></i></a>
                                        </div>
                                        <div class="input-group-append">
                                            <button class="input-group-text clipboardjs" type="button" data-clipboard-text="<?= $credential_uri ?>"><i class="fa fa-fw fa-copy"></i></button>
                                        </div>
                                        <?php } ?>
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
                                        <input type="text" class="form-control" name="wifi_admin_username" id="wifi_admin_username<?= $credential_id ?>" placeholder="e.g. admin" maxlength="350" value="<?= $credential_username ?>">
                                        <?php if (!empty($credential_username)) { ?>
                                        <div class="input-group-append">
                                            <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?= $credential_username ?>"><i class="fa fa-fw fa-copy"></i></button>
                                        </div>
                                        <?php } ?>
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
                                        <input type="password" class="form-control" data-toggle="password" id="wifi_admin_password<?= $credential_id ?>" name="wifi_admin_password" placeholder="Admin Password or Key" maxlength="350" value="<?= $credential_password ?>" autocomplete="new-password">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                        </div>
                                        <?php if (!empty($credential_password)) { ?>
                                        <div class="input-group-append">
                                            <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?= $credential_password ?>"><i class="fa fa-fw fa-copy"></i></button>
                                        </div>
                                        <?php } ?>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-default" onclick="generatePassword('wifi_admin_password<?= $credential_id ?>')"><i class="fa fa-fw fa-magic" title="Generate Password"></i></button>
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
                                        <input type="password" class="form-control" data-toggle="password" name="wifi_admin_otp_secret" id="wifi_admin_otp_secret<?= $credential_id ?>" placeholder="Insert secret key" maxlength="200" value="<?= $credential_otp_secret ?>">
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

            <div class="tab-pane fade" id="pills-credential-relation<?= $credential_id ?>">

                <div class="form-group">
                    <label>Contact</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="contact">
                            <option value="">- Select Contact -</option>
                            <?php

                            $sql_contacts = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_contacts)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name_select = escapeHtml($row['contact_name']);
                                ?>
                                <option <?php if ($credential_contact_id == $contact_id_select) { echo "selected"; } ?> value="<?= $contact_id_select ?>"><?= $contact_name_select ?></option>
                            <?php } ?>
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
                            <option value="0">- Select Asset -</option>
                            <?php

                            $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, location_name  FROM assets LEFT JOIN locations on asset_location_id = location_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_assets)) {
                                $asset_id_select = intval($row['asset_id']);
                                $asset_name_select = escapeHtml($row['asset_name']);
                                $asset_location_select = escapeHtml($row['location_name']);

                                $asset_select_display_string = $asset_name_select;
                                if (!empty($asset_location_select)) {
                                    $asset_select_display_string = "$asset_name_select ($asset_location_select)";
                                }

                                ?>
                                <option <?php if ($credential_asset_id == $asset_id_select) { echo "selected"; } ?> value="<?= $asset_id_select ?>"><?= $asset_select_display_string ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-credential-notes<?= $credential_id ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="12" placeholder="Enter some notes" name="note"><?= $credential_note ?></textarea>
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
                                <option value="<?= $tag_id_select ?>" <?php if (in_array($tag_id_select, $credential_tag_id_array)) { echo "selected"; } ?>><?= $tag_name_select ?></option>
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
        <button type="submit" name="edit_credential" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/generate_password.js"></script>
<script>
$(document).ready(function() {
    function toggleCredentialType<?= $credential_id ?>(type) {
        if (type === 'Wi-Fi') {
            $('#credential_standard_fields<?= $credential_id ?>').hide();
            $('#credential_wifi_fields<?= $credential_id ?>').slideDown(200);
            $('#password<?= $credential_id ?>').prop('required', false);
            $('#wifi_ssid<?= $credential_id ?>').prop('required', true);
            $('#credential_name_icon<?= $credential_id ?>').removeClass('fa-key').addClass('fa-wifi');
            $('#credential_name_input<?= $credential_id ?>').attr('placeholder', 'e.g. Office Wi-Fi');
        } else {
            $('#credential_wifi_fields<?= $credential_id ?>').hide();
            $('#credential_standard_fields<?= $credential_id ?>').slideDown(200);
            $('#password<?= $credential_id ?>').prop('required', true);
            $('#wifi_ssid<?= $credential_id ?>').prop('required', false);
            $('#credential_name_icon<?= $credential_id ?>').removeClass('fa-wifi').addClass('fa-key');
            $('#credential_name_input<?= $credential_id ?>').attr('placeholder', 'Name of Credential');
        }
    }

    $('#credential_type<?= $credential_id ?>').on('change', function() {
        toggleCredentialType<?= $credential_id ?>($(this).val());
    });
});
</script>

<?php
require_once '../../../includes/modal_footer.php';
