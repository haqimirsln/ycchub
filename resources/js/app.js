import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// An app left open on the home screen for days ends up with an old security
// token. On a 419, fetch a fresh one and try once more.
async function refreshCsrf() {
    try {
        const url = document.querySelector('meta[name="csrf-refresh"]')?.content;
        if (!url) return false;
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return false;
        const { token } = await res.json();
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && token) meta.setAttribute('content', token);
        return Boolean(token);
    } catch {
        return false;
    }
}

async function postJson(url, body = {}, retried = false) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    if (res.status === 419 && !retried && (await refreshCsrf())) {
        return postJson(url, body, true);
    }
    if (res.status === 419 || res.status === 401) {
        throw new Error('Your login session ran out. Close YCC Hub, open it again, then retry.');
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(data.message && !firstError ? data.message : firstError || data.message || 'Something went wrong. Try again.');
    }
    return data;
}

async function getJson(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('Could not load data.');
    return res.json();
}

window.yccToast = (message, type = 'success') => {
    window.dispatchEvent(new CustomEvent('ycc-toast', { detail: { message, type } }));
};

// Toast stack (flash messages and async results)
Alpine.data('toasts', (initial = []) => ({
    items: [],
    init() {
        initial.forEach((t) => this.push(t.message, t.type));
        window.addEventListener('ycc-toast', (e) => this.push(e.detail.message, e.detail.type));
    },
    push(message, type) {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });
        setTimeout(() => this.dismiss(id), type === 'error' ? 7000 : 4500);
    },
    dismiss(id) {
        this.items = this.items.filter((t) => t.id !== id);
    },
}));

// Exco attendance sheet: tap a row to mark attended / absent, add walk-ins.
Alpine.data('attendanceSheet', ({ rows, counts, updateUrl, searchUrl, editable }) => ({
    rows,
    counts,
    editable,
    filter: '',
    view: 'all',
    busy: {},
    walkIn: { open: false, q: '', results: [], loading: false },

    get visibleRows() {
        const q = this.filter.trim().toLowerCase();
        return this.rows.filter((r) => {
            if (this.view !== 'all' && r.status !== this.view) return false;
            if (!q) return true;
            return r.name.toLowerCase().includes(q) || r.matric.toLowerCase().includes(q);
        });
    },

    async mark(row, status) {
        if (!this.editable || this.busy[row.user_id]) return;
        const target = row.status === status ? 'registered' : status;
        const previous = row.status;
        row.status = target; // optimistic
        this.busy[row.user_id] = true;
        try {
            const data = await postJson(updateUrl, { user_id: row.user_id, status: target });
            Object.assign(row, data.row);
            this.counts = data.counts;
        } catch (e) {
            row.status = previous;
            window.yccToast(e.message, 'error');
        } finally {
            this.busy[row.user_id] = false;
        }
    },

    async search() {
        const q = this.walkIn.q.trim();
        if (q.length < 2) {
            this.walkIn.results = [];
            return;
        }
        this.walkIn.loading = true;
        try {
            const data = await getJson(`${searchUrl}?q=${encodeURIComponent(q)}`);
            this.walkIn.results = data.results;
        } catch (e) {
            window.yccToast(e.message, 'error');
        } finally {
            this.walkIn.loading = false;
        }
    },

    async addWalkIn(member) {
        try {
            const data = await postJson(updateUrl, { user_id: member.id, status: 'attended' });
            this.rows.unshift(data.row);
            this.counts = data.counts;
            this.walkIn = { open: false, q: '', results: [], loading: false };
            window.yccToast(`${member.name} checked in as a walk-in.`);
        } catch (e) {
            window.yccToast(e.message, 'error');
        }
    },
}));

// Program register / cancel without a page reload (falls back to normal form posts without JS).
Alpine.data('joinButton', ({ registered, registerUrl, cancelUrl }) => ({
    registered,
    loading: false,
    async toggle() {
        if (this.loading) return;
        if (this.registered && !confirm('Cancel your registration for this program?')) return;
        this.loading = true;
        try {
            const data = await postJson(this.registered ? cancelUrl : registerUrl);
            this.registered = !this.registered;
            window.yccToast(data.message);
            this.$dispatch('seat-changed', { delta: this.registered ? 1 : -1 });
        } catch (e) {
            window.yccToast(e.message, 'error');
        } finally {
            this.loading = false;
        }
    },
}));

// ---- Instant feedback on every click --------------------------------------
// A thin gold bar starts the moment a link or form is used, and the pressed
// button shows a spinner, so the site never feels stuck while the next page loads.
const progress = document.getElementById('nav-progress');
let progressTimer = null;

function startProgress() {
    if (!progress) return;
    clearTimeout(progressTimer);
    progress.classList.remove('is-done');
    void progress.offsetWidth; // restart the animation
    progress.classList.add('is-loading');
    // Safety net: never leave the bar hanging (e.g. a file download).
    progressTimer = setTimeout(finishProgress, 8000);
}

