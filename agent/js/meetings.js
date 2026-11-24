(function () {
    'use strict';

    const boot = window.MTG_CONFIG || {};
    if (!boot.seriesId || !boot.occurrenceId) {
        return;
    }

    const state = {
        seriesId: boot.seriesId,
        occurrenceId: boot.occurrenceId,
        csrf: boot.csrfToken,
        currentUserId: Number(boot.currentUserId),
        canEditAny: !!boot.canEditAny,
        syncSeq: Number(boot.initialState.occurrence.sync_seq || 1),
        metricPeriod: boot.initialState.metric_period,
        data: boot.initialState,
        showAllAgenda: true,
        selectedAgendaId: null,
        teamFilter: [],
        metricScopeFilter: 'All',
        actionFilter: 'Open',
        searchTerms: {},
        pendingWrites: {},
        timers: {},
        pollHandle: null,
        sortable: null
    };

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function formatResponseHtml(raw) {
        if (!raw) {
            return '';
        }
        const s = String(raw);
        if (/<\/?[a-z][\s\S]*>/i.test(s)) {
            return s;
        }
        return esc(s).replace(/\r?\n/g, '<br>');
    }

    function stripHtml(html) {
        return $('<div>').html(html || '').text();
    }

    function extractEditorHtml(el) {
        if (!el) {
            return '';
        }
        const plain = (el.textContent || '').replace(/\u00a0/g, ' ').trim();
        if (plain === '' && !el.querySelector('img, hr')) {
            return '';
        }
        return el.innerHTML;
    }

    function buildRteToolbarHtml() {
        const textColors = ['#1e293b', '#64748b', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0d9488', '#2563eb', '#7c3aed', '#db2777'];
        const hiliteColors = ['#fef08a', '#bbf7d0', '#bae6fd', '#fecdd3', '#e9d5ff', '#fed7aa', '#e2e8f0', '#f1f5f9'];

        const textSwatches = textColors.map(function (c) {
            return '<button type="button" class="mtg-rte-swatch" data-rte-cmd="foreColor" data-rte-val="' + c + '" style="background:' + c + ';" title="' + c + '"></button>';
        }).join('');

        const hiliteSwatches = hiliteColors.map(function (c) {
            return '<button type="button" class="mtg-rte-swatch" data-rte-cmd="hiliteColor" data-rte-val="' + c + '" style="background:' + c + ';" title="' + c + '"></button>';
        }).join('');

        return '<div class="mtg-rte-toolbar">'
            + '<div class="mtg-rte-dd">'
            + '<button type="button" class="mtg-rte-btn mtg-rte-btn-pill mtg-rte-dd-toggle" data-rte-dd="block" title="Text style"><span class="mtg-rte-block-label">Tt</span><i class="fas fa-chevron-down fa-xs"></i></button>'
            + '<div class="mtg-rte-menu">'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="formatBlock" data-rte-val="p">Normal text</button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="formatBlock" data-rte-val="h1"><strong>Heading 1</strong></button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="formatBlock" data-rte-val="h2"><strong>Heading 2</strong></button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="formatBlock" data-rte-val="h3"><strong>Heading 3</strong></button>'
            + '</div>'
            + '</div>'
            + '<span class="mtg-rte-sep"></span>'
            + '<button type="button" class="mtg-rte-btn" data-rte-cmd="bold" title="Bold"><i class="fas fa-bold"></i></button>'
            + '<button type="button" class="mtg-rte-btn" data-rte-cmd="italic" title="Italic"><i class="fas fa-italic"></i></button>'
            + '<button type="button" class="mtg-rte-btn" data-rte-cmd="underline" title="Underline"><i class="fas fa-underline"></i></button>'
            + '<div class="mtg-rte-dd">'
            + '<button type="button" class="mtg-rte-btn mtg-rte-dd-toggle" data-rte-dd="more" title="More formatting"><i class="fas fa-ellipsis-h"></i></button>'
            + '<div class="mtg-rte-menu">'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="strikeThrough"><i class="fas fa-strikethrough fa-fw"></i>Strikethrough</button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="inlineCode"><i class="fas fa-code fa-fw"></i>Inline code</button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="removeFormat"><i class="fas fa-eraser fa-fw"></i>Clear formatting</button>'
            + '</div>'
            + '</div>'
            + '<span class="mtg-rte-sep"></span>'
            + '<div class="mtg-rte-dd">'
            + '<button type="button" class="mtg-rte-btn mtg-rte-dd-toggle" data-rte-dd="color" title="Text color"><span style="font-weight:700;text-decoration:underline;text-decoration-color:#0d9488;text-decoration-thickness:2px;">A</span><i class="fas fa-chevron-down fa-xs"></i></button>'
            + '<div class="mtg-rte-menu">'
            + '<div class="mtg-rte-swatch-grid">' + textSwatches + '</div>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="foreColor" data-rte-val="inherit"><i class="fas fa-undo fa-fw"></i>Default color</button>'
            + '</div>'
            + '</div>'
            + '<div class="mtg-rte-dd">'
            + '<button type="button" class="mtg-rte-btn mtg-rte-dd-toggle" data-rte-dd="hilite" title="Highlight color"><i class="fas fa-highlighter"></i><i class="fas fa-chevron-down fa-xs"></i></button>'
            + '<div class="mtg-rte-menu">'
            + '<div class="mtg-rte-swatch-grid">' + hiliteSwatches + '</div>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="hiliteColor" data-rte-val="transparent"><i class="fas fa-ban fa-fw"></i>No highlight</button>'
            + '</div>'
            + '</div>'
            + '<span class="mtg-rte-sep"></span>'
            + '<div class="mtg-rte-dd">'
            + '<button type="button" class="mtg-rte-btn mtg-rte-dd-toggle" data-rte-dd="lists" title="Lists"><i class="fas fa-list-ul"></i><i class="fas fa-chevron-down fa-xs"></i></button>'
            + '<div class="mtg-rte-menu">'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="insertUnorderedList"><i class="fas fa-list-ul fa-fw"></i>Bulleted list</button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="insertOrderedList"><i class="fas fa-list-ol fa-fw"></i>Numbered list</button>'
            + '</div>'
            + '</div>'
            + '<span class="mtg-rte-sep"></span>'
            + '<button type="button" class="mtg-rte-btn" data-rte-action="link" title="Insert link"><i class="fas fa-link"></i></button>'
            + '<button type="button" class="mtg-rte-btn" data-rte-action="image" title="Insert image URL"><i class="far fa-image"></i></button>'
            + '<div class="mtg-rte-dd">'
            + '<button type="button" class="mtg-rte-btn mtg-rte-dd-toggle" data-rte-dd="insert" title="Insert"><i class="fas fa-plus"></i><i class="fas fa-chevron-down fa-xs"></i></button>'
            + '<div class="mtg-rte-menu">'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="formatBlock" data-rte-val="blockquote"><i class="fas fa-quote-left fa-fw"></i>Blockquote</button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="insertHorizontalRule"><i class="fas fa-minus fa-fw"></i>Horizontal rule</button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="insertHTML" data-rte-val="&#9744; "><i class="far fa-check-square fa-fw"></i>Checklist item</button>'
            + '</div>'
            + '</div>'
            + '<span class="mtg-rte-sep"></span>'
            + '<div class="mtg-rte-dd">'
            + '<button type="button" class="mtg-rte-btn mtg-rte-dd-toggle" data-rte-dd="align" title="Alignment"><i class="fas fa-align-left"></i><i class="fas fa-chevron-down fa-xs"></i></button>'
            + '<div class="mtg-rte-menu">'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="justifyLeft"><i class="fas fa-align-left fa-fw"></i>Align left</button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="justifyCenter"><i class="fas fa-align-center fa-fw"></i>Align center</button>'
            + '<button type="button" class="mtg-rte-menu-item" data-rte-cmd="justifyRight"><i class="fas fa-align-right fa-fw"></i>Align right</button>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function setSavePill(mode, label) {
        const el = $('#mtgSyncBadge');
        if (mode === 'saving') {
            el.css({ background: '#dbeafe', color: '#1e40af' }).html('<i class="fas fa-spinner fa-spin"></i> ' + esc(label || 'Saving...'));
        } else if (mode === 'error') {
            el.css({ background: '#fee2e2', color: '#991b1b' }).html('<i class="fas fa-exclamation-triangle"></i> ' + esc(label || 'Sync Error'));
        } else {
            el.css({ background: '#dcfce7', color: '#166534' }).html('<span class="mtg-live-dot"></span> ' + esc(label || 'Live'));
        }
    }

    function avatarHtml(u, onlineIds) {
        const isOnline = (onlineIds || []).indexOf(Number(u.user_id)) !== -1 || u.is_online;
        const dot = isOnline ? '<span class="mtg-online-dot" title="Online in meeting"></span>' : '';
        let inner = '';
        if (u.avatar_url) {
            inner = '<img src="' + esc(u.avatar_url) + '" alt="' + esc(u.user_name) + '" class="mtg-avatar-img">';
        } else {
            inner = '<span class="mtg-avatar-initials" style="background-color:' + esc(u.color || '#0f766e') + ';">' + esc(u.initials || 'U') + '</span>';
        }
        return '<div class="mtg-avatar-wrap" title="' + esc(u.user_name) + '">' + inner + dot + '</div>';
    }

    function autoGrow(el) {
        el.style.height = 'auto';
        el.style.height = Math.max(24, el.scrollHeight) + 'px';
    }

    function fmtMetricVal(val, unit) {
        if (val === null || val === undefined || val === '') {
            return '-';
        }
        const num = Number(val);
        if (!isFinite(num)) {
            return '-';
        }
        if (unit === 'Currency') {
            return '$' + num.toLocaleString(undefined, { maximumFractionDigits: 2 });
        }
        if (unit === 'Percent') {
            return num.toLocaleString(undefined, { maximumFractionDigits: 2 }) + '%';
        }
        return num.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    function quarterKeyFromMonth(periodStr) {
        const parts = String(periodStr || '').split('-');
        const y = Number(parts[0]) || new Date().getFullYear();
        const m = Number(parts[1]) || (new Date().getMonth() + 1);
        const q = Math.floor((m - 1) / 3) + 1;
        return y + '-Q' + q;
    }

    function stepMetricMonth(periodStr, delta) {
        const parts = periodStr.split('-');
        const d = new Date(Number(parts[0]), Number(parts[1]) - 1 + delta, 1);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        return y + '-' + m;
    }

    function renderAgendaSidebar() {
        const listEl = $('#mtgAgendaList');
        const items = state.data.agenda_items || [];
        listEl.html(items.map(function (ag) {
            const activeCls = (!state.showAllAgenda && Number(state.selectedAgendaId) === Number(ag.agenda_item_id)) ? 'active' : '';
            const controls = state.canEditAny
                ? '<span class="text-nowrap ml-1">'
                + '<i class="fas fa-pencil-alt text-muted mr-2 mtg-edit-agenda" data-id="' + ag.agenda_item_id + '" title="Rename / Edit"></i>'
                + '<i class="fas fa-times text-muted mtg-del-agenda" data-id="' + ag.agenda_item_id + '" title="Remove"></i>'
                + '</span>'
                : '';
            const handle = state.canEditAny ? '<span class="mtg-drag-handle">=</span>' : '';
            return '<div class="mtg-agenda-row ' + activeCls + '" data-id="' + ag.agenda_item_id + '">'
                + '<div class="text-truncate">' + handle + '<i class="' + esc(ag.icon) + ' text-secondary mr-1"></i> ' + esc(ag.title) + '</div>'
                + controls
                + '</div>';
        }).join(''));

        if (state.canEditAny && typeof Sortable !== 'undefined' && listEl.length) {
            if (state.sortable) {
                state.sortable.destroy();
            }
            state.sortable = new Sortable(listEl[0], {
                handle: '.mtg-drag-handle',
                animation: 150,
                onEnd: function () {
                    const order = [];
                    listEl.find('.mtg-agenda-row').each(function () {
                        order.push($(this).data('id'));
                    });
                    $.post('/agent/ajax_meetings.php', {
                        action: 'agenda_reorder',
                        series_id: state.seriesId,
                        occurrence_id: state.occurrenceId,
                        order: order,
                        csrf_token: state.csrf
                    }, function () {
                        pollNow(true);
                    }, 'json');
                }
            });
        }
    }

    function renderAttendeesSidebar() {
        const online = state.data.online_users || [];
        const list = state.data.attendees || [];
        $('#mtgAttendeesCount').text(list.length);
        $('#mtgAttendeesList').html(list.map(function (u) {
            const remBtn = (state.canEditAny && u.role !== 'Facilitator')
                ? '<button type="button" class="btn btn-xs btn-link text-danger mtg-remove-attendee" data-user-id="' + u.user_id + '" title="Remove"><i class="fas fa-times"></i></button>'
                : '';
            return '<div class="d-flex align-items-center justify-content-between py-1">'
                + '<div class="d-flex align-items-center" style="gap:0.5rem;">'
                + avatarHtml(u, online)
                + '<div><div class="small font-weight-bold">' + esc(u.user_name) + '</div><div class="text-muted" style="font-size:11px;">' + esc(u.role) + '</div></div>'
                + '</div>'
                + remBtn
                + '</div>';
        }).join(''));
    }

    function renderSidebarActionItems() {
        const items = (state.data.action_items || []).filter(function (a) { return a.status !== 'Completed'; });
        $('#mtgSidebarActionsCount').text(items.length);
        if (!items.length) {
            $('#mtgSidebarActionsList').html('<div class="small text-muted py-1">No open action items.</div>');
            return;
        }
        $('#mtgSidebarActionsList').html(items.slice(0, 8).map(function (a) {
            return '<div class="d-flex align-items-start py-1 border-bottom small">'
                + '<input type="checkbox" class="mr-2 mt-1 mtg-toggle-action" data-id="' + a.action_id + '" ' + (a.can_edit ? '' : 'disabled') + '>'
                + '<div class="flex-grow-1">'
                + '<div>' + esc(a.description) + '</div>'
                + '<div class="text-muted" style="font-size:11px;">' + esc(a.owner_name) + (a.due_date ? ' &middot; Due ' + esc(a.due_date) : '') + '</div>'
                + '</div>'
                + '</div>';
        }).join(''));
    }

    function renderAttachmentsSidebar() {
        const list = state.data.attachments || [];
        $('#mtgAttachmentsCount').text(list.length);
        if (!list.length) {
            $('#mtgAttachmentsList').html('<div class="small text-muted">No attachments yet.</div>');
            return;
        }
        $('#mtgAttachmentsList').html(list.map(function (at) {
            const href = at.has_file
                ? '/agent/meeting_attachment.php?attachment_id=' + at.attachment_id
                : at.external_url;
            const delBtn = at.can_delete
                ? '<button type="button" class="btn btn-xs btn-link text-danger p-0 ml-2 mtg-del-attachment" data-id="' + at.attachment_id + '"><i class="fas fa-times"></i></button>'
                : '';
            return '<div class="d-flex justify-content-between align-items-center py-1 border-bottom small">'
                + '<a href="' + esc(href) + '" target="_blank" class="text-truncate"><i class="fas fa-' + (at.has_file ? 'file' : 'link') + ' mr-1"></i>' + esc(at.title) + '</a>'
                + delBtn
                + '</div>';
        }).join(''));
    }

    function renderNotesBox() {
        const occ = state.data.occurrence || {};
        const sharedEl = $('#mtgSharedNotesInput');
        if (document.activeElement !== sharedEl[0] && !state.pendingWrites['shared_notes']) {
            if (sharedEl.val() !== (occ.shared_notes || '')) {
                sharedEl.val(occ.shared_notes || '');
                $('#mtgSharedNotesFull').val(occ.shared_notes || '');
            }
        }
    }

    function buildPromptCardHtml(ag) {
        const aid = ag.agenda_item_id;
        const term = (state.searchTerms[aid] || '').toLowerCase();
        const attendees = (state.data.attendees || []).filter(function (u) {
            if (state.teamFilter.length && state.teamFilter.indexOf(String(u.user_id)) === -1) {
                return false;
            }
            return true;
        });
        const online = state.data.online_users || [];
        const respMap = (state.data.responses && state.data.responses[String(aid)]) || {};

        let rowsHtml = '';
        attendees.forEach(function (u) {
            const r = respMap[String(u.user_id)] || { content: '' };
            const txt = r.content || '';
            const plainTxt = stripHtml(txt).toLowerCase();
            if (term && plainTxt.indexOf(term) === -1 && u.user_name.toLowerCase().indexOf(term) === -1) {
                return;
            }
            const isMine = Number(u.user_id) === state.currentUserId;
            const editable = isMine || state.canEditAny;
            const key = aid + '_' + u.user_id;

            let toolbarHtml = '';
            let bodyHtml = '';
            if (editable) {
                toolbarHtml = buildRteToolbarHtml();
                bodyHtml = '<div class="mtg-bubble-editor" contenteditable="true" data-agenda-id="' + aid + '" data-user-id="' + u.user_id + '" data-resp-key="' + key + '" data-synced-raw="' + esc(txt) + '" data-placeholder="' + (isMine ? 'Type your update...' : 'Facilitator / Admin edit...') + '">' + formatResponseHtml(txt) + '</div><div class="mtg-rte-link-bar"></div>';
            } else {
                bodyHtml = '<div class="mtg-bubble-readonly" data-resp-key="' + key + '" data-user-name="' + esc(u.user_name) + '" data-raw-content="' + esc(txt) + '">' + (txt ? formatResponseHtml(txt) : '<span class="text-muted small">Waiting for ' + esc(u.user_name) + '...</span>') + '</div>';
            }

            rowsHtml += '<div class="mtg-bubble-row">'
                + avatarHtml(u, online)
                + '<div class="mtg-bubble ' + (isMine ? 'mtg-bubble-mine' : '') + '" data-bubble-key="' + key + '">'
                + toolbarHtml
                + '<div class="mtg-bubble-author"><span>' + esc(u.user_name) + (isMine ? ' (You)' : '') + '</span><span>' + (r.updated_at ? esc(r.updated_at.substring(11, 16)) : '') + '</span></div>'
                + bodyHtml
                + '</div>'
                + '</div>';
        });

        if (!rowsHtml) {
            rowsHtml = '<div class="text-muted small py-2">No matching team member entries.</div>';
        }

        return '<div class="mtg-card" data-card-agenda-id="' + aid + '">'
            + '<div class="mtg-card-header">'
            + '<h5 class="mb-0 font-weight-bold"><i class="' + esc(ag.icon) + ' text-secondary mr-2"></i>' + esc(ag.title) + '</h5>'
            + '<button type="button" class="btn btn-xs btn-link text-muted mtg-force-sync-btn" title="Refresh"><i class="fas fa-sync-alt"></i></button>'
            + '</div>'
            + '<div class="p-3">'
            + '<div class="mb-3"><input type="search" class="form-control form-control-sm mtg-card-search" data-agenda-id="' + aid + '" value="' + esc(state.searchTerms[aid] || '') + '" placeholder="Search"></div>'
            + '<div class="mtg-bubbles-list">' + rowsHtml + '</div>'
            + '</div>'
            + '</div>';
    }

    function buildMetricsCardHtml(ag) {
        const aid = ag.agenda_item_id;
        const term = (state.searchTerms[aid] || '').toLowerCase();
        const online = state.data.online_users || [];
        const metrics = (state.data.metrics || []).filter(function (m) {
            if (state.teamFilter.length && state.teamFilter.indexOf(String(m.owner_user_id)) === -1) {
                return false;
            }
            if (state.metricScopeFilter === 'Attendees' && m.scope !== 'Attendee') {
                return false;
            }
            if (state.metricScopeFilter === 'Company' && m.scope !== 'Company') {
                return false;
            }
            if (term && m.metric_name.toLowerCase().indexOf(term) === -1 && m.owner_name.toLowerCase().indexOf(term) === -1) {
                return false;
            }
            return true;
        });

        const rowsHtml = metrics.length ? metrics.map(function (m) {
            const ownerObj = {
                user_id: m.owner_user_id,
                user_name: m.owner_name,
                avatar_url: m.owner_avatar_url,
                initials: m.owner_initials,
                color: m.owner_color
            };
            const starCls = m.is_starred ? 'fas fa-star mtg-star-btn starred' : 'far fa-star mtg-star-btn';
            const metaParts = [m.owner_name, m.scope, m.metric_type || 'Count'];
            if (m.quarter_label) {
                metaParts.push(m.quarter_label);
            }
            return '<tr data-metric-id="' + m.metric_id + '">'
                + '<td style="width:30px;"><span class="mtg-health-square mtg-health-' + esc(m.status_color) + ' ' + (m.can_edit ? 'mtg-cycle-metric-color' : '') + '" data-id="' + m.metric_id + '" title="Click to toggle status"></span></td>'
                + '<td style="width:28px;"><i class="' + starCls + ' ' + (m.can_edit ? 'mtg-toggle-metric-star' : '') + '" data-id="' + m.metric_id + '"></i></td>'
                + '<td style="width:44px;">' + avatarHtml(ownerObj, online) + '</td>'
                + '<td><strong>' + esc(m.metric_name) + '</strong><br><small class="text-muted">' + esc(metaParts.join(' \u00B7 ')) + '</small></td>'
                + '<td class="text-right text-nowrap"><a href="#" class="text-reset mtg-open-metric-table" data-id="' + m.metric_id + '" title="Update interval &amp; quarterly table"><strong>' + fmtMetricVal(m.actual_value, m.unit) + '</strong> / ' + fmtMetricVal(m.period_target, m.unit) + '</a></td>'
                + '<td class="text-right text-nowrap"><strong>' + fmtMetricVal(m.to_date_actual, m.unit) + '</strong> / ' + fmtMetricVal(m.to_date_target, m.unit) + '</td>'
                + '<td class="text-right text-nowrap">' + fmtMetricVal(m.target_pace, m.unit) + '</td>'
                + '<td class="text-right text-nowrap" style="width:98px;">'
                + '<button type="button" class="btn btn-xs btn-outline-success mtg-open-metric-table mr-1" data-id="' + m.metric_id + '" title="Sales &amp; Quarterly Targets Table"><i class="fas fa-table"></i></button>'
                + (m.can_edit ? '<button type="button" class="btn btn-xs btn-outline-primary mtg-edit-metric mr-1" data-id="' + m.metric_id + '" title="Metric Properties"><i class="fas fa-sliders-h"></i></button>' : '')
                + '<a href="/agent/metrics.php?metric_id=' + m.metric_id + '" target="_blank" class="btn btn-xs btn-outline-secondary" title="Open Full Metrics Page"><i class="fas fa-external-link-alt"></i></a>'
                + '</td>'
                + '</tr>';
        }).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">No KPI metrics added yet. Click "+ Add Metric" to track manual KPIs.</td></tr>';

        return '<div class="mtg-card" data-card-agenda-id="' + aid + '">'
            + '<div class="mtg-card-header flex-wrap">'
            + '<h5 class="mb-0 font-weight-bold"><i class="' + esc(ag.icon) + ' text-secondary mr-2"></i>' + esc(ag.title) + '</h5>'
            + '<div class="d-flex align-items-center" style="gap:0.65rem;">'
            + '<div class="btn-group btn-group-xs">'
            + '<button type="button" class="btn btn-' + (state.metricScopeFilter === 'All' ? 'dark' : 'outline-secondary') + ' mtg-metric-scope-btn" data-scope="All">All</button>'
            + '<button type="button" class="btn btn-' + (state.metricScopeFilter === 'Attendees' ? 'dark' : 'outline-secondary') + ' mtg-metric-scope-btn" data-scope="Attendees">Attendees Only</button>'
            + '<button type="button" class="btn btn-' + (state.metricScopeFilter === 'Company' ? 'dark' : 'outline-secondary') + ' mtg-metric-scope-btn" data-scope="Company">Company Only</button>'
            + '</div>'
            + '<a href="/agent/metrics.php" target="_blank" class="btn btn-xs btn-outline-secondary"><i class="fas fa-chart-line mr-1"></i>Metrics</a>'
            + '<button type="button" class="btn btn-xs btn-outline-primary mtg-open-metric-modal"><i class="fas fa-plus mr-1"></i>Add Metric</button>'
            + '<button type="button" class="btn btn-xs btn-link text-muted mtg-force-sync-btn"><i class="fas fa-sync-alt"></i></button>'
            + '</div>'
            + '</div>'
            + '<div class="p-3">'
            + '<div class="mb-2"><input type="search" class="form-control form-control-sm mtg-card-search" data-agenda-id="' + aid + '" value="' + esc(state.searchTerms[aid] || '') + '" placeholder="Search metrics..."></div>'
            + '<div class="table-responsive"><table class="table table-sm table-hover mb-0">'
            + '<thead><tr class="small text-muted">'
            + '<th></th><th></th><th></th><th>Metric</th>'
            + '<th class="text-right text-nowrap"><a href="#" class="mtg-metric-period-step" data-delta="-1">&lt;</a> ' + esc(state.data.metric_period_display) + ' <a href="#" class="mtg-metric-period-step" data-delta="1">&gt;</a></th>'
            + '<th class="text-right text-nowrap" title="Quarter to-date actual vs full quarter goal">Quarter To Date</th><th class="text-right text-nowrap" title="Auto-calculated work-day pace for the quarter">Target Pace</th><th></th>'
            + '</tr></thead>'
            + '<tbody>' + rowsHtml + '</tbody>'
            + '</table></div>'
            + '</div>'
            + '</div>';
    }

    function buildActionItemsCardHtml(ag) {
        const aid = ag.agenda_item_id;
        const term = (state.searchTerms[aid] || '').toLowerCase();
        const online = state.data.online_users || [];
        const todayStr = new Date().toISOString().slice(0, 10);

        const items = (state.data.action_items || []).filter(function (a) {
            if (state.teamFilter.length && state.teamFilter.indexOf(String(a.owner_user_id)) === -1) {
                return false;
            }
            if (state.actionFilter === 'Open' && a.status === 'Completed') {
                return false;
            }
            if (term && a.description.toLowerCase().indexOf(term) === -1 && a.owner_name.toLowerCase().indexOf(term) === -1) {
                return false;
            }
            return true;
        });

        const rowsHtml = items.length ? items.map(function (a) {
            const ownerObj = {
                user_id: a.owner_user_id,
                user_name: a.owner_name,
                avatar_url: a.owner_avatar_url,
                initials: a.owner_initials,
                color: a.owner_color
            };
            const isOverdue = a.due_date && a.status !== 'Completed' && a.due_date < todayStr;
            const prioCls = a.priority === 'High' ? 'danger' : (a.priority === 'Medium' ? 'info' : 'secondary');
            return '<tr>'
                + '<td style="width:28px;"><input type="checkbox" class="mtg-toggle-action" data-id="' + a.action_id + '" ' + (a.status === 'Completed' ? 'checked' : '') + ' ' + (a.can_edit ? '' : 'disabled') + '></td>'
                + '<td style="width:42px;">' + avatarHtml(ownerObj, online) + '</td>'
                + '<td class="' + (a.status === 'Completed' ? 'text-muted' : '') + '">'
                + (a.status === 'Completed' ? '<del>' + esc(a.description) + '</del>' : '<strong>' + esc(a.description) + '</strong>')
                + '<br><small class="text-muted">' + esc(a.owner_name) + '</small>'
                + '</td>'
                + '<td><span class="badge badge-' + prioCls + '">' + esc(a.priority) + '</span></td>'
                + '<td class="' + (isOverdue ? 'text-danger font-weight-bold' : '') + '">' + esc(a.due_date || '-') + '</td>'
                + '<td class="text-right text-nowrap">'
                + (a.can_edit ? '<button type="button" class="btn btn-xs btn-outline-primary mtg-edit-action" data-id="' + a.action_id + '"><i class="fas fa-edit"></i></button> <button type="button" class="btn btn-xs btn-outline-danger mtg-del-action" data-id="' + a.action_id + '"><i class="fas fa-trash"></i></button>' : '')
                + '</td>'
                + '</tr>';
        }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">No action items to display.</td></tr>';

        return '<div class="mtg-card" data-card-agenda-id="' + aid + '">'
            + '<div class="mtg-card-header flex-wrap">'
            + '<h5 class="mb-0 font-weight-bold"><i class="' + esc(ag.icon) + ' text-secondary mr-2"></i>' + esc(ag.title) + '</h5>'
            + '<div class="d-flex align-items-center" style="gap:0.5rem;">'
            + '<div class="btn-group btn-group-xs">'
            + '<button type="button" class="btn btn-' + (state.actionFilter === 'Open' ? 'dark' : 'outline-secondary') + ' mtg-action-filter-btn" data-filter="Open">Open</button>'
            + '<button type="button" class="btn btn-' + (state.actionFilter === 'All' ? 'dark' : 'outline-secondary') + ' mtg-action-filter-btn" data-filter="All">All</button>'
            + '</div>'
            + '<button type="button" class="btn btn-xs btn-outline-primary mtg-open-action-modal"><i class="fas fa-plus mr-1"></i>Add Action Item</button>'
            + '<button type="button" class="btn btn-xs btn-link text-muted mtg-force-sync-btn"><i class="fas fa-sync-alt"></i></button>'
            + '</div>'
            + '</div>'
            + '<div class="p-3">'
            + '<div class="mb-2"><input type="search" class="form-control form-control-sm mtg-card-search" data-agenda-id="' + aid + '" value="' + esc(state.searchTerms[aid] || '') + '" placeholder="Search action items..."></div>'
            + '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><tbody>' + rowsHtml + '</tbody></table></div>'
            + '</div>'
            + '</div>';
    }

    function renderWorkspaceCards(fullRebuild) {
        const container = $('#meetingCardsContainer');
        const activeEl = document.activeElement;
        const activeRespKey = activeEl && activeEl.dataset ? activeEl.dataset.respKey : null;
        const activeSearchAid = activeEl && $(activeEl).hasClass('mtg-card-search') ? $(activeEl).data('agenda-id') : null;

        if (!fullRebuild && activeRespKey) {
            patchResponsesInPlace();
            return;
        }

        const items = (state.data.agenda_items || []).filter(function (ag) {
            return state.showAllAgenda || !state.selectedAgendaId || Number(ag.agenda_item_id) === Number(state.selectedAgendaId);
        });

        let html = '';
        items.forEach(function (ag) {
            if (ag.item_type === 'metrics') {
                html += buildMetricsCardHtml(ag);
            } else if (ag.item_type === 'action_items') {
                html += buildActionItemsCardHtml(ag);
            } else {
                html += buildPromptCardHtml(ag);
            }
        });

        container.html(html);

        if (activeSearchAid) {
            const sEl = container.find('.mtg-card-search[data-agenda-id="' + activeSearchAid + '"]');
            if (sEl.length) {
                sEl.focus();
                const v = sEl.val();
                sEl[0].setSelectionRange(v.length, v.length);
            }
        }
    }

    function patchResponsesInPlace() {
        const respMap = state.data.responses || {};
        const online = state.data.online_users || [];

        (state.data.attendees || []).forEach(function (u) {
            const isOnline = online.indexOf(Number(u.user_id)) !== -1;
            $('[data-bubble-key$="_' + u.user_id + '"]').closest('.mtg-bubble-row').find('.mtg-avatar-wrap').replaceWith(avatarHtml(u, online));
            if (isOnline) {
                $('.mtg-avatar-wrap[title="' + esc(u.user_name) + '"]').find('.mtg-online-dot').show();
            }
        });

        Object.keys(respMap).forEach(function (aid) {
            Object.keys(respMap[aid]).forEach(function (uid) {
                const key = aid + '_' + uid;
                if (state.pendingWrites[key]) {
                    return;
                }
                const el = $('[data-resp-key="' + key + '"]');
                if (!el.length || el.closest('.mtg-bubble').hasClass('is-focused') || document.activeElement === el[0] || el[0].contains(document.activeElement)) {
                    return;
                }
                const newVal = respMap[aid][uid].content || '';
                if (el.hasClass('mtg-bubble-editor')) {
                    if ((el.attr('data-synced-raw') || '') !== newVal) {
                        el.attr('data-synced-raw', newVal).html(formatResponseHtml(newVal));
                        el.closest('.mtg-bubble').addClass('mtg-flash');
                        setTimeout(function () { el.closest('.mtg-bubble').removeClass('mtg-flash'); }, 900);
                    }
                } else {
                    if ((el.attr('data-raw-content') || '') !== newVal) {
                        const waitName = el.data('user-name') || 'user';
                        el.attr('data-raw-content', newVal).html(newVal ? formatResponseHtml(newVal) : '<span class="text-muted small">Waiting for ' + esc(waitName) + '...</span>');
                        el.closest('.mtg-bubble').addClass('mtg-flash');
                        setTimeout(function () { el.closest('.mtg-bubble').removeClass('mtg-flash'); }, 900);
                    }
                }
            });
        });
    }

    function applyFullState(newState, forceRebuild) {
        const prevAgendaJson = JSON.stringify((state.data && state.data.agenda_items) || []);
        const prevAttJson = JSON.stringify((state.data && state.data.attendees) || []);
        const prevMetricsJson = JSON.stringify((state.data && state.data.metrics) || []);
        const prevActionsJson = JSON.stringify((state.data && state.data.action_items) || []);

        state.data = newState;
        state.syncSeq = Number(newState.occurrence.sync_seq || state.syncSeq);

        renderAgendaSidebar();
        renderAttendeesSidebar();
        renderSidebarActionItems();
        renderAttachmentsSidebar();
        renderNotesBox();

        const structuralChanged = forceRebuild
            || prevAgendaJson !== JSON.stringify(newState.agenda_items || [])
            || prevAttJson !== JSON.stringify(newState.attendees || [])
            || prevMetricsJson !== JSON.stringify(newState.metrics || [])
            || prevActionsJson !== JSON.stringify(newState.action_items || []);

        if (structuralChanged) {
            renderWorkspaceCards(true);
        } else {
            patchResponsesInPlace();
        }
    }

    function pollNow(force) {
        $.getJSON('/agent/ajax_meetings.php', {
            action: 'sync',
            series_id: state.seriesId,
            occurrence_id: state.occurrenceId,
            since_seq: force ? -1 : state.syncSeq,
            metric_period: state.metricPeriod,
            active_item_id: state.selectedAgendaId || ''
        }, function (res) {
            if (!res || !res.ok) return;
            if (res.changed && res.state) {
                applyFullState(res.state, !!force);
            } else if (res.online_users) {
                state.data.online_users = res.online_users;
                renderAttendeesSidebar();
            }
            if (Object.keys(state.pendingWrites).length === 0) {
                setSavePill('live', 'Live');
            }
        }).fail(function () {
            setSavePill('error', 'Reconnecting...');
        });
    }

    function startPolling() {
        clearInterval(state.pollHandle);
        const intervalMs = document.hidden ? 10000 : 2000;
        state.pollHandle = setInterval(function () {
            pollNow(false);
        }, intervalMs);
    }

    $(function () {
        applyFullState(state.data, true);
        startPolling();

        document.addEventListener('visibilitychange', function () {
            startPolling();
            if (!document.hidden) {
                pollNow(false);
            }
        });

        $('.mtg-icon-strip a').on('click', function (e) {
            e.preventDefault();
            const target = $(this).data('section');
            const body = $('#mtgSec_' + target);
            if (body.length) {
                body.collapse('show');
                body[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });

        $('#mtgShowAllToggle').on('change', function () {
            state.showAllAgenda = $(this).is(':checked');
            renderAgendaSidebar();
            renderWorkspaceCards(true);
        });

        $(document).on('click', '.mtg-agenda-row', function (e) {
            if ($(e.target).closest('.mtg-edit-agenda, .mtg-del-agenda, .mtg-drag-handle').length) {
                return;
            }
            const aid = Number($(this).data('id'));
            state.selectedAgendaId = aid;
            if (state.showAllAgenda) {
                const card = $('.mtg-card[data-card-agenda-id="' + aid + '"]');
                if (card.length) {
                    card[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                renderAgendaSidebar();
                renderWorkspaceCards(true);
            }
        });

        $('#mtgTeamFilter').on('change', function () {
            state.teamFilter = $(this).val() || [];
            renderWorkspaceCards(true);
        });

        $('#mtgDateOverrideChk, #mtgPeriodSelect').on('change', function () {
            $.post('/agent/ajax_meetings.php', {
                action: 'update_occurrence_meta',
                series_id: state.seriesId,
                occurrence_id: state.occurrenceId,
                date_override: $('#mtgDateOverrideChk').is(':checked') ? 1 : 0,
                period_label: $('#mtgPeriodSelect').val(),
                csrf_token: state.csrf
            }, function (res) {
                if (res && res.ok) {
                    state.syncSeq = res.sync_seq;
                }
            }, 'json');
        });

        function saveCurrentRange(editorEl) {
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) {
                return null;
            }
            const range = sel.getRangeAt(0);
            if (!editorEl.contains(range.commonAncestorContainer)) {
                return null;
            }
            return range.cloneRange();
        }

        function restoreRange(editorEl, range) {
            editorEl.focus();
            const sel = window.getSelection();
            if (!sel) {
                return;
            }
            sel.removeAllRanges();
            if (range) {
                sel.addRange(range);
            } else {
                const r = document.createRange();
                r.selectNodeContents(editorEl);
                r.collapse(false);
                sel.addRange(r);
            }
        }

        function findActiveLink(bubbleEl) {
            const editorEl = $(bubbleEl).find('.mtg-bubble-editor')[0];
            const sel = window.getSelection();
            if (!editorEl || !sel || !sel.rangeCount) {
                return null;
            }
            let node = sel.anchorNode;
            while (node && node !== editorEl) {
                if (node.nodeType === 1 && node.tagName === 'A' && node.getAttribute('href')) {
                    return node;
                }
                node = node.parentNode;
            }
            return null;
        }

        function updateRteLinkBar(bubbleEl) {
            const $bubble = $(bubbleEl);
            const $bar = $bubble.find('.mtg-rte-link-bar');
            if (!$bar.length) {
                return;
            }
            const linkEl = findActiveLink(bubbleEl);
            if (!linkEl) {
                $bar.removeClass('is-visible').empty();
                return;
            }
            const href = linkEl.getAttribute('href') || '';
            $bar.html(
                '<i class="fas fa-link text-muted"></i>'
                + '<a href="' + esc(href) + '" target="_blank" rel="noopener noreferrer" class="text-truncate" style="max-width:260px;">' + esc(href) + '</a>'
                + '<a href="' + esc(href) + '" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-secondary py-0 px-2">Open <i class="fas fa-external-link-alt fa-xs"></i></a>'
                + '<button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 mtg-rte-unlink-btn">Remove</button>'
            ).addClass('is-visible');
        }

        function refreshRteToolbarState(bubbleEl) {
            const $bubble = $(bubbleEl);
            if (!$bubble.length) {
                return;
            }
            ['bold', 'italic', 'underline'].forEach(function (cmd) {
                let active = false;
                try {
                    active = document.queryCommandState(cmd);
                } catch (err) {
                    active = false;
                }
                $bubble.find('[data-rte-cmd="' + cmd + '"]').toggleClass('is-active', !!active);
            });
            let blockLabel = 'Tt';
            const sel = window.getSelection();
            if (sel && sel.rangeCount) {
                let node = sel.anchorNode;
                const editorEl = $bubble.find('.mtg-bubble-editor')[0];
                while (node && node !== editorEl) {
                    if (node.nodeType === 1) {
                        const tag = node.tagName.toUpperCase();
                        if (tag === 'H1' || tag === 'H2' || tag === 'H3') {
                            blockLabel = tag;
                            break;
                        }
                    }
                    node = node.parentNode;
                }
            }
            $bubble.find('.mtg-rte-block-label').text(blockLabel);
            updateRteLinkBar(bubbleEl);
        }

        function scheduleResponseSave(editorEl, immediate) {
            const $el = $(editorEl);
            const aid = $el.data('agenda-id');
            const uid = $el.data('user-id');
            const key = $el.data('resp-key');
            if (!key) {
                return;
            }
            state.pendingWrites[key] = true;
            setSavePill('saving', 'Saving...');
            clearTimeout(state.timers[key]);
            const doPost = function () {
                delete state.timers[key];
                const content = extractEditorHtml(editorEl);
                $.post('/agent/ajax_meetings.php', {
                    action: 'save_response',
                    series_id: state.seriesId,
                    occurrence_id: state.occurrenceId,
                    agenda_item_id: aid,
                    user_id: uid,
                    content: content,
                    csrf_token: state.csrf
                }, function (res) {
                    delete state.pendingWrites[key];
                    if (res && res.ok) {
                        const savedContent = res.content !== undefined ? res.content : content;
                        state.syncSeq = res.sync_seq;
                        state.data.responses[String(aid)] = state.data.responses[String(aid)] || {};
                        state.data.responses[String(aid)][String(uid)] = { content: savedContent, updated_at: res.saved_at };
                        $el.attr('data-synced-raw', savedContent);
                        setSavePill('live', 'Saved');
                    }
                }, 'json').fail(function () {
                    delete state.pendingWrites[key];
                    setSavePill('error', 'Save Failed');
                });
            };
            if (immediate) {
                doPost();
            } else {
                state.timers[key] = setTimeout(doPost, 400);
            }
        }

        function closeFocusedBubbles(exceptBubbleEl) {
            $('.mtg-bubble.is-focused').each(function () {
                if (exceptBubbleEl && this === exceptBubbleEl) {
                    return;
                }
                const $b = $(this);
                const ed = $b.find('.mtg-bubble-editor')[0];
                if (ed) {
                    const key = $(ed).data('resp-key');
                    if (key && state.timers[key]) {
                        scheduleResponseSave(ed, true);
                    }
                }
                $b.find('.mtg-rte-dd.open').removeClass('open');
                $b.removeClass('is-focused');
            });
        }

        $(document).on('focus', '.mtg-bubble-editor', function () {
            const $bubble = $(this).closest('.mtg-bubble');
            closeFocusedBubbles($bubble[0]);
            $bubble.addClass('is-focused');
            refreshRteToolbarState($bubble[0]);
        });

        $(document).on('focusin', function (e) {
            if (!$(e.target).closest('.mtg-bubble.is-focused').length) {
                $('.mtg-rte-dd.open').removeClass('open');
                closeFocusedBubbles(null);
            }
        });

        $(document).on('mousedown', function (e) {
            const $target = $(e.target);
            const $inFocused = $target.closest('.mtg-bubble.is-focused');
            if (!$inFocused.length) {
                $('.mtg-rte-dd.open').removeClass('open');
                closeFocusedBubbles(null);
                return;
            }
            if (!$target.closest('.mtg-rte-dd').length) {
                $inFocused.find('.mtg-rte-dd.open').removeClass('open');
            }
        });

        $(document).on('mousedown', '.mtg-rte-toolbar button, .mtg-rte-menu-item, .mtg-rte-swatch', function (e) {
            e.preventDefault();
        });

        $(document).on('click', '.mtg-rte-dd-toggle', function (e) {
            e.preventDefault();
            const $dd = $(this).closest('.mtg-rte-dd');
            const wasOpen = $dd.hasClass('open');
            $dd.closest('.mtg-rte-toolbar').find('.mtg-rte-dd.open').removeClass('open');
            if (!wasOpen) {
                $dd.addClass('open');
            }
        });

        function unwrapNode(el) {
            const parent = el.parentNode;
            if (!parent) {
                return;
            }
            while (el.firstChild) {
                parent.insertBefore(el.firstChild, el);
            }
            parent.removeChild(el);
        }

        function collectSelectedTextSlices(editorEl, range) {
            const walker = document.createTreeWalker(editorEl, NodeFilter.SHOW_TEXT, null);
            const rawNodes = [];
            let node;
            while ((node = walker.nextNode())) {
                if (!node.nodeValue || !node.nodeValue.length) {
                    continue;
                }
                if (typeof range.intersectsNode === 'function' ? range.intersectsNode(node) : true) {
                    rawNodes.push(node);
                }
            }
            const slices = [];
            rawNodes.forEach(function (tNode) {
                let start = 0;
                let end = tNode.nodeValue.length;
                if (tNode === range.startContainer) {
                    start = range.startOffset;
                }
                if (tNode === range.endContainer) {
                    end = range.endOffset;
                }
                if (end <= start) {
                    return;
                }
                let target = tNode;
                if (end < target.nodeValue.length) {
                    target.splitText(end);
                }
                if (start > 0) {
                    target = target.splitText(start);
                }
                slices.push(target);
            });
            return slices;
        }

        function splitAndClearAncestorStyle(tNode, editorEl, prop) {
            let cur = tNode;
            while (cur && cur !== editorEl) {
                const parent = cur.parentNode;
                if (!parent || parent === editorEl || parent.nodeType !== 1) {
                    cur = parent;
                    continue;
                }
                const isTargetSpan = parent.tagName === 'SPAN' && !!parent.style[prop];
                const isTargetFont = prop === 'color' && parent.tagName === 'FONT';
                if (!isTargetSpan && !isTargetFont) {
                    cur = parent;
                    continue;
                }
                const before = parent.cloneNode(false);
                const after = parent.cloneNode(false);
                while (cur.previousSibling) {
                    before.insertBefore(cur.previousSibling, before.firstChild);
                }
                while (cur.nextSibling) {
                    after.appendChild(cur.nextSibling);
                }
                let midNode = cur;
                if (isTargetSpan) {
                    const mid = parent.cloneNode(false);
                    mid.style[prop] = '';
                    if ((mid.getAttribute('style') || '').trim() !== '') {
                        mid.appendChild(cur);
                        midNode = mid;
                    }
                }
                const grand = parent.parentNode;
                if (grand) {
                    if (before.childNodes.length) {
                        grand.insertBefore(before, parent);
                    }
                    grand.insertBefore(midNode, parent);
                    if (after.childNodes.length) {
                        grand.insertBefore(after, parent);
                    }
                    grand.removeChild(parent);
                }
                cur = midNode;
            }
        }

        function applyInlineStyle(editorEl, prop, val, clearVal) {
            editorEl.style[prop] = '';
            $(editorEl).find('div, p, h1, h2, h3, blockquote, li').each(function () {
                this.style[prop] = '';
            });
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) {
                return;
            }
            const range = sel.getRangeAt(0);
            if (!editorEl.contains(range.commonAncestorContainer)) {
                return;
            }
            if (val === clearVal) {
                if (range.collapsed) {
                    let node = range.startContainer;
                    while (node && node !== editorEl) {
                        const next = node.parentNode;
                        if (node.nodeType === 1) {
                            if (prop === 'color' && node.tagName === 'FONT') {
                                unwrapNode(node);
                            } else if (node.tagName === 'SPAN' && node.style[prop]) {
                                node.style[prop] = '';
                                if ((node.getAttribute('style') || '').trim() === '') {
                                    unwrapNode(node);
                                }
                            }
                        }
                        node = next;
                    }
                    return;
                }
                const clearSlices = collectSelectedTextSlices(editorEl, range);
                clearSlices.forEach(function (tNode) {
                    splitAndClearAncestorStyle(tNode, editorEl, prop);
                });
                return;
            }
            if (range.collapsed) {
                let node = range.startContainer;
                while (node && node !== editorEl) {
                    if (node.nodeType === 1 && node.tagName === 'SPAN' && node.style[prop]) {
                        node.style[prop] = val;
                        break;
                    }
                    node = node.parentNode;
                }
                return;
            }
            const slices = collectSelectedTextSlices(editorEl, range);
            if (!slices.length) {
                return;
            }
            const wrapped = [];
            slices.forEach(function (tNode) {
                const parent = tNode.parentNode;
                if (parent && parent !== editorEl && parent.tagName === 'SPAN' && parent.childNodes.length === 1) {
                    parent.style[prop] = val;
                    wrapped.push(tNode);
                } else {
                    const span = document.createElement('span');
                    span.style[prop] = val;
                    parent.insertBefore(span, tNode);
                    span.appendChild(tNode);
                    wrapped.push(tNode);
                }
            });
            if (wrapped.length) {
                const newRange = document.createRange();
                newRange.setStart(wrapped[0], 0);
                const last = wrapped[wrapped.length - 1];
                newRange.setEnd(last, last.nodeValue.length);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }

        $(document).on('click', '[data-rte-cmd]', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const $bubble = $btn.closest('.mtg-bubble');
            const editorEl = $bubble.find('.mtg-bubble-editor')[0];
            if (!editorEl) {
                return;
            }
            editorEl.focus();
            const cmd = $btn.data('rte-cmd');
            const val = $btn.data('rte-val');
            const useCss = (cmd === 'justifyLeft' || cmd === 'justifyCenter' || cmd === 'justifyRight');
            try {
                document.execCommand('styleWithCSS', false, useCss);
            } catch (err) {}
            if (cmd === 'formatBlock') {
                document.execCommand('formatBlock', false, '<' + val + '>');
            } else if (cmd === 'inlineCode') {
                const sel = window.getSelection();
                const selectedText = sel ? sel.toString() : '';
                if (selectedText) {
                    document.execCommand('insertHTML', false, '<code>' + esc(selectedText) + '</code>');
                } else {
                    document.execCommand('insertHTML', false, '<code>code</code>');
                }
            } else if (cmd === 'foreColor') {
                applyInlineStyle(editorEl, 'color', val, 'inherit');
            } else if (cmd === 'hiliteColor') {
                applyInlineStyle(editorEl, 'backgroundColor', val, 'transparent');
            } else {
                document.execCommand(cmd, false, val !== undefined ? val : null);
            }
            $bubble.find('.mtg-rte-dd.open').removeClass('open');
            refreshRteToolbarState($bubble[0]);
            scheduleResponseSave(editorEl, false);
        });

        $(document).on('click', '[data-rte-action="link"]', function (e) {
            e.preventDefault();
            const $bubble = $(this).closest('.mtg-bubble');
            const editorEl = $bubble.find('.mtg-bubble-editor')[0];
            if (!editorEl) {
                return;
            }
            $bubble.find('.mtg-rte-dd.open').removeClass('open');
            const saved = saveCurrentRange(editorEl);
            const rawUrl = window.prompt('Enter link URL (https://...):', 'https://');
            if (rawUrl === null) {
                restoreRange(editorEl, saved);
                return;
            }
            let url = rawUrl.trim();
            if (!url || /^(javascript|data|vbscript):/i.test(url)) {
                restoreRange(editorEl, saved);
                return;
            }
            if (!/^(https?:\/\/|mailto:)/i.test(url)) {
                url = 'https://' + url;
            }
            restoreRange(editorEl, saved);
            const sel = window.getSelection();
            let range = (sel && sel.rangeCount) ? sel.getRangeAt(0) : null;
            if (!range || !editorEl.contains(range.commonAncestorContainer)) {
                range = document.createRange();
                range.selectNodeContents(editorEl);
                range.collapse(false);
            }
            const linkEl = document.createElement('a');
            linkEl.href = url;
            linkEl.target = '_blank';
            linkEl.rel = 'noopener noreferrer';
            if (!range.collapsed) {
                linkEl.appendChild(range.extractContents());
            } else {
                linkEl.textContent = url;
            }
            range.insertNode(linkEl);
            const afterNode = document.createTextNode(' ');
            if (linkEl.parentNode) {
                linkEl.parentNode.insertBefore(afterNode, linkEl.nextSibling);
            }
            if (sel) {
                const caretRange = document.createRange();
                caretRange.setStart(linkEl, linkEl.childNodes.length);
                caretRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(caretRange);
            }
            updateRteLinkBar($bubble[0]);
            scheduleResponseSave(editorEl, false);
        });

        $(document).on('mousedown', '.mtg-rte-unlink-btn', function (e) {
            e.preventDefault();
        });

        $(document).on('click', '.mtg-rte-unlink-btn', function (e) {
            e.preventDefault();
            const $bubble = $(this).closest('.mtg-bubble');
            const editorEl = $bubble.find('.mtg-bubble-editor')[0];
            const linkEl = findActiveLink($bubble[0]);
            if (editorEl && linkEl) {
                unwrapNode(linkEl);
                updateRteLinkBar($bubble[0]);
                scheduleResponseSave(editorEl, false);
            }
        });

        $(document).on('mousedown', '.mtg-bubble-editor a[href]', function (e) {
            if (!$(this).closest('.mtg-bubble').hasClass('is-focused')) {
                e.preventDefault();
            }
        });

        $(document).on('click', '.mtg-bubble-editor a[href]', function (e) {
            const $bubble = $(this).closest('.mtg-bubble');
            if (!$bubble.hasClass('is-focused') || e.ctrlKey || e.metaKey) {
                e.preventDefault();
                e.stopPropagation();
                window.open(this.href, '_blank', 'noopener,noreferrer');
            }
        });

        $(document).on('click', '[data-rte-action="image"]', function (e) {
            e.preventDefault();
            const $bubble = $(this).closest('.mtg-bubble');
            const editorEl = $bubble.find('.mtg-bubble-editor')[0];
            if (!editorEl) {
                return;
            }
            $bubble.find('.mtg-rte-dd.open').removeClass('open');
            const range = saveCurrentRange(editorEl);
            const rawUrl = window.prompt('Enter image URL (https://...):', 'https://');
            if (rawUrl === null) {
                restoreRange(editorEl, range);
                return;
            }
            const url = rawUrl.trim();
            if (!/^https?:\/\//i.test(url)) {
                return;
            }
            restoreRange(editorEl, range);
            document.execCommand('insertHTML', false, '<img src="' + esc(url) + '" alt="">');
            scheduleResponseSave(editorEl, false);
        });

        $(document).on('input', '.mtg-bubble-editor', function () {
            if (this.innerHTML === '<br>') {
                this.innerHTML = '';
            }
            scheduleResponseSave(this, false);
        });

        $(document).on('keyup mouseup', '.mtg-bubble-editor', function () {
            refreshRteToolbarState($(this).closest('.mtg-bubble')[0]);
        });

        $(document).on('input', '.mtg-card-search', function () {
            const aid = $(this).data('agenda-id');
            state.searchTerms[aid] = $(this).val() || '';
            renderWorkspaceCards(true);
        });

        $(document).on('click', '.mtg-force-sync-btn', function () {
            pollNow(true);
        });

        $(document).on('input', '#mtgSharedNotesInput, #mtgSharedNotesFull', function () {
            const val = $(this).val();
            $('#mtgSharedNotesInput').val(val);
            $('#mtgSharedNotesFull').val(val);
            state.pendingWrites['shared_notes'] = true;
            setSavePill('saving', 'Saving Notes...');
            clearTimeout(state.timers['shared_notes']);
            state.timers['shared_notes'] = setTimeout(function () {
                $.post('/agent/ajax_meetings.php', {
                    action: 'save_shared_notes',
                    series_id: state.seriesId,
                    occurrence_id: state.occurrenceId,
                    shared_notes: val,
                    expected_version: state.data.occurrence.shared_notes_version,
                    csrf_token: state.csrf
                }, function (res) {
                    delete state.pendingWrites['shared_notes'];
                    if (res && res.ok) {
                        state.syncSeq = res.sync_seq;
                        state.data.occurrence.shared_notes = val;
                        state.data.occurrence.shared_notes_version = res.shared_notes_version;
                        setSavePill('live', 'Saved');
                    }
                }, 'json').fail(function (xhr) {
                    delete state.pendingWrites['shared_notes'];
                    if (xhr.status === 409 && xhr.responseJSON) {
                        state.data.occurrence.shared_notes_version = xhr.responseJSON.server_version;
                        toastr.warning('Shared notes were updated by another user. Reloading latest.');
                        pollNow(true);
                    }
                });
            }, 500);
        });

        $(document).on('input', '#mtgPersonalNotesInput, #mtgPersonalNotesFull', function () {
            const val = $(this).val();
            $('#mtgPersonalNotesInput').val(val);
            $('#mtgPersonalNotesFull').val(val);
            setSavePill('saving', 'Saving Personal...');
            clearTimeout(state.timers['personal_notes']);
            state.timers['personal_notes'] = setTimeout(function () {
                $.post('/agent/ajax_meetings.php', {
                    action: 'save_personal_notes',
                    series_id: state.seriesId,
                    occurrence_id: state.occurrenceId,
                    personal_note: val,
                    csrf_token: state.csrf
                }, function () {
                    setSavePill('live', 'Saved');
                }, 'json');
            }, 500);
        });

        $('#mtgAddAgendaBtn').on('click', function () {
            $('#mtgAgendaForm')[0].reset();
            $('#mtgAgendaId').val(0);
            $('#mtgAgendaModal').modal('show');
        });

        $(document).on('click', '.mtg-edit-agenda', function (e) {
            e.stopPropagation();
            const id = Number($(this).data('id'));
            const ag = (state.data.agenda_items || []).find(function (x) { return Number(x.agenda_item_id) === id; });
            if (!ag) return;
            $('#mtgAgendaId').val(ag.agenda_item_id);
            $('#mtgAgendaTitle').val(ag.title);
            $('#mtgAgendaType').val(ag.item_type);
            $('#mtgAgendaIcon').val(ag.icon);
            $('#mtgAgendaModal').modal('show');
        });

        $('#mtgAgendaForm').on('submit', function (e) {
            e.preventDefault();
            $.post('/agent/ajax_meetings.php', $(this).serialize() + '&action=agenda_save&series_id=' + state.seriesId + '&occurrence_id=' + state.occurrenceId + '&csrf_token=' + encodeURIComponent(state.csrf), function (res) {
                if (res && res.ok) {
                    $('#mtgAgendaModal').modal('hide');
                    pollNow(true);
                }
            }, 'json');
        });

        $(document).on('click', '.mtg-del-agenda', function (e) {
            e.stopPropagation();
            if (!confirm('Remove this agenda item?')) return;
            $.post('/agent/ajax_meetings.php', { action: 'agenda_delete', series_id: state.seriesId, agenda_item_id: $(this).data('id'), csrf_token: state.csrf }, function () {
                pollNow(true);
            }, 'json');
        });

        $('#mtgAddAttendeeBtn').on('click', function () {
            const uid = $('#mtgAddAttendeeSelect').val();
            if (!uid) return;
            $.post('/agent/ajax_meetings.php', { action: 'attendee_add', series_id: state.seriesId, user_id: uid, csrf_token: state.csrf }, function () {
                pollNow(true);
            }, 'json');
        });

        $(document).on('click', '.mtg-remove-attendee', function () {
            $.post('/agent/ajax_meetings.php', { action: 'attendee_remove', series_id: state.seriesId, user_id: $(this).data('user-id'), csrf_token: state.csrf }, function () {
                pollNow(true);
            }, 'json');
        });

        $(document).on('click', '.mtg-metric-scope-btn', function () {
            state.metricScopeFilter = $(this).data('scope');
            renderWorkspaceCards(true);
        });

        $(document).on('click', '.mtg-metric-period-step', function (e) {
            e.preventDefault();
            const delta = Number($(this).data('delta'));
            state.metricPeriod = stepMetricMonth(state.metricPeriod, delta);
            pollNow(true);
        });

        function toggleStartTargetField() {
            $('#mtgStartTargetWrap').toggle($('#mtgMetricType').val() === 'Measurement');
        }

        $('#mtgMetricType').on('change', toggleStartTargetField);

        $(document).on('click', '.mtg-open-metric-modal', function () {
            $('#mtgMetricForm')[0].reset();
            $('#mtgMetricId').val(0);
            $('#mtgMetricOwner').val(state.currentUserId);
            $('#mtgMetricPeriodKey').val(state.metricPeriod);
            $('#mtgMetricQuarterKey').val(quarterKeyFromMonth(state.metricPeriod));
            $('#mtgMetricType').val('Count');
            $('#mtgMetricFrequency').val('Monthly');
            $('#mtgMetricWeekStart').val('Monday');
            $('#mtgMetricPrivacy').val('Public');
            $('#mtgMetricStartTarget').val('0');
            $('#mtgMetricQuarterGoal').val('');
            $('#mtgMetricTarget').val('0');
            $('#mtgMetricActual').val('');
            $('#mtgMetricAutoStatus').prop('checked', true);
            $('#mtgMetricSuperGreenPct').val('120');
            $('#mtgMetricYellowPct').val('80');
            $('.mtg-work-day-chk').each(function () {
                const v = $(this).val();
                $(this).prop('checked', v !== 'Sat' && v !== 'Sun');
            });
            toggleStartTargetField();
            $('#mtgMetricModal').modal('show');
        });

        $(document).on('click', '.mtg-edit-metric', function () {
            const id = Number($(this).data('id'));
            const m = (state.data.metrics || []).find(function (x) { return Number(x.metric_id) === id; });
            if (!m) return;
            $('#mtgMetricId').val(m.metric_id);
            $('#mtgMetricName').val(m.metric_name);
            $('#mtgMetricOwner').val(m.owner_user_id);
            $('#mtgMetricType').val(m.metric_type || 'Count');
            $('#mtgMetricFrequency').val(m.frequency || 'Monthly');
            $('#mtgMetricUnit').val(m.unit);
            $('#mtgMetricScope').val(m.scope);
            $('#mtgMetricPrivacy').val(m.privacy || 'Public');
            $('#mtgMetricDir').val(m.goal_direction);
            $('#mtgMetricWeekStart').val(m.week_start || 'Monday');
            $('#mtgMetricStartTarget').val(m.start_target != null ? m.start_target : 0);
            $('#mtgMetricQuarterGoal').val('');
            $('#mtgMetricTarget').val(m.period_target != null ? m.period_target : 0);
            $('#mtgMetricActual').val(m.actual_value !== null && m.actual_value !== undefined ? m.actual_value : '');
            $('#mtgMetricAutoStatus').prop('checked', Number(m.auto_set_status) !== 0);
            $('#mtgMetricSuperGreenPct').val(m.super_green_pct != null ? m.super_green_pct : 120);
            $('#mtgMetricYellowPct').val(m.yellow_pct != null ? m.yellow_pct : 80);
            $('#mtgMetricStarred').prop('checked', !!m.is_starred);
            $('#mtgMetricPeriodKey').val(m.period_key || state.metricPeriod);
            $('#mtgMetricQuarterKey').val(m.quarter_key || quarterKeyFromMonth(state.metricPeriod));
            const wdSet = String(m.work_days || 'Mon,Tue,Wed,Thu,Fri').split(',').map(function (s) { return s.trim(); });
            $('.mtg-work-day-chk').each(function () {
                $(this).prop('checked', wdSet.indexOf($(this).val()) !== -1);
            });
            toggleStartTargetField();
            $('#mtgMetricModal').modal('show');
        });

        $('#mtgMetricForm').on('submit', function (e) {
            e.preventDefault();
            const autoVal = $('#mtgMetricAutoStatus').is(':checked') ? '1' : '0';
            const payload = $(this).serialize()
                + '&auto_set_status=' + autoVal
                + '&action=metric_save&series_id=' + state.seriesId
                + '&occurrence_id=' + state.occurrenceId
                + '&csrf_token=' + encodeURIComponent(state.csrf);
            $.post('/agent/ajax_meetings.php', payload, function (res) {
                if (res && res.ok) {
                    $('#mtgMetricModal').modal('hide');
                    pollNow(true);
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Could not save metric.');
            });
        });

        let activeTableMetric = null;

        function renderMetricTableModal(metric, summary) {
            activeTableMetric = { metric: metric, summary: summary };
            const unit = metric.unit;
            const q = summary.quarter || {};
            $('#mtgTableModalTitle').text(metric.metric_name);
            $('#mtgTableModalSub').text(metric.owner_name + ' \u00B7 ' + metric.metric_type + ' \u00B7 ' + metric.frequency + ' \u00B7 ' + (q.label || ''));
            $('#mtgTableModalFullLink').attr('href', '/agent/metrics.php?metric_id=' + metric.metric_id + '&quarter=' + encodeURIComponent(q.quarter_key || ''));
            $('#mtgTblIntervalLabel').text(summary.interval_label || 'Current Interval');
            $('#mtgTblIntervalVal').html('<span class="mtg-health-square mtg-health-' + esc(summary.status_color) + ' mr-2"></span>' + fmtMetricVal(summary.interval_actual, unit) + ' / ' + fmtMetricVal(summary.interval_target, unit));
            $('#mtgTblQuarterLabel').text((q.label || 'Quarter') + ' To Date');
            $('#mtgTblToDateVal').text(fmtMetricVal(summary.to_date_actual, unit) + ' / ' + fmtMetricVal(summary.to_date_target, unit));
            $('#mtgTblWorkDaysInfo').text('Target Pace (' + (summary.elapsed_work_days || 0) + ' / ' + (summary.total_work_days || 0) + ' work days)');
            $('#mtgTblPaceVal').text(fmtMetricVal(summary.target_pace, unit));
            $('#mtgTblSplitWrap').toggle(!!metric.can_edit);

            const rows = summary.rows || [];
            const html = rows.map(function (r) {
                const ptdStr = fmtMetricVal(r.period_to_date_actual, unit) + ' / ' + fmtMetricVal(r.period_to_date_target, unit);
                const actVal = (r.actual_value !== null && r.actual_value !== undefined) ? r.actual_value : '';
                if (!metric.can_edit) {
                    return '<tr>'
                        + '<td><span class="mtg-health-square mtg-health-' + esc(r.status_color) + ' mr-2"></span><strong>' + esc(r.label) + '</strong></td>'
                        + '<td class="text-right">' + fmtMetricVal(r.actual_value, unit) + '</td>'
                        + '<td class="text-right">' + fmtMetricVal(r.rolling_target, unit) + '</td>'
                        + '<td class="text-right">' + fmtMetricVal(r.approved_target, unit) + '</td>'
                        + '<td class="text-right">' + ptdStr + '</td>'
                        + '<td class="small text-muted">' + esc(r.status_note || '') + '</td>'
                        + '</tr>';
                }
                return '<tr data-period-key="' + esc(r.period_key) + '">'
                    + '<td class="align-middle"><span class="mtg-health-square mtg-health-' + esc(r.status_color) + ' mr-2"></span><strong>' + esc(r.label) + '</strong></td>'
                    + '<td><input type="number" step="0.01" class="form-control form-control-sm text-right mtg-tbl-cell-input" data-field="actual_value" value="' + esc(actVal) + '" placeholder="-"></td>'
                    + '<td><input type="number" step="0.01" class="form-control form-control-sm text-right mtg-tbl-cell-input" data-field="rolling_target" value="' + esc(r.rolling_target) + '"></td>'
                    + '<td><input type="number" step="0.01" class="form-control form-control-sm text-right mtg-tbl-cell-input" data-field="approved_target" value="' + esc(r.approved_target) + '"></td>'
                    + '<td class="text-right align-middle text-nowrap">' + ptdStr + '</td>'
                    + '<td><input type="text" class="form-control form-control-sm mtg-tbl-cell-input" data-field="status_note" value="' + esc(r.status_note || '') + '" placeholder="Add note..."></td>'
                    + '</tr>';
            }).join('');
            $('#mtgTblBody').html(html);
        }

        function loadMetricTableModal(metricId) {
            const qKey = quarterKeyFromMonth(state.metricPeriod);
            const occDate = (state.data.occurrence && state.data.occurrence.occurrence_date) || '';
            $.get('/agent/ajax_meetings.php', {
                action: 'metric_detail',
                metric_id: metricId,
                quarter: qKey,
                as_of: occDate
            }, function (res) {
                if (res && res.ok) {
                    renderMetricTableModal(res.metric, res.summary);
                    $('#mtgTblQuarterGoalInput').val(res.summary.to_date_target || '');
                    $('#mtgMetricTableModal').modal('show');
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Unable to load metric table.');
            });
        }

        $(document).on('click', '.mtg-open-metric-table', function (e) {
            e.preventDefault();
            loadMetricTableModal(Number($(this).data('id')));
        });

        $(document).on('change', '.mtg-tbl-cell-input', function () {
            if (!activeTableMetric) return;
            const $tr = $(this).closest('tr');
            const pk = $tr.data('period-key');
            const field = $(this).data('field');
            const val = $(this).val();
            const qKey = activeTableMetric.summary.quarter.quarter_key;
            const occDate = (state.data.occurrence && state.data.occurrence.occurrence_date) || '';
            const payload = {
                action: 'metric_save_cell',
                metric_id: activeTableMetric.metric.metric_id,
                period_key: pk,
                quarter: qKey,
                as_of: occDate,
                csrf_token: state.csrf
            };
            payload[field] = val;
            $('#mtgTblSaveHint').text('Saving...');
            $.post('/agent/ajax_meetings.php', payload, function (res) {
                if (res && res.ok) {
                    renderMetricTableModal(activeTableMetric.metric, res.summary);
                    $('#mtgTblSaveHint').text('Saved & synced.');
                    pollNow(true);
                }
            }, 'json').fail(function (xhr) {
                $('#mtgTblSaveHint').text('Save failed.');
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save table cell.');
            });
        });

        $('#mtgTblSplitBtn').on('click', function () {
            if (!activeTableMetric) return;
            const totalGoal = $('#mtgTblQuarterGoalInput').val();
            if (totalGoal === '') return;
            const qKey = activeTableMetric.summary.quarter.quarter_key;
            const occDate = (state.data.occurrence && state.data.occurrence.occurrence_date) || '';
            $('#mtgTblSaveHint').text('Splitting goal...');
            $.post('/agent/ajax_meetings.php', {
                action: 'metric_split_quarter_goal',
                metric_id: activeTableMetric.metric.metric_id,
                quarter: qKey,
                as_of: occDate,
                total_goal: totalGoal,
                csrf_token: state.csrf
            }, function (res) {
                if (res && res.ok) {
                    renderMetricTableModal(activeTableMetric.metric, res.summary);
                    $('#mtgTblSaveHint').text('Quarter goal distributed.');
                    pollNow(true);
                }
            }, 'json').fail(function (xhr) {
                $('#mtgTblSaveHint').text('Split failed.');
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to split quarter goal.');
            });
        });

        $(document).on('click', '.mtg-toggle-metric-star', function () {
            $.post('/agent/ajax_meetings.php', { action: 'metric_toggle_star', series_id: state.seriesId, metric_id: $(this).data('id'), csrf_token: state.csrf }, function () {
                pollNow(true);
            }, 'json');
        });

        $(document).on('click', '.mtg-cycle-metric-color', function () {
            $.post('/agent/ajax_meetings.php', { action: 'metric_cycle_color', series_id: state.seriesId, metric_id: $(this).data('id'), period_key: state.metricPeriod, csrf_token: state.csrf }, function () {
                pollNow(true);
            }, 'json');
        });

        $(document).on('click', '.mtg-open-action-modal', function () {
            $('#mtgActionForm')[0].reset();
            $('#mtgActionId').val(0);
            $('#mtgActionOwner').val(state.currentUserId);
            $('#mtgActionModal').modal('show');
        });

        $(document).on('click', '.mtg-edit-action', function () {
            const id = Number($(this).data('id'));
            const a = (state.data.action_items || []).find(function (x) { return Number(x.action_id) === id; });
            if (!a) return;
            $('#mtgActionId').val(a.action_id);
            $('#mtgActionDesc').val(a.description);
            $('#mtgActionOwner').val(a.owner_user_id);
            $('#mtgActionDue').val(a.due_date || '');
            $('#mtgActionPrio').val(a.priority);
            $('#mtgActionStatus').val(a.status);
            $('#mtgActionModal').modal('show');
        });

        $('#mtgActionForm').on('submit', function (e) {
            e.preventDefault();
            $.post('/agent/ajax_meetings.php', $(this).serialize() + '&action=action_save&series_id=' + state.seriesId + '&occurrence_id=' + state.occurrenceId + '&csrf_token=' + encodeURIComponent(state.csrf), function (res) {
                if (res && res.ok) {
                    $('#mtgActionModal').modal('hide');
                    pollNow(true);
                }
            }, 'json').fail(function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save action item.');
            });
        });

        $(document).on('change', '.mtg-toggle-action', function () {
            $.post('/agent/ajax_meetings.php', { action: 'action_toggle', series_id: state.seriesId, occurrence_id: state.occurrenceId, action_id: $(this).data('id'), csrf_token: state.csrf }, function () {
                pollNow(true);
            }, 'json');
        });

        $(document).on('click', '.mtg-del-action', function () {
            if (!confirm('Delete this action item?')) return;
            $.post('/agent/ajax_meetings.php', { action: 'action_delete', series_id: state.seriesId, action_id: $(this).data('id'), csrf_token: state.csrf }, function () {
                pollNow(true);
            }, 'json');
        });

        $(document).on('click', '.mtg-action-filter-btn', function () {
            state.actionFilter = $(this).data('filter');
            renderWorkspaceCards(true);
        });

        $('#mtgAttachmentForm').on('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'attachment_add');
            fd.append('series_id', state.seriesId);
            fd.append('occurrence_id', state.occurrenceId);
            fd.append('csrf_token', state.csrf);
            $.ajax({
                url: '/agent/ajax_meetings.php',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function () {
                    $('#mtgAttachmentForm')[0].reset();
                    $('#mtgAttachmentModal').modal('hide');
                    pollNow(true);
                },
                error: function (xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to add attachment.');
                }
            });
        });

        $(document).on('click', '.mtg-del-attachment', function () {
            if (!confirm('Delete this attachment?')) return;
            $.post('/agent/ajax_meetings.php', { action: 'attachment_delete', series_id: state.seriesId, attachment_id: $(this).data('id'), csrf_token: state.csrf }, function () {
                pollNow(true);
            }, 'json');
        });
    });
})();
