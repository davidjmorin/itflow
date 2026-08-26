<?php
require_once "includes/inc_all_admin.php";
?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-book mr-2"></i>Knowledge Base (FAQ)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addKbModal">
                <i class="fas fa-plus mr-2"></i>New Article
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark">
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT * FROM knowledge_base ORDER BY kb_title ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $kb_id = intval($row['kb_id']);
                        $kb_title = escapeHtml($row['kb_title']);
                        $kb_status = intval($row['kb_status']);
                        $kb_updated_at = escapeHtml($row['kb_updated_at']);
                    ?>
                        <tr>
                            <td class="align-middle"><strong><?= $kb_title ?></strong></td>
                            <td class="align-middle">
                                <?php if ($kb_status == 1) { ?>
                                    <span class="badge badge-success">Published</span>
                                <?php } else { ?>
                                    <span class="badge badge-secondary">Draft</span>
                                <?php } ?>
                            </td>
                            <td class="align-middle"><?= $kb_updated_at ?></td>
                            <td class="text-center align-middle">
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-light btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editKbModal<?= $kb_id ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#deleteKbModal<?= $kb_id ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editKbModal<?= $kb_id ?>">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-fw fa-edit mr-2"></i>Edit Article</h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <form action="post/knowledge_base.php" method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="kb_id" value="<?= $kb_id ?>">
                                            <div class="form-group">
                                                <label>Title</label>
                                                <input type="text" class="form-control" name="kb_title" value="<?= $kb_title ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select class="form-control" name="kb_status">
                                                    <option value="1" <?= $kb_status == 1 ? 'selected' : '' ?>>Published</option>
                                                    <option value="0" <?= $kb_status == 0 ? 'selected' : '' ?>>Draft</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Content</label>
                                                <textarea class="form-control tinymce" name="kb_content" required><?= escapeHtml($row['kb_content']) ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-white">
                                            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                                            <button type="submit" name="edit_kb" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteKbModal<?= $kb_id ?>">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-fw fa-trash mr-2"></i>Delete Article</h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <form action="post/knowledge_base.php" method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="kb_id" value="<?= $kb_id ?>">
                                            <p>Are you sure you want to delete this article?</p>
                                        </div>
                                        <div class="modal-footer bg-white">
                                            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete_kb" class="btn btn-danger text-bold"><i class="fas fa-trash mr-2"></i>Delete</button>
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
<div class="modal fade" id="addKbModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-plus mr-2"></i>New Article</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post/knowledge_base.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" class="form-control" name="kb_title" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="kb_status">
                            <option value="1">Published</option>
                            <option value="0">Draft</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Content</label>
                        <textarea class="form-control tinymce" name="kb_content" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_kb" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
