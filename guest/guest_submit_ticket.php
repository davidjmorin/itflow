<?php

require_once "includes/inc_all_guest.php";

$company_sql = mysqli_query($mysqli, "SELECT company_name, company_logo, company_phone, company_phone_country_code FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
$company_row = mysqli_fetch_assoc($company_sql);
$company_name = escapeHtml($company_row['company_name'] ?? 'Support Portal');
$company_logo = $company_row['company_logo'] ?? '';
$company_phone = escapeHtml(formatPhoneNumber($company_row['company_phone'] ?? '', $company_row['company_phone_country_code'] ?? ''));

?>

<div class="row justify-content-center mt-3 mb-5">
    <div class="col-lg-8 col-md-10">

        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-ticket-alt mr-2"></i>Submit a Support Ticket
                        </h4>
                        <small class="text-muted">No account required. Fill out the details below and our team will get back to you.</small>
                    </div>
                    <div>
                        <a href="/login.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-sign-in-alt mr-1"></i>Client Login
                        </a>
                    </div>
                </div>
            </div>

            <form action="guest_post.php" method="post" enctype="multipart/form-data" id="guestTicketForm">
                <input type="hidden" name="guest_submit_ticket" value="1">
                <div class="card-body p-4">

                    <input type="text" name="website_hp" style="display:none !important;" tabindex="-1" autocomplete="off">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Your Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="John Doe" required autofocus>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Work Email <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="john@company.com" required>
                                </div>
                                <small id="emailHelp" class="form-text text-muted">We will automatically match your company based on your email domain.</small>
                            </div>
                        </div>
                    </div>

                    <div id="companyMatchedAlert" class="alert alert-success d-none py-2 px-3 mb-3">
                        <i class="fas fa-building mr-1"></i> Associated Organization: <strong id="matchedCompanyName"></strong>
                    </div>

                    <div class="row">
                        <div class="col-md-6" id="companyFieldContainer">
                            <div class="form-group">
                                <label for="company_name">Company / Organization Name</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-building"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="company_name" name="company_name" placeholder="Acme Corp">
                                </div>
                                <small class="form-text text-muted">If your company is new to us, please enter its name.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-phone"></i></span>
                                    </div>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="(555) 123-4567">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="subject">Subject <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="subject" name="subject" placeholder="Brief summary of the issue" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="priority">Priority <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-thermometer-half"></i></span>
                                    </div>
                                    <select class="form-control" id="priority" name="priority" required>
                                        <option value="Low" selected>Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="details">Detailed Description <strong class="text-danger">*</strong></label>
                        <textarea class="form-control" id="details" name="details" rows="6" placeholder="Please describe the issue, error messages, and any relevant steps to reproduce..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="attachments">Attachments</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="attachments" name="attachments[]" multiple>
                            <label class="custom-file-label" for="attachments">Choose files (images, logs, documents)...</label>
                        </div>
                        <small class="form-text text-muted">Supported types: JPG, PNG, PDF, TXT, DOCX, XLSX, ZIP.</small>
                    </div>

                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <a href="/login.php" class="btn btn-secondary">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </a>
                    <button type="submit" id="guestSubmitButton" name="guest_submit_ticket" class="btn btn-primary px-4 font-weight-bold">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Ticket
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {
    $('#guestTicketForm').on('submit', function() {
        if (this.checkValidity && !this.checkValidity()) {
            return;
        }
        const submitBtn = $('#guestSubmitButton');
        submitBtn.prop('disabled', true).addClass('disabled');
        submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...');
    });

    let checkTimeout = null;

    $('#email').on('input change', function() {
        clearTimeout(checkTimeout);
        const email = $(this).val().trim();
        const companyInput = $('#company_name');
        const alertBox = $('#companyMatchedAlert');
        const matchedNameSpan = $('#matchedCompanyName');

        if (email.includes('@') && email.indexOf('@') < email.length - 2) {
            checkTimeout = setTimeout(function() {
                $.ajax({
                    url: 'guest_ajax.php',
                    type: 'GET',
                    data: {
                        check_guest_company: '1',
                        email: email
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.matched && response.client_name) {
                            matchedNameSpan.text(response.client_name);
                            alertBox.removeClass('d-none');
                            if (!companyInput.val()) {
                                companyInput.val(response.client_name);
                            }
                        } else {
                            alertBox.addClass('d-none');
                        }
                    }
                });
            }, 400);
        } else {
            alertBox.addClass('d-none');
        }
    });

    $('#attachments').on('change', function() {
        const files = $(this)[0].files;
        if (files.length === 1) {
            $(this).next('.custom-file-label').text(files[0].name);
        } else if (files.length > 1) {
            $(this).next('.custom-file-label').text(files.length + ' files selected');
        } else {
            $(this).next('.custom-file-label').text('Choose files (images, logs, documents)...');
        }
    });
});
</script>

<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
