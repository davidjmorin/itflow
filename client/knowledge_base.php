<?php
require_once "includes/inc_all.php";
?>
<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-book mr-2"></i>Knowledge Base (FAQ)</h3>
    </div>
    <div class="card-body">
        <div class="accordion" id="kbAccordion">
            <?php
            $sql = mysqli_query($mysqli, "SELECT * FROM knowledge_base WHERE kb_status = 1 ORDER BY kb_title ASC");
            if (mysqli_num_rows($sql) > 0) {
                while ($row = mysqli_fetch_assoc($sql)) {
                    $kb_id = intval($row['kb_id']);
                    $kb_title = escapeHtml($row['kb_title']);
            ?>
                    <div class="card border mb-2">
                        <div class="card-header bg-light p-0" id="heading<?= $kb_id ?>">
                            <h2 class="mb-0">
                                <button class="btn btn-link btn-block text-left text-dark font-weight-bold p-3" type="button" data-toggle="collapse" data-target="#collapse<?= $kb_id ?>">
                                    <i class="fas fa-question-circle mr-2 text-primary"></i><?= $kb_title ?>
                                </button>
                            </h2>
                        </div>
                        <div id="collapse<?= $kb_id ?>" class="collapse" data-parent="#kbAccordion">
                            <div class="card-body bg-white">
                                <?= $row['kb_content'] ?>
                            </div>
                        </div>
                    </div>
            <?php 
                }
            } else {
                echo "<p class='text-muted'>No articles available at this time.</p>";
            }
            ?>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>
