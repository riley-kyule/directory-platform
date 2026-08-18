import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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
