<?php

require_once "includes/inc_all.php";

if (empty($session_is_admin) && lookupUserPermission('module_meetings') < 1) {
    enforceUserPermission('module_meetings', 1);
}

$can_create = !empty($session_is_admin) || lookupUserPermission('module_meetings') >= 2;
$series_list = meetingListAccessibleSeries($session_user_id, !empty($session_is_admin));

$q_filter = trim((string)($_GET['q'] ?? ''));
if ($q_filter !== '') {
    $series_list = array_values(array_filter($series_list, function ($s) use ($q_filter) {
        return stripos($s['series_name'], $q_filter) !== false
            || stripos((string)$s['facilitator_name'], $q_filter) !== false
            || stripos((string)$s['recurrence_type'], $q_filter) !== false;
    }));
}
?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2">
            <i class="fas fa-fw fa-comments mr-2"></i>Meetings &amp; Huddles
        </h3>
        <div class="card-tools">
            <?php if ($can_create) { ?>
                <button type="button" class="btn btn-primary btn-sm ajax-modal" data-modal-url="/agent/modals/meeting/meeting_series_modal.php" data-modal-size="lg">
                    <i class="fas fa-plus mr-2"></i>New Recurring Meeting
                </button>
            <?php } ?>
        </div>
    </div>

    <div class="card-body">
        <form autocomplete="off" class="mb-3">
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml($q_filter) ?>" placeholder="Search meetings, facilitators, recurrence...">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php if (empty($series_list)) { ?>
            <div class="text-center py-5 border rounded bg-light">
                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                <h5>No meetings found</h5>
                <p class="text-muted mb-3">Create a recurring Daily Huddle, Weekly Leadership meeting, or custom team rhythm.</p>
                <?php if ($can_create) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="/agent/modals/meeting/meeting_series_modal.php" data-modal-size="lg">
                        <i class="fas fa-plus mr-1"></i>Create First Meeting
                    </button>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="row">
                <?php foreach ($series_list as $s) {
                    $sid = intval($s['series_id']);
                    $is_fac = meetingIsFacilitatorOrAdmin($s, $session_user_id, !empty($session_is_admin));
                    $time_str = date('g:i A', strtotime($s['start_time'])) . ' - ' . date('g:i A', strtotime($s['end_time']));
                ?>
                    <div class="col-md-6 col-xl-4 mb-3">
                        <div class="card h-100 card-outline card-primary shadow-sm">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <a href="/agent/meeting.php?series_id=<?= $sid ?>" class="font-weight-bold text-dark h6 mb-0">
                                    <?= escapeHtml($s['series_name']) ?>
                                </a>
                                <span class="badge badge-<?= $s['visibility'] === 'Company-Wide' ? 'info' : 'secondary' ?>">
                                    <i class="fas fa-<?= $s['visibility'] === 'Company-Wide' ? 'globe' : 'lock' ?> mr-1"></i><?= escapeHtml($s['visibility']) ?>
                                </span>
                            </div>
                            <div class="card-body py-3">
                                <div class="small text-muted mb-2">
                                    <i class="fas fa-user-tie mr-1"></i>Facilitator: <strong><?= escapeHtml($s['facilitator_name'] ?: 'Unassigned') ?></strong>
                                </div>
                                <div class="small mb-2">
                                    <i class="fas fa-sync-alt text-primary mr-1"></i>Recurring <?= escapeHtml($s['recurrence_type']) ?>
                                    &middot; <i class="far fa-clock ml-1 mr-1"></i><?= escapeHtml($time_str) ?>
                                </div>
                                <?php if (!empty($s['series_description'])) { ?>
                                    <p class="small text-muted mb-2"><?= escapeHtml($s['series_description']) ?></p>
                                <?php } ?>
                                <div class="d-flex align-items-center justify-content-between mt-3">
                                    <div class="d-flex align-items-center">
                                        <?php foreach (array_slice($s['attendees'], 0, 6) as $att) { ?>
                                            <?php if (!empty($att['avatar_url'])) { ?>
                                                <img src="<?= escapeHtml($att['avatar_url']) ?>" alt="<?= escapeHtml($att['user_name']) ?>" title="<?= escapeHtml($att['user_name']) ?>" class="rounded-circle border mr-1" style="width:28px; height:28px; object-fit:cover;">
                                            <?php } else { ?>
                                                <span title="<?= escapeHtml($att['user_name']) ?>" class="rounded-circle text-white d-inline-flex align-items-center justify-content-center mr-1 font-weight-bold" style="width:28px; height:28px; font-size:11px; background-color:<?= escapeHtml($att['color']) ?>;">
                                                    <?= escapeHtml($att['initials']) ?>
                                                </span>
                                            <?php } ?>
                                        <?php } ?>
                                        <?php if (count($s['attendees']) > 6) { ?>
                                            <span class="badge badge-light border">+<?= count($s['attendees']) - 6 ?></span>
                                        <?php } ?>
                                    </div>
                                    <span class="badge badge-light border">
                                        <i class="fas fa-tasks mr-1"></i><?= intval($s['open_actions_count']) ?> open
                                    </span>
                                </div>
                            </div>
                            <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
                                <a href="/agent/meeting.php?series_id=<?= $sid ?>&date=<?= escapeHtml($s['default_date']) ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-door-open mr-1"></i>Open Meeting
                                </a>
                                <?php if ($is_fac) { ?>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="/agent/modals/meeting/meeting_series_modal.php?series_id=<?= $sid ?>" data-modal-size="lg" title="Edit Meeting Series">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="/agent/post.php?archive_meeting_series=<?= $sid ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger confirm-link" title="Archive Meeting">
                                            <i class="fas fa-archive"></i>
                                        </a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

<?php require_once "../includes/footer.php";
