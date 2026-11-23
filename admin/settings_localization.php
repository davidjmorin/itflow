<?php

/*
 * Admin > Settings > Localization - the language dates and numbers are
 * written in and the currency you bill in (the company's), and the timezone
 * ITFlow keeps time in - with money and the time now as they will look
 * (js/settings.js). Saved by edit_localization (admin/post/settings_localization.php).
 */

require_once "includes/inc_all_admin.php";

$company = settingsCurrent(['company_locale', 'company_currency'], 'companies');
$localization = settingsLocalizationArrays();

$locales = ['' => '- Language -'] + $localization['locales'];
$currencies = ['' => '- Currency -'];
foreach ($localization['currencies'] as $code => $name) {
    $currencies[$code] = "$code - $name";
}
$timezones = ['' => '- Timezone -'];
foreach (DateTimeZone::listIdentifiers() as $timezone) {
    $timezones[$timezone] = $timezone;
}

echo settingsPageStart('localization');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form data-settings-sample-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('language', 'Language and currency', 'How numbers, money and dates are written - on screen, on invoices and in emails - and the currency new clients, invoices and quotes start in.') ?>
                <?= settingsSelect('company_locale', (string) $company['company_locale'], $locales, ['icon' => 'fa-language', 'required' => true, 'search' => true]) ?>
                <?= settingsSelect('company_currency', (string) $company['company_currency'], $currencies, ['icon' => 'fa-money-bill', 'required' => true, 'search' => true,
                    'help' => 'What is already entered keeps the currency it was entered in - nothing is converted.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('timezone', 'Timezone', 'The clock ITFlow keeps - when tickets are due, when cron runs the nightly jobs, and the times on everything.') ?>
                <?= settingsSelect('config_timezone', (string) $config_timezone, $timezones, ['icon' => 'fa-business-time', 'required' => true, 'search' => true]) ?>
                <div class="itflow-settings-sample mt-3" data-settings-sample>
                    <span class="text-body-secondary">Looks like</span>
                    <span data-settings-sample-money><?= escapeHtml(numfmt_format_currency(numfmt_create((string) $company['company_locale'] ?: 'en_US', NumberFormatter::CURRENCY), 1234.56, (string) $company['company_currency'] ?: 'USD')) ?></span>
                    <span data-settings-sample-date><?= escapeHtml(date('l, F j, Y g:i A')) ?></span>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_localization') ?>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const testBtn = document.getElementById('btn_test_google_key');
    const keyInput = document.getElementById('google_places_api_key');
    const resultDiv = document.getElementById('google_api_test_result');

    if (!testBtn || !keyInput || !resultDiv) return;

    testBtn.addEventListener('click', function() {
        const apiKey = keyInput.value.trim();
        if (!apiKey) {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="alert alert-warning py-2 mb-0"><i class="fas fa-exclamation-triangle mr-2"></i>Please enter an API key first.</div>';
            return;
        }

        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin mr-2"></i>Testing Google Places API key...</div>';

        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

        $.ajax({
            url: '/agent/ajax.php',
            method: 'POST',
            data: {
                test_google_places_key: '1',
                api_key: apiKey,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fas fa-check-circle mr-2"></i><strong>Success:</strong> ' + data.message + '</div>';
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-times-circle mr-2"></i><strong>Test Failed:</strong> ' + data.message + '<br><small>Ensure <strong>Places API (New)</strong> is enabled in your Google Cloud project and billing is active.</small></div>';
                }
            },
            error: function(xhr, status, err) {
                resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-times-circle mr-2"></i>Error communicating with server: ' + err + '</div>';
            }
        });
    });
});
</script>

<?php
require_once "../includes/footer.php";
