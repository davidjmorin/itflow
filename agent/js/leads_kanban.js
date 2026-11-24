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
                    $.post('ajax_leads.php', {
                        csrf_token: window.LEADS_CSRF || '',
                        update_kanban_lead: true,
                        lead_id: leadId,
                        stage_id: newStageId
                    }, null, 'json').done(function (res) {
                        const countTargetEl = $(targetColumn).closest('.lead-kanban-column').find('.lead-stage-count');
                        const countSourceEl = $(sourceColumn).closest('.lead-kanban-column').find('.lead-stage-count');
                        let countTarget = parseInt(countTargetEl.text(), 10) || 0;
                        let countSource = parseInt(countSourceEl.text(), 10) || 0;
                        countTargetEl.text(countTarget + 1);
                        countSourceEl.text(Math.max(0, countSource - 1));
                        if (res && res.stats) {
                            $('#lead-stat-active-cnt').text(res.stats.cnt);
                            $('#lead-stat-active-mrr').text(res.stats.total_mrr_formatted);
                            $('#lead-stat-active-seats').text(res.stats.total_seats);
                        }
                        if (res && res.stage_color) {
                            $(leadCard).css('border-left-color', res.stage_color);
                        }
                        if (window.toastr) {
                            window.toastr.success('Lead stage updated.');
                        }
                    }).fail(function (xhr) {
                        if (sourceColumn && leadCard) {
                            if (evt.oldIndex >= 0 && evt.oldIndex < sourceColumn.children.length) {
                                sourceColumn.insertBefore(leadCard, sourceColumn.children[evt.oldIndex]);
                            } else {
                                sourceColumn.appendChild(leadCard);
                            }
                        }
                        const msg = (xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText || 'Request failed';
                        if (window.toastr) {
                            window.toastr.error('Could not update lead stage: ' + msg);
                        } else {
                            alert('Could not update lead stage: ' + msg);
                        }
                    });
                }
            }
        });
    });
});
