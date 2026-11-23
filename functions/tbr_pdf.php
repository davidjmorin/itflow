<?php

require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';

class TbrPdfDocument extends TCPDF {
    public string $tbrHeaderTitle = '';
    public string $tbrHeaderSub = '';
    public string $tbrFooterLabel = '';
    public bool $tbrIsInternal = false;

    public function Header(): void {
        if ($this->page === 1) {
            return;
        }
        $this->SetY(8);
        $this->SetFont('helvetica', 'B', 8.5);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(125, 5, $this->tbrHeaderTitle, 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        if ($this->tbrIsInternal) {
            $this->SetTextColor(185, 28, 28);
        } else {
            $this->SetTextColor(100, 116, 139);
        }
        $this->Cell(0, 5, $this->tbrHeaderSub, 0, 1, 'R');
        $this->SetDrawColor(203, 213, 225);
        $this->Line(15, 14, 195, 14);
    }

    public function Footer(): void {
        if ($this->page === 1) {
            return;
        }
        $this->SetY(-14);
        $this->SetDrawColor(203, 213, 225);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetY(-12);
        $this->SetFont('helvetica', '', 7.5);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(140, 5, $this->tbrFooterLabel, 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

function tbrPdfBadgeHtml(string $label, string $kind = 'neutral'): string {
    $colors = [
        'good' => ['#dcfce7', '#166534'],
        'success' => ['#dcfce7', '#166534'],
        'approved' => ['#dcfce7', '#166534'],
        'completed' => ['#dcfce7', '#166534'],
        'resolved' => ['#dcfce7', '#166534'],
        'active' => ['#dcfce7', '#166534'],
        'low' => ['#dbeafe', '#1e40af'],
        'moderate' => ['#fef9c3', '#854d0e'],
        'medium' => ['#fef9c3', '#854d0e'],
        'needs attention' => ['#fef3c7', '#92400e'],
        'expiring soon' => ['#fef3c7', '#92400e'],
        'in progress' => ['#e0f2fe', '#075985'],
        'proposed' => ['#e0f2fe', '#075985'],
        'planned' => ['#e0f2fe', '#075985'],
        'waiting on client' => ['#fef3c7', '#92400e'],
        'high' => ['#ffedd5', '#9a3412'],
        'critical' => ['#fee2e2', '#991b1b'],
        'expired' => ['#fee2e2', '#991b1b'],
        'declined' => ['#fee2e2', '#991b1b'],
        'cancelled' => ['#f1f5f9', '#475569'],
        'deferred' => ['#f1f5f9', '#475569'],
        'not assessed' => ['#f1f5f9', '#475569'],
        'not started' => ['#f1f5f9', '#475569'],
        'neutral' => ['#f1f5f9', '#334155'],
    ];
    $key = strtolower(trim($label));
    $pair = $colors[$key] ?? $colors[$kind] ?? $colors['neutral'];
    return '<span style="background-color:' . $pair[0] . '; color:' . $pair[1] . '; font-weight:bold;">&nbsp;' . escapeHtml($label) . '&nbsp;</span>';
}

function tbrPdfSectionHeading(string $num, string $title, ?string $subtitle = null): string {
    $html = '<table cellpadding="6" cellspacing="0" width="100%" style="margin-top:8px;">
        <tr>
            <td style="background-color:#1e293b; color:#ffffff; font-size:11.5pt; font-weight:bold; border-left:4px solid #dc2626;">' . escapeHtml($num . ' — ' . $title) . '</td>
        </tr>';
    if ($subtitle) {
        $html .= '<tr><td style="background-color:#f8fafc; color:#475569; font-size:8.5pt; border-bottom:1px solid #cbd5e1;">' . escapeHtml($subtitle) . '</td></tr>';
    }
    $html .= '</table><br>';
    return $html;
}

function tbrPdfInternalBox(string $title, string $text): string {
    if (trim($text) === '') {
        return '';
    }
    return '<table cellpadding="6" cellspacing="0" width="100%" style="border:1px solid #fca5a5; background-color:#fef2f2; margin-top:4px;">
        <tr><td style="font-size:8.5pt; color:#991b1b;"><strong>[INTERNAL MSP ONLY] ' . escapeHtml($title) . ':</strong><br>' . nl2br(escapeHtml($text)) . '</td></tr>
    </table><br>';
}

function tbrGeneratePdfReport(int $tbr_id, string $mode = 'client', int $user_id = 0): array {
    global $mysqli;

    $mode = ($mode === 'internal') ? 'internal' : 'client';
    $is_internal = ($mode === 'internal');
    $tbr_id = intval($tbr_id);

    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        throw new RuntimeException('TBR meeting not found.');
    }

    $client_id = intval($meeting['tbr_client_id']);
    $settings = tbrGetSettings();
    $sections_cfg = tbrGetSectionsConfig();
    $company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1 LIMIT 1")) ?: [];
    $client = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT c.*, l.location_address, l.location_city, l.location_state, l.location_zip, l.location_country,
               ct.contact_name, ct.contact_email, ct.contact_phone
        FROM clients c
        LEFT JOIN locations l ON c.client_id = l.location_client_id AND l.location_primary = 1
        LEFT JOIN contacts ct ON c.client_id = ct.contact_client_id AND ct.contact_primary = 1
        WHERE c.client_id = $client_id LIMIT 1
    ")) ?: [];

    $responses = [];
    $res_resp = mysqli_query($mysqli, "SELECT * FROM tbr_section_responses WHERE response_tbr_id = $tbr_id");
    while ($res_resp && ($r = mysqli_fetch_assoc($res_resp))) {
        $r['answers'] = json_decode((string)$r['answers_json'], true) ?: [];
        $responses[$r['section_key']] = $r;
    }

    $attendees = [];
    $res_att = mysqli_query($mysqli, "SELECT * FROM tbr_attendees WHERE attendee_tbr_id = $tbr_id ORDER BY attendee_type ASC, attendee_name ASC");
    while ($res_att && ($a = mysqli_fetch_assoc($res_att))) {
        $attendees[] = $a;
    }

    $findings_where = $is_internal ? "" : " AND finding_client_visible = 1 ";
    $findings_by_sec = [];
    $all_findings = [];
    $res_f = mysqli_query($mysqli, "SELECT * FROM tbr_findings WHERE finding_tbr_id = $tbr_id $findings_where ORDER BY FIELD(finding_risk_level, 'Critical','High','Moderate','Low'), finding_id ASC");
    while ($res_f && ($f = mysqli_fetch_assoc($res_f))) {
        $findings_by_sec[$f['finding_section']][] = $f;
        $all_findings[] = $f;
    }

    $recommendations = [];
    $res_rec = mysqli_query($mysqli, "SELECT * FROM tbr_recommendations WHERE recommendation_tbr_id = $tbr_id ORDER BY FIELD(recommendation_priority, 'Critical','High','Medium','Low'), recommendation_id ASC");
    while ($res_rec && ($rec = mysqli_fetch_assoc($res_rec))) {
        $recommendations[] = $rec;
    }

    $roadmap_items = [];
    $res_rm = mysqli_query($mysqli, "SELECT * FROM tbr_roadmap_items WHERE roadmap_tbr_id = $tbr_id ORDER BY roadmap_fiscal_year ASC, FIELD(roadmap_quarter, 'Q1','Q2','Q3','Q4'), FIELD(roadmap_priority, 'Critical','High','Medium','Low'), roadmap_id ASC");
    while ($res_rm && ($rm = mysqli_fetch_assoc($res_rm))) {
        $roadmap_items[] = $rm;
    }

    $action_items = [];
    $res_act = mysqli_query($mysqli, "
        SELECT a.*, u.user_name AS assigned_user_name
        FROM tbr_action_items a
        LEFT JOIN users u ON a.action_assigned_user_id = u.user_id
        WHERE a.action_tbr_id = $tbr_id
        ORDER BY FIELD(a.action_priority, 'Critical','High','Medium','Low'), a.action_due_date ASC, a.action_id ASC
    ");
    while ($res_act && ($act = mysqli_fetch_assoc($res_act))) {
        $action_items[] = $act;
    }

    $live_data = tbrFetchLiveClientData($client_id, $meeting['tbr_reporting_period_start'], $meeting['tbr_reporting_period_end']);
    $currency_code = $client['client_currency_code'] ?? ($company['company_currency'] ?? 'USD');
    $formatMoney = function($amount) use ($currency_code) {
        if ($amount === null || $amount === '') {
            return 'TBD';
        }
        return $currency_code . ' ' . number_format(floatval($amount), 2);
    };

    $conf_label = $is_internal
        ? 'INTERNAL MSP REPORT - DO NOT DISTRIBUTE EXTERNALLY'
        : ($settings['default_confidentiality_label'] ?: 'CONFIDENTIAL - PREPARED EXCLUSIVELY FOR CLIENT LEADERSHIP');

    $pdf = new TbrPdfDocument('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->tbrIsInternal = $is_internal;
    $pdf->tbrHeaderTitle = ($client['client_name'] ?? 'Client') . ' — ' . ($meeting['tbr_title'] ?? 'Technology Business Review');
    $pdf->tbrHeaderSub = ($is_internal ? 'INTERNAL MSP REPORT | ' : '') . date('M j, Y', strtotime($meeting['tbr_meeting_date']));
    $pdf->tbrFooterLabel = $conf_label;

    $pdf->SetCreator($company['company_name'] ?? 'ITFlow');
    $pdf->SetAuthor($meeting['rep_name'] ?? ($company['company_name'] ?? 'MSP'));
    $pdf->SetTitle(($is_internal ? '[Internal] ' : '') . 'TBR Report - ' . ($client['client_name'] ?? 'Client'));
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 18);

    $pdf->AddPage();
    $pdf->SetFillColor(15, 23, 42);
    $pdf->Rect(0, 0, 210, 82, 'F');
    $pdf->SetFillColor(220, 38, 38);
    $pdf->Rect(0, 82, 210, 2.5, 'F');

    $logo_file = !empty($company['company_logo']) ? realpath(__DIR__ . '/../uploads/settings/' . basename($company['company_logo'])) : false;
    if ($logo_file && is_file($logo_file)) {
        $pdf->Image($logo_file, 15, 14, 48, 0, '', '', '', true, 150);
    } else {
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(15, 18);
        $pdf->Cell(120, 10, $company['company_name'] ?? 'MSP', 0, 1, 'L');
    }

    $pdf->SetXY(15, 42);
    $pdf->SetTextColor(248, 250, 252);
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->Cell(180, 10, 'TECHNOLOGY BUSINESS REVIEW', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(203, 213, 225);
    $pdf->Cell(180, 7, ($meeting['tbr_title'] ?? 'Quarterly Strategic Review') . ' (' . ($meeting['tbr_type'] ?? 'Quarterly') . ')', 0, 1, 'L');

    if ($is_internal) {
        $pdf->SetXY(15, 68);
        $pdf->SetFillColor(185, 28, 28);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell(95, 7, '  INTERNAL MSP EDITION (CONTAINS PRIVATE NOTES)', 0, 1, 'L', true);
    }

    $pdf->SetY(96);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(180, 9, 'Prepared for: ' . ($client['client_name'] ?? ''), 0, 1, 'L');
    $pdf->Ln(4);

    $period_text = (!empty($meeting['tbr_reporting_period_start']) && !empty($meeting['tbr_reporting_period_end']))
        ? date('M j, Y', strtotime($meeting['tbr_reporting_period_start'])) . ' – ' . date('M j, Y', strtotime($meeting['tbr_reporting_period_end']))
        : 'Current Quarter';

    $cover_meta = '<table cellpadding="8" cellspacing="0" width="100%" style="border:1px solid #cbd5e1;">
        <tr style="background-color:#f8fafc;">
            <td width="35%" style="font-weight:bold; color:#334155; border-bottom:1px solid #e2e8f0;">MSP Provider</td>
            <td width="65%" style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($company['company_name'] ?? '') . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; color:#334155; border-bottom:1px solid #e2e8f0;">Client Organization</td>
            <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($client['client_name'] ?? '') . '</td>
        </tr>
        <tr style="background-color:#f8fafc;">
            <td style="font-weight:bold; color:#334155; border-bottom:1px solid #e2e8f0;">Meeting Date &amp; Time</td>
            <td style="border-bottom:1px solid #e2e8f0;">' . date('F j, Y', strtotime($meeting['tbr_meeting_date'])) . ' at ' . date('g:i A', strtotime($meeting['tbr_start_time'])) . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; color:#334155; border-bottom:1px solid #e2e8f0;">Reporting Period</td>
            <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($period_text) . '</td>
        </tr>
        <tr style="background-color:#f8fafc;">
            <td style="font-weight:bold; color:#334155; border-bottom:1px solid #e2e8f0;">Prepared By</td>
            <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($meeting['rep_name'] ?? 'Account Team') . (!empty($meeting['rep_email']) ? ' (' . escapeHtml($meeting['rep_email']) . ')' : '') . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; color:#334155; border-bottom:1px solid #e2e8f0;">Overall IT Environment Health</td>
            <td style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($meeting['tbr_overall_health'] ?? 'Not Assessed') . '</td>
        </tr>
        <tr style="background-color:#f8fafc;">
            <td style="font-weight:bold; color:#334155;">Next Scheduled Review</td>
            <td>' . (!empty($meeting['tbr_next_review_date']) ? date('F j, Y', strtotime($meeting['tbr_next_review_date'])) : 'To Be Scheduled') . '</td>
        </tr>
    </table>';

    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($cover_meta, true, false, true, false, '');

    if (!empty($attendees)) {
        $att_client = [];
        $att_msp = [];
        foreach ($attendees as $a) {
            $lbl = escapeHtml($a['attendee_name'] ?: $a['attendee_email']);
            if ($a['attendee_type'] === 'internal') {
                $att_msp[] = $lbl;
            } else {
                $att_client[] = $lbl;
            }
        }
        $att_html = '<br><table cellpadding="6" cellspacing="0" width="100%" style="border:1px solid #e2e8f0;">
            <tr style="background-color:#f1f5f9;">
                <td width="50%"><strong>Client Attendees</strong></td>
                <td width="50%"><strong>MSP Attendees</strong></td>
            </tr>
            <tr>
                <td>' . (!empty($att_client) ? implode('<br>', $att_client) : 'None listed') . '</td>
                <td>' . (!empty($att_msp) ? implode('<br>', $att_msp) : escapeHtml($meeting['rep_name'] ?? 'MSP Team')) . '</td>
            </tr>
        </table>';
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->writeHTML($att_html, true, false, true, false, '');
    }

    $pdf->SetY(260);
    $pdf->SetFillColor(241, 245, 249);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->Cell(180, 9, $conf_label, 0, 1, 'C', true);

    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9.5);
    $pdf->SetTextColor(30, 41, 59);

    $html = tbrPdfSectionHeading('1', 'Executive Summary & Business Updates', 'Review of business momentum, staffing changes, strategic initiatives, and meeting highlights.');
    $exec_sum = trim((string)($meeting['tbr_executive_summary'] ?? ''));
    $html .= '<p>' . ($exec_sum !== '' ? nl2br(escapeHtml($exec_sum)) : '<em>No executive summary recorded.</em>') . '</p>';

    if ($is_internal && !empty(trim((string)$meeting['tbr_prep_notes']))) {
        $html .= tbrPdfInternalBox('Meeting Preparation Notes', (string)$meeting['tbr_prep_notes']);
    }

    $s1 = $responses['sec_01'] ?? [];
    $s1_ans = $s1['answers'] ?? [];
    $s1_fields = $sections_cfg['sec_01']['fields'] ?? [];
    $has_s1 = false;
    $s1_rows = '';
    foreach ($s1_fields as $fk => $flabel) {
        $val = trim((string)($s1_ans[$fk] ?? ''));
        if ($val !== '') {
            $has_s1 = true;
            $s1_rows .= '<tr><td width="28%" style="background-color:#f8fafc; font-weight:bold; border-bottom:1px solid #e2e8f0;">' . escapeHtml($flabel) . '</td><td width="72%" style="border-bottom:1px solid #e2e8f0;">' . nl2br(escapeHtml($val)) . '</td></tr>';
        }
    }
    if ($has_s1) {
        $html .= '<table cellpadding="5" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:9pt;">' . $s1_rows . '</table><br>';
    }
    if (!empty(trim((string)($s1['client_notes'] ?? '')))) {
        $html .= '<p><strong>Discussion Notes:</strong><br>' . nl2br(escapeHtml($s1['client_notes'])) . '</p>';
    }
    if ($is_internal && !empty(trim((string)($s1['internal_notes'] ?? '')))) {
        $html .= tbrPdfInternalBox('Section 01 Notes', (string)$s1['internal_notes']);
    }
    $pdf->writeHTML($html, true, false, true, false, '');

    $s2 = $responses['sec_02'] ?? [];
    $s2_ans = $s2['answers'] ?? [];
    $infra = $live_data['infrastructure'];
    $ac = $infra['asset_counts'];

    $html = tbrPdfSectionHeading('2', 'IT Environment Health', 'Overall infrastructure assessment combining synchronized ITFlow asset inventory and engineering review.');
    $html .= '<table cellpadding="6" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:9pt;">
        <tr style="background-color:#f1f5f9;">
            <td width="25%"><strong>Overall Health</strong><br>' . tbrPdfBadgeHtml($meeting['tbr_overall_health'] ?? 'Not Assessed') . '</td>
            <td width="15%" align="center"><strong>Total Assets</strong><br>' . intval($ac['total']) . '</td>
            <td width="15%" align="center"><strong>Workstations</strong><br>' . intval($ac['workstations']) . '</td>
            <td width="15%" align="center"><strong>Servers</strong><br>' . intval($ac['servers']) . '</td>
            <td width="15%" align="center"><strong>Network</strong><br>' . intval($ac['network']) . '</td>
            <td width="15%" align="center"><strong>Licenses</strong><br>' . intval($infra['software_counts']['total']) . '</td>
        </tr>
    </table><br>';

    $cat_rows = '';
    foreach ($sections_cfg['sec_02']['categories'] as $ck => $clabel) {
        $c = $s2_ans['categories'][$ck] ?? [];
        $health = $c['health'] ?? 'Not Assessed';
        $status = trim((string)($c['status'] ?? ''));
        $findings = trim((string)($c['findings'] ?? ''));
        $recs = trim((string)($c['recommendations'] ?? ''));
        if ($health !== 'Not Assessed' || $status !== '' || $findings !== '' || $recs !== '') {
            $cat_rows .= '<tr>
                <td width="24%" style="border-bottom:1px solid #e2e8f0;"><strong>' . escapeHtml($clabel) . '</strong></td>
                <td width="16%" style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($health) . '</td>
                <td width="30%" style="border-bottom:1px solid #e2e8f0;">' . ($status !== '' ? '<strong>Status:</strong> ' . escapeHtml($status) . '<br>' : '') . nl2br(escapeHtml($findings)) . '</td>
                <td width="30%" style="border-bottom:1px solid #e2e8f0;">' . nl2br(escapeHtml($recs)) . '</td>
            </tr>';
        }
    }
    if ($cat_rows !== '') {
        $html .= '<table cellpadding="5" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8.5pt;">
            <tr style="background-color:#e2e8f0; font-weight:bold;">
                <th width="24%">Infrastructure Area</th>
                <th width="16%">Health</th>
                <th width="30%">Status &amp; Findings</th>
                <th width="30%">Recommendations</th>
            </tr>' . $cat_rows . '</table><br>';
    }
    if (!empty(trim((string)($s2['client_notes'] ?? '')))) {
        $html .= '<p><strong>Environment Notes:</strong><br>' . nl2br(escapeHtml($s2['client_notes'])) . '</p>';
    }
    if ($is_internal && !empty(trim((string)($s2['internal_notes'] ?? '')))) {
        $html .= tbrPdfInternalBox('Section 02 Notes', (string)$s2['internal_notes']);
    }
    $pdf->writeHTML($html, true, false, true, false, '');

    $hd = $live_data['helpdesk'];
    $s3 = $responses['sec_03'] ?? [];
    $s3_ans = $s3['answers'] ?? [];

    $html = tbrPdfSectionHeading('3', 'Helpdesk & Support Performance', 'Ticket volume, response & resolution metrics, and SLA performance for ' . $period_text . '.');
    $html .= '<table cellpadding="6" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:9pt;">
        <tr style="background-color:#f8fafc;">
            <td width="16%" align="center"><strong>Opened</strong><br><span style="font-size:12pt;">' . intval($hd['tickets_opened']) . '</span></td>
            <td width="16%" align="center"><strong>Resolved</strong><br><span style="font-size:12pt;">' . intval($hd['tickets_resolved']) . '</span></td>
            <td width="16%" align="center"><strong>Outstanding</strong><br><span style="font-size:12pt;">' . intval($hd['tickets_outstanding']) . '</span></td>
            <td width="18%" align="center"><strong>Avg Response</strong><br><span style="font-size:11pt;">' . escapeHtml(tbrFormatMinutes($hd['avg_response_minutes'])) . '</span></td>
            <td width="18%" align="center"><strong>Avg Resolution</strong><br><span style="font-size:11pt;">' . escapeHtml(tbrFormatMinutes($hd['avg_resolution_minutes'])) . '</span></td>
            <td width="16%" align="center"><strong>SLA Met</strong><br><span style="font-size:11pt;">' . ($hd['sla_resolution_compliance_pct'] !== null ? $hd['sla_resolution_compliance_pct'] . '%' : 'N/A') . '</span></td>
        </tr>
    </table><br>';

    if (!empty($hd['top_categories'])) {
        $html .= '<table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8.5pt;">
            <tr style="background-color:#e2e8f0; font-weight:bold;"><th width="75%">Top Ticket Categories</th><th width="25%" align="right">Ticket Count</th></tr>';
        foreach ($hd['top_categories'] as $tc) {
            $html .= '<tr><td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($tc['category']) . '</td><td align="right" style="border-bottom:1px solid #e2e8f0;">' . intval($tc['count']) . '</td></tr>';
        }
        $html .= '</table><br>';
    }

    foreach ($sections_cfg['sec_03']['fields'] as $fk => $flabel) {
        $val = trim((string)($s3_ans[$fk] ?? ''));
        if ($val !== '') {
            $html .= '<p><strong>' . escapeHtml($flabel) . ':</strong><br>' . nl2br(escapeHtml($val)) . '</p>';
        }
    }
    if ($is_internal && !empty(trim((string)($s3['internal_notes'] ?? '')))) {
        $html .= tbrPdfInternalBox('Section 03 Notes', (string)$s3['internal_notes']);
    }
    $pdf->writeHTML($html, true, false, true, false, '');

    $s4 = $responses['sec_04'] ?? [];
    $s4_ans = $s4['answers'] ?? [];
    $sec4_findings = $findings_by_sec['sec_04'] ?? [];

    $html = tbrPdfSectionHeading('4', 'Cybersecurity Assessment', 'Security posture evaluation, identified risks, and immediate attention items.');
    $area_rows = '';
    foreach ($sections_cfg['sec_04']['areas'] as $ak => $alabel) {
        $area_st = $s4_ans['areas'][$ak]['status'] ?? '';
        $area_nt = $s4_ans['areas'][$ak]['notes'] ?? '';
        if ($area_st !== '' || trim((string)$area_nt) !== '') {
            $area_rows .= '<tr>
                <td width="35%" style="border-bottom:1px solid #e2e8f0;"><strong>' . escapeHtml($alabel) . '</strong></td>
                <td width="20%" style="border-bottom:1px solid #e2e8f0;">' . ($area_st !== '' ? tbrPdfBadgeHtml($area_st) : '-') . '</td>
                <td width="45%" style="border-bottom:1px solid #e2e8f0;">' . nl2br(escapeHtml((string)$area_nt)) . '</td>
            </tr>';
        }
    }
    if ($area_rows !== '') {
        $html .= '<table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8.5pt;">
            <tr style="background-color:#e2e8f0; font-weight:bold;"><th width="35%">Security Control Area</th><th width="20%">Status</th><th width="45%">Observations</th></tr>' . $area_rows . '</table><br>';
    }

    if (!empty($sec4_findings)) {
        $html .= '<table cellpadding="5" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8.5pt;">
            <tr style="background-color:#e2e8f0; font-weight:bold;">
                <th width="24%">Finding</th>
                <th width="12%">Risk</th>
                <th width="26%">Business Impact</th>
                <th width="26%">Recommended Action</th>
                <th width="12%">Target</th>
            </tr>';
        foreach ($sec4_findings as $f) {
            $vis_tag = ($is_internal && intval($f['finding_client_visible']) === 0) ? ' <span style="color:#991b1b;">[INTERNAL]</span>' : '';
            $imm_tag = intval($f['finding_immediate_attention']) === 1 ? '<br><span style="color:#b91c1c; font-weight:bold;">Immediate Attention</span>' : '';
            $html .= '<tr>
                <td style="border-bottom:1px solid #e2e8f0;"><strong>' . escapeHtml($f['finding_title']) . '</strong>' . $vis_tag . $imm_tag . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($f['finding_risk_level']) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . nl2br(escapeHtml((string)$f['finding_business_impact'])) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . nl2br(escapeHtml((string)$f['finding_recommended_action'])) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . (!empty($f['finding_target_date']) ? escapeHtml($f['finding_target_date']) : '-') . '</td>
            </tr>';
        }
        $html .= '</table><br>';
    }
    if (!empty(trim((string)($s4['client_notes'] ?? '')))) {
        $html .= '<p><strong>Cybersecurity Summary Notes:</strong><br>' . nl2br(escapeHtml($s4['client_notes'])) . '</p>';
    }
    if ($is_internal && !empty(trim((string)($s4['internal_notes'] ?? '')))) {
        $html .= tbrPdfInternalBox('Section 04 Notes', (string)$s4['internal_notes']);
    }
    $pdf->writeHTML($html, true, false, true, false, '');

    $s5 = $responses['sec_05'] ?? [];
    $s5_ans = $s5['answers'] ?? [];
    $html = tbrPdfSectionHeading('5', 'Backup & Disaster Recovery', 'Backup integrity, immutability, RPO/RTO alignment, and restoration testing.');

    $metric_rows = '';
    foreach ($sections_cfg['sec_05']['metrics'] as $mk => $mlabel) {
        $mv = trim((string)($s5_ans[$mk] ?? ''));
        if ($mv !== '') {
            $metric_rows .= '<tr><td width="40%" style="background-color:#f8fafc; font-weight:bold; border-bottom:1px solid #e2e8f0;">' . escapeHtml($mlabel) . '</td><td width="60%" style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($mv) . '</td></tr>';
        }
    }
    if ($metric_rows !== '') {
        $html .= '<table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8.5pt;">' . $metric_rows . '</table><br>';
    }
    foreach ($sections_cfg['sec_05']['fields'] as $fk => $flabel) {
        $val = trim((string)($s5_ans[$fk] ?? ''));
        if ($val !== '') {
            $html .= '<p><strong>' . escapeHtml($flabel) . ':</strong><br>' . nl2br(escapeHtml($val)) . '</p>';
        }
    }
    if ($is_internal && !empty(trim((string)($s5['internal_notes'] ?? '')))) {
        $html .= tbrPdfInternalBox('Section 05 Notes', (string)$s5['internal_notes']);
    }
    $pdf->writeHTML($html, true, false, true, false, '');

    $s6 = $responses['sec_06'] ?? [];
    $s6_ans = $s6['answers'] ?? [];
    $asset_overrides = $s6_ans['asset_reviews'] ?? [];
    $html = tbrPdfSectionHeading('6', 'Hardware & Software Lifecycle', 'Equipment aging, warranty status, software licensing, and replacement planning.');

    if (!empty($infra['assets'])) {
        $html .= '<table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8pt;">
            <tr style="background-color:#e2e8f0; font-weight:bold;">
                <th width="25%">Asset</th>
                <th width="16%">Type / OS</th>
                <th width="11%">Age</th>
                <th width="16%">Warranty</th>
                <th width="12%">Status</th>
                <th width="20%">Recommendation</th>
            </tr>';
        foreach (array_slice($infra['assets'], 0, 60) as $a) {
            $aid = strval($a['asset_id']);
            $rev = $asset_overrides[$aid] ?? [];
            $rec_txt = trim((string)($rev['recommendation'] ?? ''));
            if (!empty($rev['est_cost'])) {
                $rec_txt .= ($rec_txt !== '' ? ' | ' : '') . $formatMoney($rev['est_cost']);
            }
            $age_txt = $a['age_years'] !== null ? $a['age_years'] . ' yrs' : '-';
            $w_txt = !empty($a['asset_warranty_expire']) ? $a['asset_warranty_expire'] . ' (' . $a['warranty_status'] . ')' : $a['warranty_status'];
            $html .= '<tr>
                <td style="border-bottom:1px solid #e2e8f0;"><strong>' . escapeHtml($a['asset_name']) . '</strong><br><span style="color:#64748b;">' . escapeHtml(trim($a['asset_make'] . ' ' . $a['asset_model'])) . '</span></td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($a['asset_type']) . '<br><span style="color:#64748b;">' . escapeHtml((string)($a['asset_os'] ?? '')) . '</span></td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($age_txt) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($w_txt) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml((string)($a['asset_status'] ?? 'Active')) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . ($rec_txt !== '' ? escapeHtml($rec_txt) : '-') . '</td>
            </tr>';
        }
        $html .= '</table><br>';
    } else {
        $html .= '<p><em>No hardware assets recorded in ITFlow for this client.</em></p>';
    }
    if (!empty(trim((string)($s6['client_notes'] ?? '')))) {
        $html .= '<p><strong>Lifecycle Notes:</strong><br>' . nl2br(escapeHtml($s6['client_notes'])) . '</p>';
    }
    if ($is_internal && !empty(trim((string)($s6['internal_notes'] ?? '')))) {
        $html .= tbrPdfInternalBox('Section 06 Notes', (string)$s6['internal_notes']);
    }
    $pdf->writeHTML($html, true, false, true, false, '');

    $html = tbrPdfSectionHeading('7', 'Strategic Recommendations', 'Prioritized technology initiatives aligned to business goals and risk reduction.');
    if (!empty($recommendations)) {
        $html .= '<table cellpadding="5" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8.5pt;">
            <tr style="background-color:#e2e8f0; font-weight:bold;">
                <th width="24%">Recommendation</th>
                <th width="30%">Justification &amp; Risk Addressed</th>
                <th width="12%">Priority</th>
                <th width="14%">Est. Cost</th>
                <th width="10%">Timeline</th>
                <th width="10%">Status</th>
            </tr>';
        foreach ($recommendations as $rec) {
            $just = trim((string)$rec['recommendation_justification']);
            $risk = trim((string)$rec['recommendation_risk_addressed']);
            $detail = trim((string)$rec['recommendation_description']);
            $html .= '<tr>
                <td style="border-bottom:1px solid #e2e8f0;"><strong>' . escapeHtml($rec['recommendation_title']) . '</strong>' . ($detail !== '' ? '<br><span style="color:#475569;">' . nl2br(escapeHtml($detail)) . '</span>' : '') . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . ($just !== '' ? nl2br(escapeHtml($just)) : '-') . ($risk !== '' ? '<br><em>Risk: ' . escapeHtml($risk) . '</em>' : '') . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($rec['recommendation_priority']) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($formatMoney($rec['recommendation_estimated_cost'])) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($rec['recommendation_timeline'] ?: '-') . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($rec['recommendation_status']) . '</td>
            </tr>';
            if ($is_internal && !empty(trim((string)$rec['recommendation_internal_notes']))) {
                $html .= '<tr><td colspan="6" style="background-color:#fef2f2; color:#991b1b; font-size:8pt; border-bottom:1px solid #e2e8f0;"><strong>[INTERNAL]</strong> ' . nl2br(escapeHtml($rec['recommendation_internal_notes'])) . '</td></tr>';
            }
        }
        $html .= '</table><br>';
    } else {
        $html .= '<p><em>No strategic recommendations recorded for this review.</em></p>';
    }
    $pdf->writeHTML($html, true, false, true, false, '');

    $html = tbrPdfSectionHeading('8', '12-Month Technology Roadmap & Budget', 'Projected quarterly and annual technology investments.');
    $q_totals = ['Q1' => 0.0, 'Q2' => 0.0, 'Q3' => 0.0, 'Q4' => 0.0];
    $cat_totals = [];
    $annual_total = 0.0;
    foreach ($roadmap_items as $rm) {
        $cost = floatval($rm['roadmap_estimated_cost']);
        $q_totals[$rm['roadmap_quarter']] = ($q_totals[$rm['roadmap_quarter']] ?? 0) + $cost;
        $cat_totals[$rm['roadmap_category']] = ($cat_totals[$rm['roadmap_category']] ?? 0) + $cost;
        $annual_total += $cost;
    }

    $html .= '<table cellpadding="6" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:9pt;">
        <tr style="background-color:#f8fafc;">
            <td width="20%" align="center"><strong>Q1 Spend</strong><br>' . escapeHtml($formatMoney($q_totals['Q1'])) . '</td>
            <td width="20%" align="center"><strong>Q2 Spend</strong><br>' . escapeHtml($formatMoney($q_totals['Q2'])) . '</td>
            <td width="20%" align="center"><strong>Q3 Spend</strong><br>' . escapeHtml($formatMoney($q_totals['Q3'])) . '</td>
            <td width="20%" align="center"><strong>Q4 Spend</strong><br>' . escapeHtml($formatMoney($q_totals['Q4'])) . '</td>
            <td width="20%" align="center" style="background-color:#1e293b; color:#ffffff;"><strong>12-Month Total</strong><br>' . escapeHtml($formatMoney($annual_total)) . '</td>
        </tr>
    </table><br>';

    if (!empty($roadmap_items)) {
        $html .= '<table cellpadding="5" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8.5pt;">
            <tr style="background-color:#e2e8f0; font-weight:bold;">
                <th width="12%">Quarter</th>
                <th width="26%">Project / Investment</th>
                <th width="24%">Category</th>
                <th width="12%">Priority</th>
                <th width="14%" align="right">Est. Cost</th>
                <th width="12%">Status</th>
            </tr>';
        foreach ($roadmap_items as $rm) {
            $html .= '<tr>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($rm['roadmap_quarter'] . ' ' . $rm['roadmap_fiscal_year']) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;"><strong>' . escapeHtml($rm['roadmap_project_name']) . '</strong>' . (!empty($rm['roadmap_description']) ? '<br><span style="color:#475569;">' . nl2br(escapeHtml($rm['roadmap_description'])) . '</span>' : '') . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($rm['roadmap_category']) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($rm['roadmap_priority']) . '</td>
                <td align="right" style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($formatMoney($rm['roadmap_estimated_cost'])) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($rm['roadmap_status']) . '</td>
            </tr>';
        }
        $html .= '</table><br>';
    } else {
        $html .= '<p><em>No roadmap budget items recorded.</em></p>';
    }
    $pdf->writeHTML($html, true, false, true, false, '');

    $s9 = $responses['sec_09'] ?? [];
    $s9_ans = $s9['answers'] ?? [];
    $html = tbrPdfSectionHeading('9', 'Action Items & Next Review', 'Agreed follow-up tasks, ownership, target dates, and client feedback.');

    if (!empty($action_items)) {
        $html .= '<table cellpadding="5" cellspacing="0" width="100%" style="border:1px solid #cbd5e1; font-size:8.5pt;">
            <tr style="background-color:#e2e8f0; font-weight:bold;">
                <th width="34%">Action Item</th>
                <th width="12%">Party</th>
                <th width="18%">Owner</th>
                <th width="11%">Priority</th>
                <th width="12%">Due Date</th>
                <th width="13%">Status</th>
            </tr>';
        foreach ($action_items as $act) {
            $owner = $act['action_responsible_party'] === 'Client'
                ? ($act['action_client_owner'] ?: 'Client')
                : ($act['assigned_user_name'] ?: ($act['action_client_owner'] ?: 'MSP Team'));
            $html .= '<tr>
                <td style="border-bottom:1px solid #e2e8f0;">' . nl2br(escapeHtml($act['action_description'])) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($act['action_responsible_party']) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . escapeHtml($owner) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($act['action_priority']) . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . (!empty($act['action_due_date']) ? escapeHtml($act['action_due_date']) : 'TBD') . '</td>
                <td style="border-bottom:1px solid #e2e8f0;">' . tbrPdfBadgeHtml($act['action_status']) . '</td>
            </tr>';
            if ($is_internal && !empty(trim((string)$act['action_internal_notes']))) {
                $html .= '<tr><td colspan="6" style="background-color:#fef2f2; color:#991b1b; font-size:8pt; border-bottom:1px solid #e2e8f0;"><strong>[INTERNAL]</strong> ' . nl2br(escapeHtml($act['action_internal_notes'])) . '</td></tr>';
            }
        }
        $html .= '</table><br>';
    } else {
        $html .= '<p><em>No action items recorded.</em></p>';
    }

    foreach ($sections_cfg['sec_09']['fields'] as $fk => $flabel) {
        $val = trim((string)($s9_ans[$fk] ?? ''));
        if ($val !== '') {
            $html .= '<p><strong>' . escapeHtml($flabel) . ':</strong><br>' . nl2br(escapeHtml($val)) . '</p>';
        }
    }
    if ($is_internal && !empty(trim((string)($s9['internal_notes'] ?? '')))) {
        $html .= tbrPdfInternalBox('Section 09 Notes', (string)$s9['internal_notes']);
    }

    $next_rev = !empty($meeting['tbr_next_review_date']) ? date('F j, Y', strtotime($meeting['tbr_next_review_date'])) : 'To Be Scheduled';
    $html .= '<table cellpadding="8" cellspacing="0" width="100%" style="border:1px solid #1e293b; background-color:#f8fafc;">
        <tr>
            <td width="60%"><strong>Next Technology Business Review Date:</strong><br><span style="font-size:12pt; color:#0f172a;">' . escapeHtml($next_rev) . '</span></td>
            <td width="40%" align="right"><strong>Prepared By:</strong><br>' . escapeHtml($meeting['rep_name'] ?? 'MSP Team') . '</td>
        </tr>
    </table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    $ver_row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(report_version), 0) + 1 FROM tbr_reports WHERE report_tbr_id = $tbr_id AND report_type = '$mode'"));
    $next_ver = intval($ver_row[0] ?? 1);

    $rel_dir = "uploads/tbr/$client_id";
    $abs_dir = __DIR__ . "/../" . $rel_dir;
    if (!is_dir($abs_dir)) {
        mkdir($abs_dir, 0775, true);
    }

    $safe_client = sanitizeFilename($client['client_name'] ?? 'client');
    $filename = "TBR_{$tbr_id}_{$safe_client}_{$mode}_v{$next_ver}_" . date('Ymd') . ".pdf";
    $rel_path = $rel_dir . '/' . $filename;
    $abs_path = $abs_dir . '/' . $filename;

    $pdf->Output($abs_path, 'F');
    $filesize = file_exists($abs_path) ? filesize($abs_path) : 0;

    $fn_esc = escapeSql($filename);
    $fp_esc = escapeSql($rel_path);
    $uid = intval($user_id);

    mysqli_query($mysqli, "
        INSERT INTO tbr_reports SET
            report_tbr_id = $tbr_id,
            report_client_id = $client_id,
            report_type = '$mode',
            report_version = $next_ver,
            report_filename = '$fn_esc',
            report_filepath = '$fp_esc',
            report_filesize = $filesize,
            report_generated_by = $uid
    ");
    $report_id = mysqli_insert_id($mysqli);

    tbrLogAudit($tbr_id, $client_id, 'Report Generated', "Generated $mode PDF report v$next_ver ($filename)", $uid);

    return [
        'report_id' => $report_id,
        'report_tbr_id' => $tbr_id,
        'report_client_id' => $client_id,
        'report_type' => $mode,
        'report_version' => $next_ver,
        'report_filename' => $filename,
        'report_filepath' => $rel_path,
        'report_filesize' => $filesize,
    ];
}
