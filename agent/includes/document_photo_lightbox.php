<div class="modal fade" id="docPhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-light fw-bold" id="docPhotoModalTitle">Photo Preview</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <img id="docPhotoModalImg" src="" alt="Full preview" class="img-fluid rounded" style="max-height: 80vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('click', function (e) {
    const target = e.target.closest('.onboarding-photo-thumbnail, .onboarding-doc-gallery a');
    if (!target) return;
    e.preventDefault();
    const img = target.querySelector('img');
    const src = target.getAttribute('href') || (img ? img.getAttribute('src') : '');
    const title = (img && img.getAttribute('alt')) || target.getAttribute('title') || 'Site Photo';
    const modalEl = document.getElementById('docPhotoModal');
    if (src && modalEl && window.bootstrap) {
        document.getElementById('docPhotoModalImg').setAttribute('src', src);
        document.getElementById('docPhotoModalTitle').textContent = title;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
});
</script>
