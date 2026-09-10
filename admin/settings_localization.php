<?php
require_once "includes/inc_all_admin.php";


$sql = mysqli_query($mysqli,"SELECT company_currency, company_locale FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");

$row = mysqli_fetch_assoc($sql);
$company_locale = escapeHtml($row['company_locale']);
$company_currency = escapeHtml($row['company_currency']);

// Get a list of all available timezones
$timezones = DateTimeZone::listIdentifiers();

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-globe mr-2"></i>Localization</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>Language <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-language"></i></span>
                        </div>
                        <select class="form-control select2" name="locale" required>
                            <option value="">- Select a Locale -</option>
                            <?php foreach($locales_array as $locale_code => $locale_name) { ?>
                                <option <?php if ($company_locale == $locale_code) { echo "selected"; } ?> value="<?= $locale_code ?>"><?= $locale_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Currency <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                        </div>
                        <select class="form-control select2" name="currency_code" required>
                            <option value="">- Currency -</option>
                            <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                                <option <?php if ($company_currency == $currency_code) { echo "selected"; } ?> value="<?= $currency_code ?>"><?= "$currency_code - $currency_name" ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Timezone <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-business-time"></i></span>
                        </div>
                        <select class="form-control select2" name="timezone" required>
                            <option value="">- Select a Timezone -</option>
                            <?php foreach ($timezones as $tz) { ?>
                                <option <?php if ($config_timezone == $tz) { echo "selected"; } ?> value="<?= $tz ?>"><?= $tz ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <hr>

                <div class="card card-outline card-primary mt-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-fw fa-map-marked-alt mr-2"></i>Address Autocomplete (Google Places API)</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Google Maps / Places API Key</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-fw fa-google"></i></span>
                                </div>
                                <input type="password" class="form-control" data-toggle="password" name="google_places_api_key" id="google_places_api_key" placeholder="AIzaSy..." maxlength="255" value="<?= escapeHtml($config_google_places_api_key ?? '') ?>" autocomplete="new-password">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    <button type="button" class="btn btn-outline-info" id="btn_test_google_key">
                                        <i class="fas fa-vial mr-1"></i>Test Key
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                Used to automatically suggest and auto-populate street addresses, city, state, postal code, and country when adding clients, leads, locations, and company settings.
                            </small>
                            <div id="google_api_test_result" class="mt-2" style="display:none;"></div>
                        </div>

                        <div class="callout callout-info mt-3">
                            <h5><i class="fas fa-info-circle mr-2"></i>Google Cloud Setup Instructions</h5>
                            <p class="mb-1 text-sm">To enable address autocomplete, obtain an API key from the <a href="https://console.cloud.google.com/google/maps-apis" target="_blank" rel="noopener noreferrer">Google Cloud Console <i class="fas fa-external-link-alt fa-xs"></i></a>:</p>
                            <ol class="mb-1 pl-3 text-sm">
                                <li>Enable both the <strong>Maps JavaScript API</strong> and <strong>Places API</strong> for your Google Cloud project.</li>
                                <li>Create an API key under <strong>Credentials</strong>.</li>
                                <li><em>(Recommended for security)</em> Restrict the API key to <strong>Websites (HTTP referrers)</strong> with your ITFlow domain (e.g., <code>https://<?= $_SERVER['HTTP_HOST'] ?>/*</code>).</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <button type="submit" name="edit_localization" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save Settings</button>

            </form>
        </div>
    </div>

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