function finishProgress() {
    if (!progress) return;
    clearTimeout(progressTimer);
    progress.classList.remove('is-loading');
    progress.classList.add('is-done');
}

// "/ycc-hub" and "/ycc-hub/" are the same page.
const samePath = (url) => url.pathname.replace(/\/+$/, '') === location.pathname.replace(/\/+$/, '');

function isPlainNavigation(event, link) {
    if (event.defaultPrevented || event.button !== 0) return false;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.target && link.target !== '_self') return false;
    if (link.hasAttribute('download') || link.dataset.noProgress !== undefined) return false;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin) return false;
    if (url.pathname.includes('/export')) return false; // CSV downloads don't change page
    if (samePath(url) && url.search === location.search && url.hash) return false; // in-page anchor
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return false;
    return true;
}

// Links to a section on the same page glide there instead of reloading.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href*="#"]');
    if (!link || event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || (link.target && link.target !== '_self')) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !samePath(url) || url.search !== location.search || url.hash.length < 2) return;
    const target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
    if (!target) return;
    event.preventDefault();
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    if (location.hash !== url.hash) history.pushState(null, '', url.hash);
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (link && isPlainNavigation(event, link)) startProgress();
});

// Arriving from another page with a #section: glide to it once the page has settled.
window.addEventListener('load', () => {
    if (location.hash.length < 2) return;
    const target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (target && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    }
});

document.addEventListener('submit', (event) => {
    // Runs after inline onsubmit="return confirm(...)": a cancelled confirm leaves nothing busy.
    if (event.defaultPrevented) return;
    const form = event.target;
    if (form.target && form.target !== '_self') return;
    const button = event.submitter || form.querySelector('[type=submit]');
    if (button) button.classList.add('is-busy');
    startProgress();
});

// Coming back with the browser's back button restores the page from cache: reset everything.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        finishProgress();
        document.querySelectorAll('.is-busy').forEach((el) => el.classList.remove('is-busy'));
    }
});

// ---- Program form: where it happens -----------------------------------------
const whenMapReady = () => (window.YccMap
    ? Promise.resolve(window.YccMap)
    : new Promise((resolve) => window.addEventListener('ycc-map-ready', () => resolve(window.YccMap), { once: true })));

