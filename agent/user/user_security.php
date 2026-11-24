<?php

/*
 * Your account > Security - your password, multi-factor authentication, and
 * the browsers that skip your MFA code. Changing the password or turning MFA
 * off takes your current password; you are emailed either way.
 */

require_once "includes/inc_all_user.php";

$me = userRow($session_user_id);
$has_mfa = trim((string) $me['user_token']) !== '';
$forced = intval($me['user_config_force_mfa']) === 1;
$browsers = userRememberedBrowsers($session_user_id);
$remember_days = max(1, intval($config_login_remember_me_expire));
$sql_passkeys = mysqli_query($mysqli, "SELECT passkey_id, passkey_name, passkey_created_at, passkey_last_used_at FROM user_passkeys WHERE passkey_user_id = $session_user_id ORDER BY passkey_id DESC");
$passkey_count = $sql_passkeys ? mysqli_num_rows($sql_passkeys) : 0;

?>

<div class="row g-3">

    <!-- Password -->
    <div class="col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-key me-2"></i>Password</h3>
            </div>
            <form action="post.php" method="post" autocomplete="off" id="passwordForm" data-autofill>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Current password <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control" data-toggle="password" name="current_password" autocomplete="current-password" required>
                            <span class="input-group-text"><i class="fas fa-fw fa-eye"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New password <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                            <input type="password" class="form-control" data-toggle="password" name="new_password" placeholder="At least <?= USER_PASSWORD_MIN ?> characters" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required data-password-new>
                            <span class="input-group-text"><i class="fas fa-fw fa-eye"></i></span>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">New password, again <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-check-double"></i></span>
                            <input type="password" class="form-control" name="confirm_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required data-password-confirm>
                        </div>
                        <div class="form-text" data-password-match>You're signed out once it's changed, to sign in with the new one.</div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" name="edit_your_user_password" class="btn btn-primary"><i class="fas fa-check me-2"></i>Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MFA -->
    <div class="col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-mobile-alt me-2"></i>Multi-factor authentication</h3>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <span class="itflow-mfa-state <?= $has_mfa ? 'is-on' : 'is-off' ?>"><i class="fas <?= $has_mfa ? 'fa-lock' : 'fa-unlock' ?>"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold"><?= $has_mfa ? 'On' : 'Off' ?><?= $forced ? ' <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1">Required by your administrator</span>' : '' ?></div>
                        <div class="small text-body-secondary">
                            <?php if ($has_mfa) { ?>
                                Signing in takes your password and a code from your authenticator app.
                            <?php } else { ?>
                                Signing in takes only your password. With MFA it also takes a code from an app on your phone - so a stolen password alone doesn't get anyone in.
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <?php if ($has_mfa && !$forced) { ?>
                    <div class="collapse mt-3" id="disableMfa">
                        <form action="post.php" method="post" autocomplete="off" class="border rounded p-3">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <label class="form-label">Your password <strong class="text-danger">*</strong></label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                                <input type="password" class="form-control" data-toggle="password" name="current_password" placeholder="To turn MFA off" autocomplete="current-password" required>
                                <span class="input-group-text"><i class="fas fa-fw fa-eye"></i></span>
                            </div>
                            <button type="submit" name="disable_your_mfa" class="btn btn-danger btn-sm"><i class="fas fa-unlock me-2"></i>Turn MFA Off</button>
                        </form>
                    </div>
                <?php } ?>
            </div>
            <div class="card-footer">
                <?php if (!$has_mfa) { ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enableMFAModal"><i class="fas fa-lock me-2"></i>Turn On MFA</button>
                <?php } elseif (!$forced) { ?>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#disableMfa" aria-expanded="false"><i class="fas fa-unlock me-2"></i>Turn Off</button>
                    <span class="small text-body-secondary ms-2">New phone? Turn it off, then on again with the new one.</span>
                <?php } else { ?>
                    <span class="small text-body-secondary">New phone? Ask an administrator to reset your MFA - you set it up again at your next sign in.</span>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-fingerprint me-2"></i>Passkeys &amp; Hardware Keys</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPasskeyModal">
                        <i class="fas fa-plus me-1"></i>Add Passkey
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
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($pk_row = mysqli_fetch_assoc($sql_passkeys)) { ?>
                                    <tr>
                                        <td><i class="fas fa-key me-2 text-body-secondary"></i><?= escapeHtml($pk_row['passkey_name']) ?></td>
                                        <td><?= escapeHtml($pk_row['passkey_created_at']) ?></td>
                                        <td><?= !empty($pk_row['passkey_last_used_at']) ? escapeHtml($pk_row['passkey_last_used_at']) : 'Never' ?></td>
                                        <td class="text-end">
                                            <a href="post.php?delete_passkey=<?= intval($pk_row['passkey_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-danger confirm-link">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <p class="text-body-secondary small mb-0">No passkeys registered. Add a passkey to enable fast, passwordless login using Touch ID, Face ID, Windows Hello, or a security key.</p>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Remembered browsers -->
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header bg-dark">
                <h3 class="card-title"><i class="fas fa-fw fa-laptop me-2"></i>Remembered browsers</h3>
                <?php if (count($browsers) > 1) { ?>
                    <div class="card-tools">
                        <form action="post.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" name="revoke_your_2fa_remember_tokens" class="btn btn-sm btn-outline-light"><i class="fas fa-times me-2"></i>Forget All</button>
                        </form>
                    </div>
                <?php } ?>
            </div>
            <?php if ($browsers) { ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($browsers as $browser) {
                        $login = $browser['login'];
                        ?>
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <i class="fas fa-fw fa-laptop fa-lg text-body-secondary"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div>
                                    <?= $login ? userDeviceHtml($login['log_user_agent']) : '<span class="text-body-secondary">A browser</span>' ?>
                                    <?php if ($browser['is_current']) { ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal ms-1">This browser</span><?php } ?>
                                </div>
                                <div class="small text-body-secondary">
                                    Remembered <span title="<?= formatDateTime($browser['remember_token_created_at']) ?>"><?= escapeHtml(timeAgo($browser['remember_token_created_at'])) ?></span>
                                    <?php if ($login) { ?> from <span class="font-monospace"><?= escapeHtml($login['log_ip']) ?></span><?php } ?>
                                    · skips the code until <?= escapeHtml(date('M j', strtotime($browser['remember_token_expires_at']))) ?>
                                </div>
                            </div>
                            <form action="post.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" name="revoke_your_remember_token" value="<?= intval($browser['remember_token_id']) ?>" class="btn btn-sm btn-outline-secondary" title="It asks for your code at the next sign in">Forget</button>
                            </form>
                        </li>
                    <?php } ?>
                </ul>
            <?php } else { ?>
                <div class="card-body small text-body-secondary">
                    <?php if ($has_mfa) { ?>
                        None. Tick <strong>Remember me</strong> when you enter your code at sign in, and that browser skips the code for <?= $remember_days ?> days.
                    <?php } else { ?>
                        None - browsers are remembered once MFA is on, so you're not asked for a code every time.
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>

</div>

<?php if (!$has_mfa) {
    require_once "modals/user_mfa_modal.php";
} ?>

<script>
(function () {
    // The new password, typed twice - said as soon as they match, or don't
    var form = document.getElementById('passwordForm');
    var next = form.querySelector('[data-password-new]');
    var again = form.querySelector('[data-password-confirm]');
    var hint = form.querySelector('[data-password-match]');
    var hintText = hint.textContent;
    function check() {
        var differs = again.value !== '' && again.value !== next.value;
        again.setCustomValidity(differs ? 'The two new passwords are not the same' : '');
        again.classList.toggle('is-invalid', differs);
        again.classList.toggle('is-valid', again.value !== '' && !differs && next.value.length >= <?= USER_PASSWORD_MIN ?>);
        hint.textContent = differs ? 'Not the same as the new password above.' : hintText;
        hint.classList.toggle('text-danger', differs);
    }
    next.addEventListener('input', check);
    again.addEventListener('input', check);
})();
</script>

<div class="modal fade" id="addPasskeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-fingerprint me-2"></i>Add Passkey</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="passkey_name_input">Passkey Name</label>
                    <input type="text" class="form-control" id="passkey_name_input" placeholder="e.g. MacBook Touch ID, YubiKey, iPhone" value="My Passkey">
                </div>
                <div id="passkey_error_alert" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="start_passkey_register_btn">
                    <i class="fas fa-shield-alt me-1"></i>Register Device
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= staticFileUrl('/js/passkey.js') ?>"></script>

<?php
// A code that didn't match - open the set up again, with the same secret
if (!empty($_SESSION['show_mfa_modal']) && !$has_mfa) { ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('enableMFAModal')).show();
        });
    </script>
<?php }
unset($_SESSION['show_mfa_modal']);

require_once "../../includes/footer.php";
