import QRCode from 'qrcode';

// Renders the check-in QR and keeps the attendance count live while it's on screen.
const el = document.getElementById('checkin-qr');
if (el) {
    const size = Math.min(window.innerWidth - 48, window.innerHeight - 260, 520);
    QRCode.toCanvas(el, el.dataset.url, {
        width: Math.max(size, 220),
        margin: 1,
        errorCorrectionLevel: 'M',
        color: { dark: '#0a1e24', light: '#ffffff' },
    });

    const countEl = document.getElementById('checkin-count');
    const statusUrl = el.dataset.statusUrl;
    if (countEl && statusUrl) {
        const refresh = async () => {
            try {
                const res = await fetch(statusUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) return;
                const data = await res.json();
                countEl.textContent = data.counts.attended;
                document.getElementById('checkin-closed')?.classList.toggle('hidden', data.checkin_open);
            } catch (e) {
                /* keep the last count on network hiccups */
            }
        };
        setInterval(refresh, 5000);
    }
}
