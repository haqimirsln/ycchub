import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// "Drop the pin yourself" map on the program form, for spots Google Maps has no
// name for. Loaded only on that page. Tiles come from OpenStreetMap (no API key).

const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
// Universiti Malaysia Terengganu, so a new map starts somewhere familiar.
const HOME = [5.4097, 103.087];

const pin = L.divIcon({
    className: 'ycc-pin',
    html: '<svg viewBox="0 0 32 42" width="32" height="42" aria-hidden="true"><path d="M16 1C7.7 1 1 7.6 1 15.8 1 27 16 41 16 41s15-14 15-25.2C31 7.6 24.3 1 16 1z" fill="#0a1e24" stroke="#d9aa2e" stroke-width="2"/><circle cx="16" cy="15.5" r="5.5" fill="#d9aa2e"/></svg>',
    iconSize: [32, 42],
    iconAnchor: [16, 41],
});

function baseMap(el, options = {}) {
    const map = L.map(el, { zoomControl: true, attributionControl: true, ...options });
    L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);
    map.attributionControl.setPrefix(false);
    return map;
}

/** Tap the map or drag the pin to set the place. */
function picker(el, { lat, lng, onChange }) {
    const hasPoint = lat !== null && lng !== null;
    const map = baseMap(el).setView(hasPoint ? [lat, lng] : HOME, hasPoint ? 16 : 9);
    let marker = null;

    const place = (latlng, { pan = false, zoom = null } = {}) => {
        if (!marker) {
            marker = L.marker(latlng, { icon: pin, draggable: true, autoPan: true }).addTo(map);
            marker.on('dragend', () => onChange(marker.getLatLng()));
        } else {
            marker.setLatLng(latlng);
        }
        if (zoom) map.setView(latlng, zoom);
        else if (pan) map.panTo(latlng);
    };

    if (hasPoint) place([lat, lng]);
    map.on('click', (e) => { place(e.latlng); onChange(e.latlng); });

    return {
        set(latNew, lngNew, zoom = 16) { place([latNew, lngNew], { zoom }); },
        clear() { if (marker) { map.removeLayer(marker); marker = null; } },
        refresh() { map.invalidateSize(); },
    };
}

window.YccMap = { picker };
window.dispatchEvent(new Event('ycc-map-ready'));
