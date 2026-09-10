<?php

require_once "includes/inc_all.php";

enforceUserPermission('module_client');

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
    <li class="breadcrumb-item"><a href="leads.php">Leads</a></li>
    <li class="breadcrumb-item active">Lead Email Templates</li>
</ol>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-envelope-open-text mr-2"></i>Lead Email Outreach Templates</h3>
        <div class="card-tools">
            <a href="leads.php" class="btn btn-outline-light btn-sm mr-2"><i class="fas fa-arrow-left mr-1"></i>Back to Leads</a>
            <?php if (lookupUserPermission("module_client") >= 2) { ?>
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addLeadEmailTemplateModal">
                    <i class="fas fa-plus mr-1"></i>New Template
                </button>
            <?php } ?>
        </div>
    </div>
    <div class="card-body p-3">
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i><strong>Dynamic Merge Variables:</strong> You can use these tags inside template subjects and bodies to automatically customize emails when sending:
            <div class="mt-2">
                <code>{lead_name}</code> - Company Name &nbsp;|&nbsp;
                <code>{contact_name}</code> - Contact Person &nbsp;|&nbsp;
                <code>{contact_email}</code> - Contact Email &nbsp;|&nbsp;
                <code>{contact_phone}</code> - Contact Phone &nbsp;|&nbsp;
                <code>{company_name}</code> - Your MSP &nbsp;|&nbsp;
                <code>{sender_name}</code> - Your Name &nbsp;|&nbsp;
                <code>{sender_email}</code> - Your Email &nbsp;|&nbsp;
                <code>{sender_phone}</code> - Your Phone
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Template Name</th>
                        <th>Subject Line</th>
                        <th>Preview</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT * FROM lead_email_templates ORDER BY lead_email_template_name ASC");
                    if (mysqli_num_rows($sql) === 0) {
                        echo "<tr><td colspan='4' class='text-center text-muted py-4'>No email templates configured yet.</td></tr>";
                    }
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $t_id = intval($row['lead_email_template_id']);
                        $t_name = escapeHtml($row['lead_email_template_name']);
                        $t_subject = escapeHtml($row['lead_email_template_subject']);
                        $preview = escapeHtml(strip_tags($row['lead_email_template_body']));
                        if (strlen($preview) > 120) {
                            $preview = substr($preview, 0, 120) . '...';
                        }
                    ?>
                        <tr>
                            <td class="font-weight-bold"><?= $t_name ?></td>
                            <td><?= $t_subject ?></td>
                            <td class="text-muted"><?= $preview ?></td>
                            <td class="text-right">
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-light btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLeadEmailTemplateModal<?= $t_id ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <?php if (lookupUserPermission("module_client") >= 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_lead_email_template=<?= $t_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <div class="modal fade" id="editLeadEmailTemplateModal<?= $t_id ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-fw fa-edit mr-2"></i>Edit Template: <?= $t_name ?></h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <form action="post.php" method="post" autocomplete="off">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="template_id" value="<?= $t_id ?>">
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label>Template Name <strong class="text-danger">*</strong></label>
                                                <input type="text" class="form-control" name="template_name" value="<?= $t_name ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Subject Line <strong class="text-danger">*</strong></label>
                                                <input type="text" class="form-control" name="template_subject" value="<?= $t_subject ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Body Content <strong class="text-danger">*</strong></label>
                                                <textarea class="form-control tinymce" name="template_body" rows="8"><?= escapeHtml($row['lead_email_template_body']) ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-white">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" name="edit_lead_email_template" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addLeadEmailTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-plus mr-2"></i>New Lead Email Template</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Template Name <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="template_name" placeholder="e.g. MSP Cyber Assessment Offer" required>
                    </div>
                    <div class="form-group">
                        <label>Subject Line <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="template_subject" placeholder="e.g. Free Cybersecurity Assessment for {lead_name}" required>
                    </div>
                    <div class="form-group">
                        <label>Body Content <strong class="text-danger">*</strong></label>
                        <textarea class="form-control tinymce" name="template_body" rows="8"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_lead_email_template" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Create Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>
