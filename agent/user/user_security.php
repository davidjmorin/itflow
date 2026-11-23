<?php
require_once "includes/inc_all_user.php";

// User remember me tokens
$sql_remember_tokens = mysqli_query($mysqli, "SELECT remember_token_created_at, remember_token_id FROM remember_tokens WHERE remember_token_user_id = $session_user_id");
$remember_token_count = mysqli_num_rows($sql_remember_tokens);

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-shield-alt mr-2"></i>Your Password</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label>Your New Password <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" data-toggle="password" name="new_password" placeholder="Leave blank for no change" autocomplete="new-password" minlength="8" required>
                    <div class="input-group-append">
                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                    </div>
                </div>
            </div>

            <button type="submit" name="edit_your_user_password" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Change</button>

        </form>

         <div class="float-right">
            <?php if (empty($session_token)) { ?>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#enableMFAModal">
                    <i class="fas fa-lock mr-2"></i>Enable MFA
                </button>

                <?php require_once "modals/user_mfa_modal.php"; ?>

            <?php } else { ?>
                <a href="post.php?disable_mfa&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-danger"><i class="fas fa-unlock mr-2"></i>Disable MFA</a>
            <?php } ?>
        </div>

    </div>
</div>

<?php
$sql_passkeys = mysqli_query($mysqli, "SELECT passkey_id, passkey_name, passkey_created_at, passkey_last_used_at FROM user_passkeys WHERE passkey_user_id = $session_user_id ORDER BY passkey_id DESC");
$passkey_count = mysqli_num_rows($sql_passkeys);
?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-fingerprint mr-2"></i>Passkeys & Hardware Keys</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addPasskeyModal">
                <i class="fas fa-plus mr-1"></i>Add Passkey
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if ($passkey_count > 0) { ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Device Name</th>
                            <th>Created</th>
                            <th>Last Used</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($sql_passkeys)) { ?>
                            <tr>
                                <td><i class="fas fa-key mr-2 text-secondary"></i><?= escapeHtml($row['passkey_name']) ?></td>
                                <td><?= escapeHtml($row['passkey_created_at']) ?></td>
                                <td><?= !empty($row['passkey_last_used_at']) ? escapeHtml($row['passkey_last_used_at']) : 'Never' ?></td>
                                <td class="text-right">
                                    <a href="post.php?delete_passkey=<?= intval($row['passkey_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-danger confirm-link">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted mb-0">No passkeys registered. Add a passkey to enable fast, passwordless login using Touch ID, Face ID, Windows Hello, or a security key.</p>
        <?php } ?>
    </div>
</div>

<div class="modal fade" id="addPasskeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fingerprint mr-2"></i>Add Passkey</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Passkey Name</label>
                    <input type="text" class="form-control" id="passkey_name_input" placeholder="e.g. MacBook Touch ID, YubiKey, iPhone" value="My Passkey">
                </div>
                <div id="passkey_error_alert" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="start_passkey_register_btn">
                    <i class="fas fa-shield-alt mr-1"></i>Register Device
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/js/passkey.js"></script>

<?php if ($remember_token_count > 0) { ?>
    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-clock mr-2"></i>2FA Remember-Me Tokens</h3>
        </div>
        <div class="card-body">

            <ul>
                <?php while ($row = mysqli_fetch_assoc($sql_remember_tokens)) {
                    $token_id = intval($row['remember_token_id']);
                    $token_created = escapeHtml($row['remember_token_created_at']);

                    echo "<li>ID: $token_id | Created: $token_created</li>";
                } ?>
            </ul>

            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <button type="submit" name="revoke_your_2fa_remember_tokens" class="btn btn-danger btn-block mt-3"><i class="fas fa-exclamation-triangle mr-2"></i>Revoke Remember-Me Tokens</button>

            </form>

        </div>
    </div>
<?php } ?>

<?php

// Show the error alert if it exists:
if (!empty($_SESSION['alert_type']) && $_SESSION['alert_type'] == 'error') {
    echo "<div class='alert alert-danger'>{$_SESSION['alert_message']}</div>";
    // Clear it so it doesn't persist on refresh
    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);
}

// If the user just failed a TOTP verification, auto-open the modal:
if (!empty($_SESSION['show_mfa_modal'])) {
    echo "
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // jQuery or vanilla JS to open the modal
            $('#enableMFAModal').modal('show');
        });
    </script>";
    unset($_SESSION['show_mfa_modal']);
}

require_once "../../includes/footer.php";
