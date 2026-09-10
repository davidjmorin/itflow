$(document).ready(function () {
    const stageColumns = document.querySelectorAll('.lead-kanban-stage-list');
    if (!stageColumns.length) return;

    stageColumns.forEach(column => {
        new Sortable(column, {
            group: 'leads_pipeline',
            animation: 180,
            filter: 'a, button, .dropdown-menu',
            preventOnFilter: false,
            onEnd: function (evt) {
                const targetColumn = evt.to;
                const sourceColumn = evt.from;
                const leadCard = evt.item;
                const leadId = $(leadCard).data('lead-id');
                const newStageId = $(targetColumn).data('stage-id');
                const oldStageId = $(sourceColumn).data('stage-id');

                if (newStageId && newStageId !== oldStageId) {
                    $.post('ajax.php', {
                        update_kanban_lead: true,
                        lead_id: leadId,
                        stage_id: newStageId
                    }).done(function (res) {
                        const countTargetEl = $(targetColumn).closest('.lead-kanban-column').find('.lead-stage-count');
                        const countSourceEl = $(sourceColumn).closest('.lead-kanban-column').find('.lead-stage-count');
                        let countTarget = parseInt(countTargetEl.text()) || 0;
                        let countSource = parseInt(countSourceEl.text()) || 0;
                        countTargetEl.text(countTarget + 1);
                        countSourceEl.text(Math.max(0, countSource - 1));
                    }).fail(function (xhr) {
                        alert('Could not update lead stage: ' + xhr.responseText);
                    });
                }
            }
        });
    });
});
