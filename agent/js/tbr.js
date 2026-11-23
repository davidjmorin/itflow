(function () {
    'use strict';

    const cfg = window.TBR_CONFIG || {};
    if (!cfg.tbrId) {
        return;
    }

    const canEdit = !!cfg.canEdit;
    const tbrId = cfg.tbrId;
    const csrf = cfg.csrfToken;
    const currencyCode = cfg.currencyCode || 'USD';

    const state = {
        activePanel: 'panel-overview',
        dirtySections: {},
        dirtyMeta: false,
        timers: {},
        inFlight: {},
        retryQueue: {},
        versions: Object.assign({}, cfg.sectionVersions || {}),
        conflictSection: null,
        findings: Array.isArray(cfg.initialFindings) ? cfg.initialFindings : [],
        recommendations: Array.isArray(cfg.initialRecommendations) ? cfg.initialRecommendations : [],
        roadmap: cfg.initialRoadmap || { items: [], totals: { quarters: {}, categories: {}, annual: 0 } },
        actions: Array.isArray(cfg.initialActions) ? cfg.initialActions : []
    };

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function fmtMoney(val) {
        if (val === null || val === undefined || val === '') {
            return 'TBD';
        }
        return currencyCode + ' ' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setSaveStatus(mode, text) {
        const el = $('#tbrSaveIndicator');
        el.removeClass('tbr-save-saved tbr-save-saving tbr-save-error');
        if (mode === 'saving') {
            el.addClass('tbr-save-saving').html('<i class="fas fa-spinner fa-spin"></i> ' + esc(text || 'Saving...'));
        } else if (mode === 'error') {
            el.addClass('tbr-save-error').html('<i class="fas fa-exclamation-triangle"></i> ' + esc(text || 'Save Failed'));
        } else {
            el.addClass('tbr-save-saved').html('<i class="fas fa-check-circle"></i> ' + esc(text || 'Saved'));
        }
    }

    function updateProgressUI(progress) {
        if (!progress) return;
        $('#tbrProgressBar').css('width', progress.percentage + '%');
        $('#tbrProgressText').text(progress.percentage + '% (' + progress.done + '/' + progress.total + ')');

        Object.keys(progress.sections || {}).forEach(function (secKey) {
            const info = progress.sections[secKey];
            const badge = $('.tbr-nav-item[data-target="panel-' + secKey + '"] .tbr-nav-status-icon');
            badge.removeClass('fa-circle fa-adjust fa-check-circle fa-minus-circle text-secondary text-warning text-success text-muted');
            if (info.status === 'Completed') {
                badge.addClass('fas fa-check-circle text-success');
            } else if (info.status === 'In Progress') {
                badge.addClass('fas fa-adjust text-warning');
            } else if (info.status === 'Not Assessed') {
                badge.addClass('fas fa-minus-circle text-muted');
            } else {
                badge.addClass('far fa-circle text-secondary');
            }
        });
    }

    function collectSectionPayload(sectionKey) {
        const panel = $('#panel-' + sectionKey);
        const answers = {};

        panel.find('[data-answer-key]').each(function () {
            const el = $(this);
            const key = el.data('answer-key');
            const val = el.is(':checkbox') ? (el.is(':checked') ? 1 : 0) : el.val();
            answers[key] = val;
        });

        const categories = {};
        panel.find('[data-cat-key]').each(function () {
            const el = $(this);
            const ck = el.data('cat-key');
            const cf = el.data('cat-field');
            categories[ck] = categories[ck] || {};
            categories[ck][cf] = el.val();
        });
        if (Object.keys(categories).length > 0) {
            answers.categories = categories;
        }

        const areas = {};
        panel.find('[data-area-key]').each(function () {
            const el = $(this);
            const ak = el.data('area-key');
            const af = el.data('area-field');
            areas[ak] = areas[ak] || {};
            areas[ak][af] = el.val();
        });
        if (Object.keys(areas).length > 0) {
            answers.areas = areas;
        }

        const assetReviews = {};
        panel.find('[data-asset-id]').each(function () {
            const el = $(this);
            const aid = String(el.data('asset-id'));
            const af = el.data('asset-field');
            assetReviews[aid] = assetReviews[aid] || {};
            assetReviews[aid][af] = el.val();
        });
        if (Object.keys(assetReviews).length > 0) {
            answers.asset_reviews = assetReviews;
        }

        const payload = {
            action: 'save_section',
            csrf_token: csrf,
            tbr_id: tbrId,
            section_key: sectionKey,
            section_status: panel.find('.tbr-section-status').val() || 'In Progress',
            section_health: panel.find('.tbr-section-health').val() || 'Not Assessed',
            answers_json: JSON.stringify(answers),
            client_notes: panel.find('.tbr-client-notes').val() || '',
            follow_up_notes: panel.find('.tbr-followup-notes').val() || '',
            expected_version: state.versions[sectionKey] || 1
        };

        const intEl = panel.find('.tbr-internal-notes');
        if (intEl.length > 0) {
            payload.internal_notes = intEl.val() || '';
        }

        try {
            sessionStorage.setItem('tbr_draft_' + tbrId + '_' + sectionKey, JSON.stringify(payload));
        } catch (e) {}

        return payload;
    }

    function sendSectionSave(sectionKey, forceOverwrite, retryCount) {
        if (!canEdit) return $.Deferred().resolve().promise();

        const attempt = retryCount || 0;
        const payload = collectSectionPayload(sectionKey);
        if (forceOverwrite) {
            payload.force_overwrite = 1;
        }

        delete state.dirtySections[sectionKey];
        state.inFlight[sectionKey] = true;
        setSaveStatus('saving', attempt > 0 ? 'Retrying (' + attempt + ')...' : 'Saving...');

        return $.ajax({
            url: '/agent/ajax_tbr.php',
            method: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (res) {
            delete state.inFlight[sectionKey];
            delete state.retryQueue[sectionKey];
            if (res && res.ok) {
                state.versions[sectionKey] = res.version;
                try {
                    sessionStorage.removeItem('tbr_draft_' + tbrId + '_' + sectionKey);
                } catch (e) {}
                updateProgressUI(res.progress);
                if (Object.keys(state.dirtySections).length === 0 && !state.dirtyMeta) {
                    setSaveStatus('saved', 'Saved ' + (res.saved_at || ''));
                }
            }
        }).fail(function (xhr) {
            delete state.inFlight[sectionKey];
            if (xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.conflict) {
                state.conflictSection = sectionKey;
                $('#tbrConflictSectionLabel').text(sectionKey.toUpperCase());
                $('#tbrConflictAlert').removeClass('d-none');
                setSaveStatus('error', 'Edit Conflict');
                return;
            }
            if (attempt < 3) {
                state.retryQueue[sectionKey] = true;
                setSaveStatus('saving', 'Connection lost — retrying...');
                setTimeout(function () {
                    sendSectionSave(sectionKey, forceOverwrite, attempt + 1);
                }, Math.pow(2, attempt) * 1500);
            } else {
                state.retryQueue[sectionKey] = true;
                setSaveStatus('error', 'Save Failed (Offline/Error)');
            }
        });
    }

    function scheduleSectionSave(sectionKey, immediate) {
        if (!canEdit) return;
        const panel = $('#panel-' + sectionKey);
        const statusEl = panel.find('.tbr-section-status');
        if (statusEl.val() === 'Not Started') {
            statusEl.val('In Progress');
        }
        state.dirtySections[sectionKey] = true;
        clearTimeout(state.timers[sectionKey]);
        setSaveStatus('saving', 'Unsaved changes...');
        state.timers[sectionKey] = setTimeout(function () {
            sendSectionSave(sectionKey, false, 0);
        }, immediate ? 120 : 750);
    }

    function sendMetaSave(immediate) {
        if (!canEdit) return;
        state.dirtyMeta = true;
        clearTimeout(state.timers._meta);
        setSaveStatus('saving', 'Saving...');
        state.timers._meta = setTimeout(function () {
            const attended = {};
            $('.tbr-attendee-attended').each(function () {
                attended[$(this).data('attendee-id')] = $(this).is(':checked') ? 1 : 0;
            });
            const payload = {
                action: 'save_meeting_meta',
                csrf_token: csrf,
                tbr_id: tbrId,
                tbr_executive_summary: $('#tbrExecutiveSummary').val() || '',
                tbr_overall_health: $('#tbrOverallHealth').val() || 'Not Assessed',
                tbr_reporting_period_start: $('#tbrPeriodStart').val() || '',
                tbr_reporting_period_end: $('#tbrPeriodEnd').val() || '',
                tbr_next_review_date: $('#tbrNextReviewDate').val() || '',
                attendees_attended: attended
            };
            if ($('#tbrPrepNotes').length) {
                payload.tbr_prep_notes = $('#tbrPrepNotes').val() || '';
            }
            $.ajax({
                url: '/agent/ajax_tbr.php',
                method: 'POST',
                dataType: 'json',
                data: payload
            }).done(function (res) {
                state.dirtyMeta = false;
                if (res && res.ok) {
                    $('#tbrHeaderHealthBadge').text(res.overall_health);
                    setSaveStatus('saved', 'Saved ' + res.saved_at);
                }
            }).fail(function () {
                setSaveStatus('error', 'Save Failed');
            });
        }, immediate ? 120 : 750);
    }

    function flushAllPending() {
        Object.keys(state.timers).forEach(function (k) {
            clearTimeout(state.timers[k]);
        });
        Object.keys(state.dirtySections).forEach(function (secKey) {
            sendSectionSave(secKey, false, 0);
        });
        if (state.dirtyMeta) {
            sendMetaSave(true);
        }
    }

    function switchPanel(panelId) {
        if (!$('#' + panelId).length) {
            panelId = 'panel-overview';
        }
        flushAllPending();
        state.activePanel = panelId;
        $('.tbr-panel').removeClass('active');
        $('#' + panelId).addClass('active');
        $('.tbr-nav-item').removeClass('active');
        $('.tbr-nav-item[data-target="' + panelId + '"]').addClass('active');
        if (history.replaceState) {
            history.replaceState(null, '', '#' + panelId);
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function riskBadge(level) {
        const map = { Critical: 'danger', High: 'warning', Moderate: 'info', Low: 'primary' };
        return '<span class="badge badge-' + (map[level] || 'secondary') + '">' + esc(level) + '</span>';
    }

    function prioBadge(level) {
        const map = { Critical: 'danger', High: 'warning', Medium: 'info', Low: 'secondary' };
        return '<span class="badge badge-' + (map[level] || 'secondary') + '">' + esc(level) + '</span>';
    }

    function renderFindings() {
        const sec4Body = $('#tbrSec04FindingsBody');
        const sec4Imm = $('#tbrSec04ImmediateBox');
        const sec2Body = $('#tbrSec02FindingsBody');
        const sec5Body = $('#tbrSec05FindingsBody');
        const sec6Body = $('#tbrSec06FindingsBody');

        const buckets = { sec_02: [], sec_04: [], sec_05: [], sec_06: [] };
        const immediateList = [];

        state.findings.forEach(function (f) {
            if (buckets[f.finding_section]) {
                buckets[f.finding_section].push(f);
            }
            if (f.finding_section === 'sec_04' && Number(f.finding_immediate_attention) === 1 && f.finding_status !== 'Resolved' && f.finding_status !== 'Mitigated') {
                immediateList.push(f);
            }
        });

        if (immediateList.length === 0) {
            sec4Imm.html('<span class="text-muted small">No open security concerns flagged for immediate attention.</span>');
        } else {
            sec4Imm.html(immediateList.map(function (f) {
                return '<div class="d-flex justify-content-between align-items-start border-bottom py-1">'
                    + '<div>' + riskBadge(f.finding_risk_level) + ' <strong>' + esc(f.finding_title) + '</strong>'
                    + (f.finding_recommended_action ? '<br><small class="text-muted">Action: ' + esc(f.finding_recommended_action) + '</small>' : '') + '</div>'
                    + '<span class="badge badge-light border">' + esc(f.finding_status) + '</span>'
                    + '</div>';
            }).join(''));
        }

        function buildFindingRows(list, emptyCols) {
            if (!list.length) {
                return '<tr><td colspan="' + emptyCols + '" class="text-center text-muted py-3">No findings documented yet.</td></tr>';
            }
            return list.map(function (f) {
                const visBadge = Number(f.finding_client_visible) === 0 ? ' <span class="badge badge-danger">Internal</span>' : '';
                const immBadge = Number(f.finding_immediate_attention) === 1 ? ' <span class="badge badge-warning">Immediate</span>' : '';
                const btns = canEdit
                    ? '<button type="button" class="btn btn-xs btn-outline-primary tbr-edit-finding" data-id="' + f.finding_id + '"><i class="fas fa-edit"></i></button> '
                    + '<button type="button" class="btn btn-xs btn-outline-danger tbr-del-finding" data-id="' + f.finding_id + '"><i class="fas fa-trash"></i></button>'
                    : '-';
                return '<tr>'
                    + '<td><strong>' + esc(f.finding_title) + '</strong>' + immBadge + visBadge + (f.finding_category ? '<br><small class="text-muted">' + esc(f.finding_category) + '</small>' : '') + '</td>'
                    + '<td>' + riskBadge(f.finding_risk_level) + '</td>'
                    + '<td>' + esc(f.finding_business_impact || '-') + '</td>'
                    + '<td>' + esc(f.finding_recommended_action || '-') + '</td>'
                    + '<td>' + esc(f.finding_responsible_party || '-') + (f.finding_target_date ? '<br><small>' + esc(f.finding_target_date) + '</small>' : '') + '</td>'
                    + '<td><span class="badge badge-light border">' + esc(f.finding_status) + '</span></td>'
                    + '<td class="text-right text-nowrap">' + btns + '</td>'
                    + '</tr>';
            }).join('');
        }

        sec4Body.html(buildFindingRows(buckets.sec_04, 7));
        sec2Body.html(buildFindingRows(buckets.sec_02, 7));
        sec5Body.html(buildFindingRows(buckets.sec_05, 7));
        sec6Body.html(buildFindingRows(buckets.sec_06, 7));
    }

    function renderRecommendations() {
        const body = $('#tbrRecommendationsBody');
        if (!state.recommendations.length) {
            body.html('<tr><td colspan="7" class="text-center text-muted py-3">No strategic recommendations added yet.</td></tr>');
            return;
        }
        const statusColors = { Approved: 'success', Proposed: 'primary', Declined: 'danger', Deferred: 'secondary', Completed: 'info' };
        body.html(state.recommendations.map(function (r) {
            const btns = canEdit
                ? '<button type="button" class="btn btn-xs btn-outline-success tbr-rec-roadmap" data-id="' + r.recommendation_id + '" title="Add to 12-Month Roadmap"><i class="fas fa-road"></i></button> '
                + '<button type="button" class="btn btn-xs btn-outline-primary tbr-edit-rec" data-id="' + r.recommendation_id + '"><i class="fas fa-edit"></i></button> '
                + '<button type="button" class="btn btn-xs btn-outline-danger tbr-del-rec" data-id="' + r.recommendation_id + '"><i class="fas fa-trash"></i></button>'
                : '-';
            return '<tr>'
                + '<td><strong>' + esc(r.recommendation_title) + '</strong>'
                + (r.recommendation_description ? '<br><small class="text-muted">' + esc(r.recommendation_description) + '</small>' : '')
                + (r.recommendation_justification ? '<br><small class="text-info">Justification: ' + esc(r.recommendation_justification) + '</small>' : '')
                + '</td>'
                + '<td>' + prioBadge(r.recommendation_priority) + '</td>'
                + '<td>' + fmtMoney(r.recommendation_estimated_cost) + '</td>'
                + '<td>' + esc(r.recommendation_timeline || '-') + '</td>'
                + '<td>' + esc(r.recommendation_responsible_party || '-') + '</td>'
                + '<td><span class="badge badge-' + (statusColors[r.recommendation_status] || 'secondary') + '">' + esc(r.recommendation_status) + '</span></td>'
                + '<td class="text-right text-nowrap">' + btns + '</td>'
                + '</tr>';
        }).join(''));
    }

    function renderRoadmap() {
        const items = state.roadmap.items || [];
        const totals = state.roadmap.totals || { quarters: {}, categories: {}, annual: 0 };

        ['Q1', 'Q2', 'Q3', 'Q4'].forEach(function (q) {
            $('#tbrRoadmapTotal_' + q).text(fmtMoney((totals.quarters && totals.quarters[q]) || 0));
        });
        $('#tbrRoadmapTotal_Annual').text(fmtMoney(totals.annual || 0));

        const catHtml = Object.keys(totals.categories || {}).map(function (c) {
            return '<div class="col-md-4 col-lg-2 mb-2"><div class="border rounded p-2 bg-light text-center">'
                + '<small class="text-muted d-block">' + esc(c) + '</small>'
                + '<strong>' + fmtMoney(totals.categories[c] || 0) + '</strong>'
                + '</div></div>';
        }).join('');
        $('#tbrRoadmapCategoriesRow').html(catHtml);

        const body = $('#tbrRoadmapBody');
        if (!items.length) {
            body.html('<tr><td colspan="7" class="text-center text-muted py-3">No budget/roadmap items added yet.</td></tr>');
            return;
        }
        body.html(items.map(function (rm) {
            const btns = canEdit
                ? '<button type="button" class="btn btn-xs btn-outline-primary tbr-edit-roadmap" data-id="' + rm.roadmap_id + '"><i class="fas fa-edit"></i></button> '
                + '<button type="button" class="btn btn-xs btn-outline-danger tbr-del-roadmap" data-id="' + rm.roadmap_id + '"><i class="fas fa-trash"></i></button>'
                : '-';
            return '<tr>'
                + '<td><span class="badge badge-dark">' + esc(rm.roadmap_quarter) + ' ' + esc(rm.roadmap_fiscal_year) + '</span></td>'
                + '<td><strong>' + esc(rm.roadmap_project_name) + '</strong>' + (rm.roadmap_description ? '<br><small class="text-muted">' + esc(rm.roadmap_description) + '</small>' : '') + '</td>'
                + '<td>' + esc(rm.roadmap_category) + '</td>'
                + '<td>' + prioBadge(rm.roadmap_priority) + '</td>'
                + '<td class="text-right font-weight-bold">' + fmtMoney(rm.roadmap_estimated_cost) + '</td>'
                + '<td><span class="badge badge-light border">' + esc(rm.roadmap_status) + '</span></td>'
                + '<td class="text-right text-nowrap">' + btns + '</td>'
                + '</tr>';
        }).join(''));
    }

    function renderActions() {
        const body = $('#tbrActionsBody');
        if (!state.actions.length) {
            body.html('<tr><td colspan="7" class="text-center text-muted py-3">No action items recorded yet.</td></tr>');
            return;
        }
        const statuses = ['Not Started', 'In Progress', 'Waiting on Client', 'Completed', 'Cancelled'];
        body.html(state.actions.map(function (a) {
            const owner = a.action_responsible_party === 'Client'
                ? (a.action_client_owner || 'Client')
                : (a.assigned_user_name || a.action_client_owner || 'Unassigned');

            let ticketCol = '';
            if (a.action_ticket_id) {
                ticketCol = '<a href="/agent/ticket.php?ticket_id=' + a.action_ticket_id + '" target="_blank" class="badge badge-success"><i class="fas fa-life-ring mr-1"></i>' + esc((a.ticket_prefix || '') + (a.ticket_number || a.action_ticket_id)) + '</a>';
            } else if (canEdit && cfg.canSupportWrite) {
                ticketCol = '<button type="button" class="btn btn-xs btn-outline-info tbr-create-ticket" data-id="' + a.action_id + '" title="Create ITFlow Ticket"><i class="fas fa-plus mr-1"></i>Ticket</button>';
            } else {
                ticketCol = '<span class="text-muted">-</span>';
            }

            let statusCell = '<span class="badge badge-light border">' + esc(a.action_status) + '</span>';
            if (canEdit) {
                statusCell = '<select class="form-control form-control-sm tbr-action-inline-status" data-id="' + a.action_id + '">'
                    + statuses.map(function (st) {
                        return '<option value="' + st + '"' + (a.action_status === st ? ' selected' : '') + '>' + st + '</option>';
                    }).join('') + '</select>';
            }

            const btns = canEdit
                ? '<button type="button" class="btn btn-xs btn-outline-primary tbr-edit-action" data-id="' + a.action_id + '"><i class="fas fa-edit"></i></button> '
                + '<button type="button" class="btn btn-xs btn-outline-danger tbr-del-action" data-id="' + a.action_id + '"><i class="fas fa-trash"></i></button>'
                : '-';

            const carried = a.action_carried_from_id ? ' <span class="badge badge-light border">Carried Forward</span>' : '';

            return '<tr>'
                + '<td>' + esc(a.action_description) + carried + '</td>'
                + '<td><span class="badge badge-light border">' + esc(a.action_responsible_party) + '</span><br><small>' + esc(owner) + '</small></td>'
                + '<td>' + prioBadge(a.action_priority) + '</td>'
                + '<td>' + esc(a.action_due_date || '-') + '</td>'
                + '<td>' + statusCell + '</td>'
                + '<td>' + ticketCol + '</td>'
                + '<td class="text-right text-nowrap">' + btns + '</td>'
                + '</tr>';
        }).join(''));
    }

    function renderLiveSummary(live) {
        if (!live) return;
        const hd = live.helpdesk || {};
        $('#live_hd_opened').text(hd.tickets_opened ?? 0);
        $('#live_hd_resolved').text(hd.tickets_resolved ?? 0);
        $('#live_hd_outstanding').text(hd.tickets_outstanding ?? 0);
        $('#live_hd_resp').text(hd.avg_response_minutes != null ? hd.avg_response_minutes + ' min' : 'N/A');
        $('#live_hd_res').text(hd.avg_resolution_minutes != null ? hd.avg_resolution_minutes + ' min' : 'N/A');
        $('#live_hd_sla').text(hd.sla_resolution_compliance_pct != null ? hd.sla_resolution_compliance_pct + '%' : 'N/A');
        if (toastr) {
            toastr.success('Synchronized live ITFlow metrics refreshed.');
        }
    }

    $(function () {
        renderFindings();
        renderRecommendations();
        renderRoadmap();
        renderActions();

        if (window.location.hash) {
            const hashTarget = window.location.hash.substring(1);
            if ($('#' + hashTarget).length) {
                switchPanel(hashTarget);
            }
        }

        $('.tbr-nav-item').on('click', function (e) {
            e.preventDefault();
            switchPanel($(this).data('target'));
        });

        $('.tbr-jump-panel').on('click', function (e) {
            e.preventDefault();
            switchPanel($(this).data('target'));
        });

        if (canEdit) {
            $(document).on('input', '.tbr-panel[data-section-key] input[type="text"], .tbr-panel[data-section-key] input[type="number"], .tbr-panel[data-section-key] textarea', function () {
                const secKey = $(this).closest('.tbr-panel').data('section-key');
                scheduleSectionSave(secKey, false);
            });

            $(document).on('change', '.tbr-panel[data-section-key] select, .tbr-panel[data-section-key] input[type="checkbox"], .tbr-panel[data-section-key] input[type="date"]', function () {
                const secKey = $(this).closest('.tbr-panel').data('section-key');
                scheduleSectionSave(secKey, true);
            });

            $(document).on('input', '#tbrExecutiveSummary, #tbrPrepNotes', function () {
                sendMetaSave(false);
            });

            $(document).on('change', '#tbrOverallHealth, #tbrPeriodStart, #tbrPeriodEnd, #tbrNextReviewDate, .tbr-attendee-attended', function () {
                sendMetaSave(true);
            });
        }

        $('#tbrConflictReloadBtn').on('click', function () {
            if (!state.conflictSection) return;
            const sec = state.conflictSection;
            $.getJSON('/agent/ajax_tbr.php', { action: 'get_section', tbr_id: tbrId, section_key: sec }, function (res) {
                if (res && res.ok) {
                    location.reload();
                }
            });
        });

        $('#tbrConflictForceBtn').on('click', function () {
            if (!state.conflictSection) return;
            const sec = state.conflictSection;
            $('#tbrConflictAlert').addClass('d-none');
            state.conflictSection = null;
            sendSectionSave(sec, true, 0);
        });

        window.addEventListener('online', function () {
            Object.keys(state.retryQueue).forEach(function (sec) {
                sendSectionSave(sec, false, 0);
            });
        });

        window.addEventListener('beforeunload', function (e) {
            if (Object.keys(state.dirtySections).length > 0 || state.dirtyMeta || Object.keys(state.retryQueue).length > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        $('.tbr-refresh-live-btn').on('click', function () {
            $.getJSON('/agent/ajax_tbr.php', {
                action: 'refresh_live_data',
                tbr_id: tbrId,
                period_start: $('#tbrPeriodStart').val(),
                period_end: $('#tbrPeriodEnd').val()
            }, function (res) {
                if (res && res.ok) {
                    renderLiveSummary(res.live_data);
                }
            });
        });

        $(document).on('click', '.tbr-open-finding-modal', function () {
            const sec = $(this).data('section') || 'sec_04';
            $('#tbrFindingForm')[0].reset();
            $('#tbrFindingId').val(0);
            $('#tbrFindingSection').val(sec);
            $('#tbrFindingVisible').prop('checked', true);
            $('#tbrFindingImmediate').prop('checked', false);
            $('#tbrFindingModal').modal('show');
        });

        $(document).on('click', '.tbr-edit-finding', function () {
            const id = Number($(this).data('id'));
            const f = state.findings.find(function (x) { return Number(x.finding_id) === id; });
            if (!f) return;
            $('#tbrFindingId').val(f.finding_id);
            $('#tbrFindingSection').val(f.finding_section);
            $('#tbrFindingTitle').val(f.finding_title);
            $('#tbrFindingCategory').val(f.finding_category || '');
            $('#tbrFindingRisk').val(f.finding_risk_level);
            $('#tbrFindingPriority').val(f.finding_priority);
            $('#tbrFindingStatus').val(f.finding_status);
            $('#tbrFindingDesc').val(f.finding_description || '');
            $('#tbrFindingImpact').val(f.finding_business_impact || '');
            $('#tbrFindingAction').val(f.finding_recommended_action || '');
            $('#tbrFindingParty').val(f.finding_responsible_party || '');
            $('#tbrFindingTargetDate').val(f.finding_target_date || '');
            $('#tbrFindingCost').val(f.finding_estimated_cost || '');
            $('#tbrFindingImmediate').prop('checked', Number(f.finding_immediate_attention) === 1);
            $('#tbrFindingVisible').prop('checked', Number(f.finding_client_visible) === 1);
            if ($('#tbrFindingInternalNotes').length) {
                $('#tbrFindingInternalNotes').val(f.finding_internal_notes || '');
            }
            $('#tbrFindingModal').modal('show');
        });

        $('#tbrFindingForm').on('submit', function (e) {
            e.preventDefault();
            $.post('/agent/ajax_tbr.php', $(this).serialize() + '&action=finding_save&tbr_id=' + tbrId + '&csrf_token=' + encodeURIComponent(csrf), function (res) {
                if (res && res.ok) {
                    state.findings = res.findings;
                    renderFindings();
                    $('#tbrFindingModal').modal('hide');
                    toastr.success('Finding saved.');
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save finding.');
            });
        });

        $(document).on('click', '.tbr-del-finding', function () {
            if (!confirm('Delete this finding?')) return;
            const id = $(this).data('id');
            $.post('/agent/ajax_tbr.php', { action: 'finding_delete', tbr_id: tbrId, finding_id: id, csrf_token: csrf }, function (res) {
                if (res && res.ok) {
                    state.findings = res.findings;
                    renderFindings();
                    toastr.info('Finding deleted.');
                }
            }, 'json');
        });

        $(document).on('click', '.tbr-open-rec-modal', function () {
            $('#tbrRecForm')[0].reset();
            $('#tbrRecId').val(0);
            $('#tbrRecModal').modal('show');
        });

        $(document).on('click', '.tbr-edit-rec', function () {
            const id = Number($(this).data('id'));
            const r = state.recommendations.find(function (x) { return Number(x.recommendation_id) === id; });
            if (!r) return;
            $('#tbrRecId').val(r.recommendation_id);
            $('#tbrRecTitle').val(r.recommendation_title);
            $('#tbrRecPriority').val(r.recommendation_priority);
            $('#tbrRecStatus').val(r.recommendation_status);
            $('#tbrRecCost').val(r.recommendation_estimated_cost || '');
            $('#tbrRecTimeline').val(r.recommendation_timeline || '');
            $('#tbrRecParty').val(r.recommendation_responsible_party || '');
            $('#tbrRecDesc').val(r.recommendation_description || '');
            $('#tbrRecJust').val(r.recommendation_justification || '');
            $('#tbrRecProblem').val(r.recommendation_problem || '');
            $('#tbrRecRisk').val(r.recommendation_risk_addressed || '');
            $('#tbrRecNotes').val(r.recommendation_notes || '');
            if ($('#tbrRecInternalNotes').length) {
                $('#tbrRecInternalNotes').val(r.recommendation_internal_notes || '');
            }
            $('#tbrRecModal').modal('show');
        });

        $('#tbrRecForm').on('submit', function (e) {
            e.preventDefault();
            $.post('/agent/ajax_tbr.php', $(this).serialize() + '&action=recommendation_save&tbr_id=' + tbrId + '&csrf_token=' + encodeURIComponent(csrf), function (res) {
                if (res && res.ok) {
                    state.recommendations = res.recommendations;
                    renderRecommendations();
                    $('#tbrRecModal').modal('hide');
                    toastr.success('Recommendation saved.');
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save recommendation.');
            });
        });

        $(document).on('click', '.tbr-del-rec', function () {
            if (!confirm('Delete this recommendation?')) return;
            $.post('/agent/ajax_tbr.php', { action: 'recommendation_delete', tbr_id: tbrId, recommendation_id: $(this).data('id'), csrf_token: csrf }, function (res) {
                if (res && res.ok) {
                    state.recommendations = res.recommendations;
                    renderRecommendations();
                    toastr.info('Recommendation deleted.');
                }
            }, 'json');
        });

        $(document).on('click', '.tbr-rec-roadmap', function () {
            const id = $(this).data('id');
            $.post('/agent/ajax_tbr.php', { action: 'recommendation_to_roadmap', tbr_id: tbrId, recommendation_id: id, csrf_token: csrf }, function (res) {
                if (res && res.ok) {
                    state.roadmap = res.roadmap;
                    renderRoadmap();
                    toastr.success('Added recommendation to 12-Month Roadmap.');
                }
            }, 'json');
        });

        $(document).on('click', '.tbr-open-roadmap-modal', function () {
            $('#tbrRoadmapForm')[0].reset();
            $('#tbrRoadmapId').val(0);
            $('#tbrRoadmapYear').val(new Date().getFullYear());
            $('#tbrRoadmapModal').modal('show');
        });

        $(document).on('click', '.tbr-edit-roadmap', function () {
            const id = Number($(this).data('id'));
            const rm = (state.roadmap.items || []).find(function (x) { return Number(x.roadmap_id) === id; });
            if (!rm) return;
            $('#tbrRoadmapId').val(rm.roadmap_id);
            $('#tbrRoadmapName').val(rm.roadmap_project_name);
            $('#tbrRoadmapDesc').val(rm.roadmap_description || '');
            $('#tbrRoadmapCategory').val(rm.roadmap_category);
            $('#tbrRoadmapQuarter').val(rm.roadmap_quarter);
            $('#tbrRoadmapYear').val(rm.roadmap_fiscal_year);
            $('#tbrRoadmapCost').val(rm.roadmap_estimated_cost);
            $('#tbrRoadmapPriority').val(rm.roadmap_priority);
            $('#tbrRoadmapStatus').val(rm.roadmap_status);
            $('#tbrRoadmapModal').modal('show');
        });

        $('#tbrRoadmapForm').on('submit', function (e) {
            e.preventDefault();
            $.post('/agent/ajax_tbr.php', $(this).serialize() + '&action=roadmap_save&tbr_id=' + tbrId + '&csrf_token=' + encodeURIComponent(csrf), function (res) {
                if (res && res.ok) {
                    state.roadmap = res.roadmap;
                    renderRoadmap();
                    $('#tbrRoadmapModal').modal('hide');
                    toastr.success('Roadmap item saved.');
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save roadmap item.');
            });
        });

        $(document).on('click', '.tbr-del-roadmap', function () {
            if (!confirm('Delete this roadmap item?')) return;
            $.post('/agent/ajax_tbr.php', { action: 'roadmap_delete', tbr_id: tbrId, roadmap_id: $(this).data('id'), csrf_token: csrf }, function (res) {
                if (res && res.ok) {
                    state.roadmap = res.roadmap;
                    renderRoadmap();
                    toastr.info('Roadmap item deleted.');
                }
            }, 'json');
        });

        $(document).on('click', '.tbr-open-action-modal', function () {
            $('#tbrActionForm')[0].reset();
            $('#tbrActionId').val(0);
            $('#tbrActionModal').modal('show');
        });

        $(document).on('click', '.tbr-edit-action', function () {
            const id = Number($(this).data('id'));
            const a = state.actions.find(function (x) { return Number(x.action_id) === id; });
            if (!a) return;
            $('#tbrActionId').val(a.action_id);
            $('#tbrActionDesc').val(a.action_description);
            $('#tbrActionParty').val(a.action_responsible_party);
            $('#tbrActionUser').val(a.action_assigned_user_id || '');
            $('#tbrActionClientOwner').val(a.action_client_owner || '');
            $('#tbrActionPriority').val(a.action_priority);
            $('#tbrActionDueDate').val(a.action_due_date || '');
            $('#tbrActionStatus').val(a.action_status);
            if ($('#tbrActionInternalNotes').length) {
                $('#tbrActionInternalNotes').val(a.action_internal_notes || '');
            }
            $('#tbrActionModal').modal('show');
        });

        $('#tbrActionForm').on('submit', function (e) {
            e.preventDefault();
            $.post('/agent/ajax_tbr.php', $(this).serialize() + '&action=action_save&tbr_id=' + tbrId + '&csrf_token=' + encodeURIComponent(csrf), function (res) {
                if (res && res.ok) {
                    state.actions = res.actions;
                    renderActions();
                    $('#tbrActionModal').modal('hide');
                    toastr.success('Action item saved.');
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save action item.');
            });
        });

        $(document).on('change', '.tbr-action-inline-status', function () {
            const id = $(this).data('id');
            const st = $(this).val();
            $.post('/agent/ajax_tbr.php', { action: 'action_save', tbr_id: tbrId, action_id: id, inline_status: st, csrf_token: csrf }, function (res) {
                if (res && res.ok) {
                    state.actions = res.actions;
                    renderActions();
                    toastr.success('Action status updated.');
                }
            }, 'json');
        });

        $(document).on('click', '.tbr-del-action', function () {
            if (!confirm('Delete this action item?')) return;
            $.post('/agent/ajax_tbr.php', { action: 'action_delete', tbr_id: tbrId, action_id: $(this).data('id'), csrf_token: csrf }, function (res) {
                if (res && res.ok) {
                    state.actions = res.actions;
                    renderActions();
                    toastr.info('Action item deleted.');
                }
            }, 'json');
        });

        $(document).on('click', '.tbr-create-ticket', function () {
            const btn = $(this);
            const id = btn.data('id');
            btn.prop('disabled', true);
            $.post('/agent/ajax_tbr.php', { action: 'action_create_ticket', tbr_id: tbrId, action_id: id, csrf_token: csrf }, function (res) {
                if (res && res.ok) {
                    state.actions = res.actions;
                    renderActions();
                    toastr.success('Created ITFlow Ticket ' + (res.ticket && res.ticket.ticket_ref ? res.ticket.ticket_ref : ''));
                }
            }, 'json').fail(function (xhr) {
                btn.prop('disabled', false);
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to create ticket.');
            });
        });

        $(document).on('click', '.tbr-carry-forward-btn', function () {
            const incActions = $(this).data('actions') ? 1 : 0;
            const incRecs = $(this).data('recs') ? 1 : 0;
            $.post('/agent/ajax_tbr.php', {
                action: 'carry_forward',
                tbr_id: tbrId,
                include_actions: incActions,
                include_recommendations: incRecs,
                csrf_token: csrf
            }, function (res) {
                if (res && res.ok) {
                    state.actions = res.actions;
                    state.recommendations = res.recommendations;
                    renderActions();
                    renderRecommendations();
                    toastr.success('Carried forward ' + res.result.actions_carried + ' action item(s) and ' + res.result.recommendations_carried + ' recommendation(s).');
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Carry forward failed.');
            });
        });

        function runCompleteValidation() {
            $.getJSON('/agent/ajax_tbr.php', {
                action: 'validate_completion',
                tbr_id: tbrId,
                next_review_date: $('#tbrCompleteNextDate').val() || ''
            }, function (res) {
                if (!res || !res.ok) return;
                const v = res.validation;
                let html = '';
                if (v.errors && v.errors.length) {
                    html += '<div class="alert alert-danger"><strong>Required before completion:</strong><ul class="mb-0 mt-1">'
                        + v.errors.map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('')
                        + '</ul></div>';
                } else {
                    html += '<div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i> All required sections and assignments are complete.</div>';
                }
                if (v.warnings && v.warnings.length) {
                    html += '<div class="alert alert-warning"><strong>Advisory Warnings:</strong><ul class="mb-0 mt-1">'
                        + v.warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('')
                        + '</ul></div>';
                }
                $('#tbrCompleteValidationBox').html(html);
                $('#tbrConfirmCompleteBtn').prop('disabled', !v.valid);
            });
        }

        $('#tbrOpenCompleteModalBtn').on('click', function () {
            flushAllPending();
            $('#tbrCompleteValidationBox').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Checking completion requirements...</div>');
            $('#tbrConfirmCompleteBtn').prop('disabled', true);
            $('#tbrCompleteNextDate').val($('#tbrNextReviewDate').val() || '');
            $('#tbrCompleteModal').modal('show');
            setTimeout(runCompleteValidation, 250);
        });

        $('#tbrCompleteNextDate').on('change input', function () {
            const val = $(this).val() || '';
            $('#tbrNextReviewDate').val(val);
            if (canEdit) {
                sendMetaSave(true);
            }
            runCompleteValidation();
        });

        $('#tbrConfirmCompleteBtn').on('click', function () {
            const btn = $(this);
            btn.prop('disabled', true);
            $.post('/agent/ajax_tbr.php', {
                action: 'complete_tbr',
                tbr_id: tbrId,
                tbr_next_review_date: $('#tbrCompleteNextDate').val(),
                approve_client_report: $('#tbrCompleteApprove').is(':checked') ? 1 : 0,
                schedule_next_tbr: $('#tbrCompleteScheduleNext').is(':checked') ? 1 : 0,
                csrf_token: csrf
            }, function (res) {
                if (res && res.ok) {
                    location.reload();
                }
            }, 'json').fail(function (xhr) {
                btn.prop('disabled', false);
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Could not complete TBR.');
            });
        });

        $('#tbrReopenForm').on('submit', function (e) {
            e.preventDefault();
            $.post('/agent/ajax_tbr.php', {
                action: 'reopen_tbr',
                tbr_id: tbrId,
                reopen_reason: $('#tbrReopenReason').val(),
                csrf_token: csrf
            }, function (res) {
                if (res && res.ok) {
                    location.reload();
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to reopen TBR.');
            });
        });

        $('#tbrApproveReportBtn').on('click', function () {
            const nextVal = Number($(this).data('approved')) === 1 ? 0 : 1;
            $.post('/agent/ajax_tbr.php', { action: 'approve_client_report', tbr_id: tbrId, approved: nextVal, csrf_token: csrf }, function (res) {
                if (res && res.ok) {
                    location.reload();
                }
            }, 'json');
        });

        $('#tbrEmailReportForm').on('submit', function (e) {
            e.preventDefault();
            const btn = $('#tbrSendReportEmailBtn');
            btn.prop('disabled', true);
            $.post('/agent/ajax_tbr.php', $(this).serialize() + '&action=email_report&tbr_id=' + tbrId + '&csrf_token=' + encodeURIComponent(csrf), function (res) {
                if (res && res.ok) {
                    toastr.success('Client report queued for delivery to ' + res.recipients.join(', '));
                    $('#tbrEmailReportModal').modal('hide');
                    setTimeout(function () { location.reload(); }, 900);
                }
            }, 'json').fail(function (xhr) {
                btn.prop('disabled', false);
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to email report.');
            });
        });
    });
})();