Alpine.data('wherePicker', ({ type, lat, lng, venue, linkUrl }) => {
    let pickerMap = null; // kept outside Alpine so Leaflet isn't wrapped in a proxy
    let venueTimer = null;

    return {
        type,
        lat,
        lng,
        venue,
        previewVenue: venue,
        link: '',
        linkBusy: false,
        linkError: '',

        get needsPlace() { return this.type !== 'online'; },
        get needsMeeting() { return this.type !== 'in_person'; },
        get hasPin() { return this.lat !== null && this.lng !== null; },
        get pinText() { return this.hasPin ? `${Number(this.lat).toFixed(5)}, ${Number(this.lng).toFixed(5)}` : ''; },
        // What Google Maps shows: the exact pin if there is one, otherwise a search for the venue.
        get mapQuery() { return this.hasPin ? `${this.lat},${this.lng}` : (this.previewVenue || '').trim(); },
        get embedUrl() {
            return `https://maps.google.com/maps?q=${encodeURIComponent(this.mapQuery)}&z=${this.hasPin ? 17 : 15}&output=embed`;
        },
        get searchUrl() {
            const q = (this.venue || '').trim();
            return q ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(q)}` : 'https://www.google.com/maps';
        },

        init() {
            // Wait until typing pauses before reloading the preview.
            this.$watch('venue', (v) => {
                clearTimeout(venueTimer);
                venueTimer = setTimeout(() => { this.previewVenue = v; }, 700);
            });
        },
        async mountMap() {
            const lib = await whenMapReady();
            if (pickerMap) { pickerMap.refresh(); return; }
            pickerMap = lib.picker(this.$refs.map, {
                lat: this.lat,
                lng: this.lng,
                onChange: ({ lat: a, lng: b }) => { this.lat = +a.toFixed(7); this.lng = +b.toFixed(7); },
            });
        },
        setPin(a, b) {
            this.lat = +Number(a).toFixed(7);
            this.lng = +Number(b).toFixed(7);
            pickerMap?.set(this.lat, this.lng);
        },
        clearPin() {
            this.lat = null;
            this.lng = null;
            pickerMap?.clear();
        },
        async useLink() {
            if (!this.link.trim() || this.linkBusy) return;
            this.linkBusy = true;
            this.linkError = '';
            try {
                const res = await fetch(linkUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ link: this.link }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'That link did not work.');
                this.setPin(data.lat, data.lng);
                this.link = '';
            } catch (e) {
                this.linkError = e.message;
            } finally {
                this.linkBusy = false;
            }
        },
    };
});

// ---- Phone notifications ----------------------------------------------------
// Turns Web Push on or off for this phone. On iPhone it only works once YCC Hub
// is added to the home screen and opened from there (iOS 16.4 or newer).
function keyToBytes(base64url) {
    const padded = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

async function swRegistration() {
    if (!('serviceWorker' in navigator)) return null;
    const ready = navigator.serviceWorker.ready;
    const timeout = new Promise((resolve) => setTimeout(() => resolve(null), 6000));
    return Promise.race([ready, timeout]);
}

Alpine.data('pushToggle', ({ keyUrl, subscribeUrl, unsubscribeUrl, testUrl, devices = 0 }) => ({
    supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    needsInstall: false,
    permission: 'Notification' in window ? Notification.permission : 'denied',
    subscribed: false,
    checking: true,
    busy: false,
    devices,
    message: '',
    error: false,
    bannerHidden: safeStorage.get('ycc-push-banner') === 'hidden',
    hideBanner() {
        this.bannerHidden = true;
        safeStorage.set('ycc-push-banner', 'hidden');
    },
    async init() {
        const store = Alpine.store('app');
        this.needsInstall = store.platform === 'ios' && !store.standalone;
        if (!this.supported) {
            this.checking = false;
            return;
        }
        try {
            const reg = await swRegistration();
            const sub = reg ? await reg.pushManager.getSubscription() : null;
            this.subscribed = Boolean(sub) && this.permission === 'granted';
            // Make sure the server still knows this phone (it forgets phones that stopped answering).
            if (this.subscribed) {
                postJson(subscribeUrl, sub.toJSON()).then((d) => { this.devices = d.devices; }).catch(() => {});
            }
        } catch {
            /* leave it off */
        } finally {
            this.checking = false;
        }
    },
    say(text, isError = false) {
        this.message = text;
        this.error = isError;
    },
    async enable() {
        if (this.busy) return;
        this.busy = true;
        this.say('');
        try {
            this.permission = await Notification.requestPermission();
            if (this.permission !== 'granted') {
                this.say('Notifications are blocked for YCC Hub. Allow them in your phone’s settings, then try again.', true);
                return;
            }
            const reg = await swRegistration();
            if (!reg) throw new Error('The app isn’t ready yet. Reload the page and try again.');
            const { key } = await getJson(keyUrl);
            let sub = await reg.pushManager.getSubscription();
            if (sub) await sub.unsubscribe().catch(() => {});
            sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(key) });
            const data = await postJson(subscribeUrl, sub.toJSON());
            this.devices = data.devices;
            this.subscribed = true;
            this.say('On for this phone. You’ll get event reminders and new events here.');
        } catch (e) {
            this.say(e.message || 'Couldn’t turn notifications on. Try again.', true);
        } finally {
            this.busy = false;
        }
    },
    async disable() {
        if (this.busy) return;
        this.busy = true;
        this.say('');
        try {
            const reg = await swRegistration();
            const sub = reg ? await reg.pushManager.getSubscription() : null;
            if (sub) {
                await postJson(unsubscribeUrl, { endpoint: sub.endpoint }).then((d) => { this.devices = d.devices; }).catch(() => {});
                await sub.unsubscribe().catch(() => {});
            }
            this.subscribed = false;
            this.say('Off for this phone.');
        } finally {
            this.busy = false;
        }
    },
    async test() {
        if (this.busy) return;
        this.busy = true;
        this.say('');
        try {
            const data = await postJson(testUrl);
            this.say(data.message);
        } catch (e) {
            this.say(e.message, true);
        } finally {
            this.busy = false;
        }
    },
}));

// ---- Install as an app (PWA) ----------------------------------------------
// The service worker lives at the app's root so it works in a subfolder too.
const swMeta = document.querySelector('meta[name="ycc-sw"]');
if (swMeta && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(swMeta.content, { scope: swMeta.dataset.scope }).catch(() => {});
    });
}

const ua = navigator.userAgent;
const isIos = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isAndroid = /Android/i.test(ua);
const safeStorage = {
    get(key) { try { return localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch { /* private mode */ } },
};

Alpine.store('app', {
    platform: isIos ? 'ios' : (isAndroid ? 'android' : 'desktop'),
    // iPhone Safari is the only browser that can add to the home screen on iOS.
    iosNotSafari: isIos && /CriOS|FxiOS|EdgiOS|OPiOS|GSA\//.test(ua),
    standalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
    canPrompt: false,
    installed: false,
    deferred: null,
    bannerHidden: safeStorage.get('ycc-app-banner') === 'hidden',
    async install() {
        if (!this.deferred) return;
        this.deferred.prompt();
        const { outcome } = await this.deferred.userChoice;
        this.deferred = null;
        this.canPrompt = false;
        if (outcome === 'accepted') this.installed = true;
    },
    hideBanner() {
        this.bannerHidden = true;
        safeStorage.set('ycc-app-banner', 'hidden');
    },
});

// Chrome/Edge on Android and desktop offer a one-tap install; keep the offer for our button.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    const store = Alpine.store('app');
    store.deferred = event;
    store.canPrompt = true;
});
window.addEventListener('appinstalled', () => {
    const store = Alpine.store('app');
    store.installed = true;
    store.canPrompt = false;
});

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();
