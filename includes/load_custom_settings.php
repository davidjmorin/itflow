<?php

$custom_settings_sql = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1 LIMIT 1");
$custom_settings_row = ($custom_settings_sql && mysqli_num_rows($custom_settings_sql) > 0) ? mysqli_fetch_assoc($custom_settings_sql) : [];

$config_google_places_api_key = $custom_settings_row['config_google_places_api_key'] ?? '';

$config_smsgate_enable = intval($custom_settings_row['config_smsgate_enable'] ?? 0);
$config_smsgate_url = $custom_settings_row['config_smsgate_url'] ?? 'https://api.sms-gate.app/3rdparty/v1/messages';
$config_smsgate_username = $custom_settings_row['config_smsgate_username'] ?? '';
$config_smsgate_password = $custom_settings_row['config_smsgate_password'] ?? '';
$config_smsgate_emergency_recipients = $custom_settings_row['config_smsgate_emergency_recipients'] ?? '';
$config_smsgate_ack_message = $custom_settings_row['config_smsgate_ack_message'] ?? '';
$config_smsgate_urgent_message = $custom_settings_row['config_smsgate_urgent_message'] ?? '';

$config_tech_weekly_summary_enable = intval($custom_settings_row['config_tech_weekly_summary_enable'] ?? 0);
$config_tech_weekly_summary_time = $custom_settings_row['config_tech_weekly_summary_time'] ?? '08:00';
$config_tech_weekly_summary_last_sent_week = $custom_settings_row['config_tech_weekly_summary_last_sent_week'] ?? '';

$config_email_header = $custom_settings_row['config_email_header'] ?? '';
$config_email_footer = $custom_settings_row['config_email_footer'] ?? '';
$config_client_onboarding_email_subject = $custom_settings_row['config_client_onboarding_email_subject'] ?? '';
$config_client_onboarding_email_body = $custom_settings_row['config_client_onboarding_email_body'] ?? '';
$config_ticket_client_email_subject = $custom_settings_row['config_ticket_client_email_subject'] ?? '';
$config_ticket_client_email_body = $custom_settings_row['config_ticket_client_email_body'] ?? '';
