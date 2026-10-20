// Page de recherche : profil retenu, suggestions d'adresses, position, carte des stations
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const PROFILE_KEY = 'detour:profil';

/* ---------- Profil du plein (carburant, litres, consommation, temps) retenu sur l'appareil ---------- */
function rememberProfile(form) {
    const fields = [...form.querySelectorAll('[data-profile]')];
    const params = new URLSearchParams(location.search);
    let saved = {};
    try {
        saved = JSON.parse(localStorage.getItem(PROFILE_KEY) ?? '{}');
    } catch {
        // stockage indisponible : on garde les valeurs par défaut
    }

    // Sans recherche en cours, on reprend le dernier profil
    if (!params.has('carburant')) {
        for (const field of fields) {
            if (!(field.name in saved)) continue;
            if (field.type === 'radio') field.checked = field.value === saved[field.name];
            else field.value = saved[field.name];
        }
    }

    form.addEventListener('change', () => {
        const profile = {};
        for (const field of fields) {
            if (field.type !== 'radio' || field.checked) profile[field.name] = field.value;
        }
        try {
            localStorage.setItem(PROFILE_KEY, JSON.stringify(profile));
        } catch {
            // stockage plein ou bloqué : rien de grave
        }
    });
}

/* ---------- Suggestions d'adresses pendant la frappe ---------- */
function addressField(wrapper) {
    const input = wrapper.querySelector('input:not([type=hidden])');
    const [lat, lon] = wrapper.querySelectorAll('input[type=hidden]');
    const list = wrapper.querySelector('.suggestions');
    let timer;
    let controller;

    const close = () => {
        list.hidden = true;
        list.replaceChildren();
    };

    input.addEventListener('input', () => {
        // L'adresse change : les coordonnées choisies avant ne valent plus
        lat.value = '';
        lon.value = '';
        clearTimeout(timer);
        if (input.value.trim().length < 3) return close();

        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            try {
                const response = await fetch(`/adresses?q=${encodeURIComponent(input.value)}`, { signal: controller.signal });
                if (!response.ok) return close();
                const places = await response.json();
                list.replaceChildren(...places.map((place) => {
                    const li = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = place.label;
                    button.addEventListener('click', () => {
                        input.value = place.label;
                        lat.value = place.lat;
                        lon.value = place.lon;
                        close();
                    });
                    li.append(button);
                    return li;
                }));
                list.hidden = places.length === 0;
            } catch (error) {
                if (error.name !== 'AbortError') close();
            }
        }, 250);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });
    document.addEventListener('click', (event) => {
        if (!wrapper.contains(event.target)) close();
    });
}

/* ---------- Ma position ---------- */
function locateButton(button) {
    const wrapper = button.closest('[data-address]');
    const input = wrapper.querySelector('input:not([type=hidden])');
    const [lat, lon] = wrapper.querySelectorAll('input[type=hidden]');

    if (!('geolocation' in navigator)) {
        button.hidden = true;
        return;
    }

    button.addEventListener('click', () => {
        button.disabled = true;
        button.textContent = '📍 Localisation…';
        navigator.geolocation.getCurrentPosition(
            (position) => {
                lat.value = position.coords.latitude.toFixed(6);
                lon.value = position.coords.longitude.toFixed(6);
                input.value = 'Ma position';
                button.textContent = '📍 Ma position';
                button.disabled = false;
            },
            () => {
                button.textContent = '📍 Position refusée';
                button.disabled = false;
                input.focus();
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
        );
    });
}

/* ---------- Carte ---------- */
const COLORS = { best: '#3ddc84', good: '#9be7b4', reference: '#ffc93c', bad: '#ff7b6b' };

function stationMap(element, data) {
    const map = L.map(element, { scrollWheelZoom: false });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">contributeurs OpenStreetMap</a>',
    }).addTo(map);

    const points = [];
    const pin = (place, text) => {
        L.marker([place.lat, place.lon], {
            icon: L.divIcon({ className: 'pin', html: `<span>${text}</span>`, iconSize: [30, 30], iconAnchor: [15, 15] }),
        }).addTo(map).bindTooltip(place.label);
        points.push([place.lat, place.lon]);
    };
    pin(data.start, 'A');
    if (data.destination) pin(data.destination, 'B');

    // Les moins intéressantes d'abord, pour que la meilleure soit dessinée au-dessus
    for (const station of [...data.stations].reverse()) {
        const marker = L.circleMarker([station.lat, station.lon], {
            radius: station.kind === 'best' ? 11 : 7,
            color: '#14171c',
            weight: 2,
            fillColor: COLORS[station.kind],
            fillOpacity: 1,
        }).addTo(map);
        const popup = document.createElement('div');
        popup.className = 'popup';
        const title = document.createElement('strong');
        title.textContent = station.label;
        popup.append(title, document.createTextNode(` ${station.price} · ${station.detour} · ${station.gain}`));
        marker.bindPopup(popup);
        marker.on('click', () => {
            const item = document.querySelector(`[data-station="${CSS.escape(station.id)}"]`);
            item?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            item?.classList.add('flash');
            setTimeout(() => item?.classList.remove('flash'), 1200);
        });
        points.push([station.lat, station.lon]);
    }

    map.fitBounds(points, { padding: [30, 30], maxZoom: 15 });
}

const form = document.querySelector('[data-search-form]');
if (form) {
    rememberProfile(form);
    form.querySelectorAll('[data-address]').forEach(addressField);
    form.querySelectorAll('[data-locate]').forEach(locateButton);
}

const mapElement = document.getElementById('map');
const mapData = document.getElementById('map-data');
if (mapElement && mapData) {
    stationMap(mapElement, JSON.parse(mapData.textContent));
}
