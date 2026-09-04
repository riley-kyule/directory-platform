import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Registered here (not an inline <script> in the blade component) because the
// app's CSP is `script-src 'self' 'unsafe-eval'` with no 'unsafe-inline' — an
// inline <script> block is silently dropped by the browser, which left the
// upload button unlabelled and unresponsive with x-data="mediaManager(...)"
// never resolving. Alpine.data() is exactly this: a named factory usable from
// any x-data="mediaManager(...)" expression, served from this same-origin bundle.
Alpine.data('mediaManager', (config) => ({
    ...config,
    uploading: false,
    errors: [],
    progressLabel: '',
    pollTimer: null,

    init() {
        if (this.pollForVideo) {
            this.pollTimer = window.setTimeout(() => window.location.reload(), 9000);
        }
    },

    async upload(kind, fileList) {
        const files = Array.from(fileList || []);
        if (!files.length || this.uploading) return;

        const isPhoto = kind === 'photo';
        const url = isPhoto ? this.photoUrl : this.videoUrl;
        const field = isPhoto ? 'image' : 'video';
        const maxKb = isPhoto ? this.photoMaxKb : this.videoMaxKb;
        const slots = isPhoto ? this.photoSlotsLeft : this.videoSlotsLeft;
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        this.errors = [];
        if (files.length > slots) {
            this.errors.push(`Only ${slots} more ${isPhoto ? 'photo' : 'video'} slot(s) — the rest were skipped.`);
            files.length = slots;
        }
        if (!files.length) return;

        this.uploading = true;
        let added = 0;
        for (let i = 0; i < files.length; i++) {
            this.progressLabel = `Uploading ${isPhoto ? 'photo' : 'video'} ${i + 1} of ${files.length}…`;
            const file = files[i];
            if (file.size > maxKb * 1024) {
                this.errors.push(`${file.name}: ${Math.ceil(file.size / 1048576)} MB is over the ${Math.floor(maxKb / 1024)} MB limit.`);
                continue;
            }
            try {
                const body = new FormData();
                body.append(field, file);
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                });
                if (response.status === 429) {
                    this.errors.push('Too many uploads at once — wait a minute and add the rest.');
                    break;
                }
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const detail = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Upload failed.');
                    this.errors.push(`${file.name}: ${detail}`);
                    continue;
                }
                added++;
            } catch (e) {
                this.errors.push(`${file.name}: the upload could not be completed.`);
            }
        }

        this.uploading = false;
        this.progressLabel = '';
        if (added > 0) {
            window.location.reload();
        }
    },
}));

Alpine.start();

if ('serviceWorker' in navigator && window.isSecureContext && document.querySelector('link[rel="manifest"]')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', {
            scope: '/',
            updateViaCache: 'none',
        }).catch(() => {});
    });
}

const profileViewEndpoint = document.querySelector('meta[name="profile-view-endpoint"]')?.content;
const profileViewId = document.querySelector('meta[name="profile-view-id"]')?.content;

if (profileViewEndpoint && profileViewId) {
    const payload = new URLSearchParams({ profile: profileViewId });

    if (navigator.sendBeacon) {
        navigator.sendBeacon(profileViewEndpoint, payload);
    } else {
        fetch(profileViewEndpoint, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        }).catch(() => {});
    }
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-conversion]');
    if (!link) return;

    const endpoint = document.querySelector('meta[name="conversion-endpoint"]')?.content;
    if (!endpoint) return;

    const payload = new URLSearchParams({
        profile: link.dataset.profile,
        channel: link.dataset.channel,
        placement: link.dataset.placement,
    });

    if (navigator.sendBeacon) {
        navigator.sendBeacon(endpoint, payload);
        return;
    }

    fetch(endpoint, {
        method: 'POST',
        body: payload,
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
    }).catch(() => {});
});
