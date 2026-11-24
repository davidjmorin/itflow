<?php

require_once "includes/inc_all_guest.php";

$support_email = !empty($config_ticket_from_email) ? $config_ticket_from_email : ($config_mail_from_email ?? '');
?>

<div class="row justify-content-center mt-3 mb-5">
    <div class="col-lg-8 col-md-10">

        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h4 class="m-0 fw-bold text-primary">
                            <i class="fas fa-ticket-alt me-2"></i>Submit a Support Ticket
                        </h4>
                        <small class="text-body-secondary">Enter your work email below to verify your organization and submit a ticket without signing in.</small>
                    </div>
                    <div>
                        <a href="/login.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-sign-in-alt me-1"></i>Client Login
                        </a>
                    </div>
                </div>
            </div>

            <form action="guest_post.php" method="post" enctype="multipart/form-data" id="guestTicketForm">
                <input type="hidden" name="guest_submit_ticket" value="1">
                <div class="card-body p-4">

                    <input type="text" name="website_hp" style="display:none !important;" tabindex="-1" autocomplete="off">

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="email">Work Email <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="you@yourcompany.com" required autofocus>
                            <button type="button" class="btn btn-outline-primary" id="verifyEmailBtn">
                                <i class="fas fa-search me-1"></i>Verify Email
                            </button>
                        </div>
                        <div class="form-text" id="emailVerifyHint">We match your email domain to your organization automatically.</div>
                    </div>

                    <div id="companyMatchedAlert" class="alert alert-success d-none py-2 px-3 mb-3">
                        <i class="fas fa-check-circle me-2"></i>Verified Organization: <strong id="matchedCompanyName"></strong>
                    </div>

                    <div id="companyUnmatchedAlert" class="alert alert-warning d-none py-3 px-3 mb-0">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-exclamation-triangle mt-1"></i>
                            <div>
                                <div class="fw-bold mb-1">We couldn't match your email domain to an existing organization.</div>
                                <?php if (!empty($support_email)) { ?>
                                    <div>Please send an email directly to <a class="fw-bold" id="unmatchedSupportLink" href="mailto:<?= escapeHtml($support_email) ?>"><?= escapeHtml($support_email) ?></a> to open a support ticket, or sign in with your client portal account.</div>
                                <?php } else { ?>
                                    <div>Please contact your support team directly or sign in with your client portal account.</div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div id="guestTicketFields" class="d-none">
                        <hr class="my-3">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Your Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-user"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="John Doe" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="(555) 123-4567">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label" for="subject">Subject <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-tag"></i></span>
                                    <input type="text" class="form-control" id="subject" name="subject" placeholder="Brief summary of the issue" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="priority">Priority <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-thermometer-half"></i></span>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="Low" selected>Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="details">Detailed Description <strong class="text-danger">*</strong></label>
                            <textarea class="form-control" id="details" name="details" rows="6" placeholder="Please describe the issue, error messages, and any relevant steps to reproduce..." required></textarea>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="attachments">Attachments</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                            <div class="form-text">Supported types: JPG, PNG, PDF, TXT, DOCX, XLSX, ZIP.</div>
                        </div>
                    </div>

                </div>

                <div class="card-footer p-3 d-flex justify-content-between align-items-center">
                    <a href="/login.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                    <button type="submit" id="guestSubmitButton" name="guest_submit_ticket" class="btn btn-primary px-4 fw-bold" disabled>
                        <i class="fas fa-paper-plane me-2"></i>Submit Ticket
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
(function () {
    'use strict';

    const formEl = document.getElementById('guestTicketForm');
    const emailInput = document.getElementById('email');
    const verifyBtn = document.getElementById('verifyEmailBtn');
    const hintEl = document.getElementById('emailVerifyHint');
    const matchedAlert = document.getElementById('companyMatchedAlert');
    const matchedNameEl = document.getElementById('matchedCompanyName');
    const unmatchedAlert = document.getElementById('companyUnmatchedAlert');
    const unmatchedLink = document.getElementById('unmatchedSupportLink');
    const fieldsWrap = document.getElementById('guestTicketFields');
    const submitBtn = document.getElementById('guestSubmitButton');

    let checkTimer = null;
    let lastCheckedEmail = '';
    let emailVerified = false;

    function isValidEmailFormat(val) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val);
    }

    function resetVerification() {
        emailVerified = false;
        matchedAlert.classList.add('d-none');
        unmatchedAlert.classList.add('d-none');
        fieldsWrap.classList.add('d-none');
        submitBtn.disabled = true;
    }

    function applyCheckResult(res) {
        if (res && res.matched && res.client_name) {
            emailVerified = true;
            matchedNameEl.textContent = res.client_name;
            matchedAlert.classList.remove('d-none');
            unmatchedAlert.classList.add('d-none');
            fieldsWrap.classList.remove('d-none');
            submitBtn.disabled = false;
            hintEl.textContent = 'Organization verified. Complete the ticket details below.';
        } else {
            emailVerified = false;
            matchedAlert.classList.add('d-none');
            fieldsWrap.classList.add('d-none');
            submitBtn.disabled = true;
            if (res && res.support_email && unmatchedLink) {
                unmatchedLink.textContent = res.support_email;
                unmatchedLink.setAttribute('href', 'mailto:' + res.support_email);
            }
            unmatchedAlert.classList.remove('d-none');
            hintEl.textContent = 'Domain not recognized under an active client account.';
        }
    }

    function verifyEmail(force) {
        const email = (emailInput.value || '').trim();
        if (!isValidEmailFormat(email)) {
            lastCheckedEmail = '';
            resetVerification();
            hintEl.textContent = 'Enter a valid work email address to check your organization.';
            return;
        }
        if (!force && email.toLowerCase() === lastCheckedEmail.toLowerCase()) {
            return;
        }
        lastCheckedEmail = email;
        hintEl.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking organization domain...';

        fetch('guest_ajax.php?check_guest_company=1&email=' + encodeURIComponent(email), {
            credentials: 'same-origin'
        })
            .then(function (resp) {
                if (!resp.ok) {
                    throw new Error('HTTP ' + resp.status);
                }
                return resp.json();
            })
            .then(function (res) {
                if ((emailInput.value || '').trim().toLowerCase() !== email.toLowerCase()) {
                    return;
                }
                applyCheckResult(res);
            })
            .catch(function () {
                resetVerification();
                hintEl.textContent = 'Unable to verify email right now. Please try again.';
            });
    }

    emailInput.addEventListener('input', function () {
        clearTimeout(checkTimer);
        const val = (emailInput.value || '').trim();
        if (val.toLowerCase() !== lastCheckedEmail.toLowerCase()) {
            resetVerification();
        }
        if (isValidEmailFormat(val)) {
            checkTimer = setTimeout(function () {
                verifyEmail(false);
            }, 350);
        } else {
            hintEl.textContent = 'We match your email domain to your organization automatically.';
        }
    });

    verifyBtn.addEventListener('click', function () {
        clearTimeout(checkTimer);
        verifyEmail(true);
    });

    formEl.addEventListener('submit', function (e) {
        if (!emailVerified || (formEl.checkValidity && !formEl.checkValidity())) {
            e.preventDefault();
            return;
        }
        submitBtn.disabled = true;
        submitBtn.classList.add('disabled');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    });
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
