<?php
require_once "includes/inc_all_admin.php";
?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-comment-dots mr-2"></i>Canned Responses</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addCannedResponseModal">
                <i class="fas fa-plus mr-2"></i>Add Response
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark">
                    <tr>
                        <th>Name</th>
                        <th>Content Preview</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT * FROM canned_responses ORDER BY canned_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $canned_id = intval($row['canned_id']);
                        $canned_name = escapeHtml($row['canned_name']);
                        $canned_body = escapeHtml(strip_tags($row['canned_body']));
                        if (strlen($canned_body) > 100) {
                            $canned_body = substr($canned_body, 0, 100) . "...";
                        }
                    ?>
                        <tr>
                            <td class="align-middle"><strong><?= $canned_name ?></strong></td>
                            <td class="align-middle"><?= $canned_body ?></td>
                            <td class="text-center align-middle">
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-light btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editCannedResponseModal<?= $canned_id ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#deleteCannedResponseModal<?= $canned_id ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editCannedResponseModal<?= $canned_id ?>">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-fw fa-edit mr-2"></i>Edit Canned Response</h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <form action="post/canned_responses.php" method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="canned_id" value="<?= $canned_id ?>">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" class="form-control" name="canned_name" value="<?= $canned_name ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Body</label>
                                                <textarea class="form-control tinymce" name="canned_body" required><?= escapeHtml($row['canned_body']) ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-white">
                                            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                                            <button type="submit" name="edit_canned_response" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteCannedResponseModal<?= $canned_id ?>">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-fw fa-trash mr-2"></i>Delete Canned Response</h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <form action="post/canned_responses.php" method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="canned_id" value="<?= $canned_id ?>">
                                            <p>Are you sure you want to delete this canned response?</p>
                                        </div>
                                        <div class="modal-footer bg-white">
                                            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete_canned_response" class="btn btn-danger text-bold"><i class="fas fa-trash mr-2"></i>Delete</button>
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

<!-- Add Modal -->
<div class="modal fade" id="addCannedResponseModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-plus mr-2"></i>Add Canned Response</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post/canned_responses.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" name="canned_name" required>
                    </div>
                    <div class="form-group">
                        <label>Body</label>
                        <textarea class="form-control tinymce" name="canned_body" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_canned_response" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
