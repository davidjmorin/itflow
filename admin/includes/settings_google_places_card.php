<?= settingsSectionStart('google_places', 'Address Autocomplete (Google Places API)', 'Suggests and populates street address, city, state/province, postal code, and country across client, lead, location, and company forms.') ?>
    <div class="mb-3">
        <label class="form-label" for="google_places_api_key">Google Maps / Places API Key</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fab fa-fw fa-google"></i></span>
            <input type="password" class="form-control" data-toggle="password" name="google_places_api_key" id="google_places_api_key" placeholder="AIzaSy..." maxlength="255" value="<?= escapeHtml($config_google_places_api_key ?? '') ?>" autocomplete="new-password">
            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
            <button type="button" class="btn btn-outline-info" id="btn_test_google_key">
                <i class="fas fa-vial me-1"></i>Test Key
            </button>
        </div>
        <div class="form-text">Enable <strong>Maps JavaScript API</strong> and <strong>Places API (New)</strong> in Google Cloud Console and restrict by HTTP referrer (<code>https://<?= escapeHtml($_SERVER['HTTP_HOST'] ?? '') ?>/*</code>).</div>
        <div id="google_api_test_result" class="mt-2 d-none"></div>
    </div>
<?= settingsSectionEnd() ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const testBtn = document.getElementById('btn_test_google_key');
    const keyInput = document.getElementById('google_places_api_key');
    const resultDiv = document.getElementById('google_api_test_result');
    if (!testBtn || !keyInput || !resultDiv) return;

    testBtn.addEventListener('click', function () {
        const apiKey = keyInput.value.trim();
        resultDiv.classList.remove('d-none');
        if (!apiKey) {
            resultDiv.innerHTML = '<div class="alert alert-warning py-2 mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Please enter an API key first.</div>';
            return;
        }
        resultDiv.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-2"></i>Testing Google Places API key...</div>';
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const body = new URLSearchParams({
            test_google_places_key: '1',
            api_key: apiKey,
            csrf_token: csrfInput ? csrfInput.value : ''
        });
        fetch('/agent/ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fas fa-check-circle me-2"></i><strong>Success:</strong> ' + (data.message || '') + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-times-circle me-2"></i><strong>Test Failed:</strong> ' + (data.message || 'Unknown error') + '</div>';
            }
        })
        .catch(err => {
            resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-times-circle me-2"></i>Error communicating with server: ' + err.message + '</div>';
        });
    });
});
</script>
