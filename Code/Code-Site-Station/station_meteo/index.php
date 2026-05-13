<?php
/**
 * index.php — CLM Station Météo
 * Dashboard principal — lit data.json toutes les 5 secondes via fetch()
 * Déposer dans : /var/www/html/meteo/
 */

$data = [];
$dataFile = __DIR__ . '/data.json';
if (file_exists($dataFile)) {
    $raw = file_get_contents($dataFile);
    $data = json_decode($raw, true) ?? [];
}

function val($data, $key, $default = '--') {
    return isset($data[$key]) && $data[$key] !== null ? $data[$key] : $default;
}

function aqiLabel($aqi) {
    if ($aqi === '--') return '';
    $aqi = (int)$aqi;
    if ($aqi <= 50)  return 'Bonne';
    if ($aqi <= 100) return 'Modérée';
    if ($aqi <= 150) return 'Mauvaise pour groupes sensibles';
    if ($aqi <= 200) return 'Mauvaise';
    return 'Très mauvaise';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CLM Station Météo</title>
    <link rel="icon" type="image/png" href="meteo/flavicon.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #ffffff;
            --surface:   #fafafa;
            --border:    rgba(255,255,255,0.07);
            --accent:    #00c9a7;
            --accent2:   #3b82f6;
            --warn:      #f59e0b;
            --danger:    #ef4444;
            --text:      #000000;
            --muted:     #000000;
            --font-head: 'Bebas Neue', sans-serif;
            --font-body: 'DM Sans', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body);
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 40% at 20% 20%, rgba(0,201,167,0.06) 0%, transparent 70%),
                radial-gradient(ellipse 50% 50% at 80% 80%, rgba(59,130,246,0.06) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        #background-video {
            position: fixed;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .wrap {
            position: relative;
            width: 100%;
            padding: 0 0 48px;
        }

        /* ── Header ── */
        header {
            padding: 15px 24px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            margin-bottom: 32px;
            position: relative;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }

        .logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-text p {
            font-size: 1.75rem;
            color: var(--muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .header-center {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
        }

        .header-center h1 {
            font-family: var(--font-head);
            font-size: 5.0rem;
            letter-spacing: 2px;
            line-height: 1;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        #clock {
            font-family: var(--font-head);
            font-size: 3.7rem;
            letter-spacing: 1px;
            color: var(--text);
            line-height: 1;
        }

        #date-display {
            color: var(--muted);
            margin-top: 5px;
            text-transform: capitalize;
            font-size: 1.5rem;
        }

        /* ── Status bar ── */
        .status-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 28px;
            padding: 0 24px;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(0.8); }
        }

        #last-update { color: var(--text); font-weight: 500; }

        /* ── Grid ── */
        .grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 40px;
            padding: 0 24px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 46px 42px;
            position: relative;
            overflow: hidden;
            transition: border-color 0.3s, transform 0.2s;
            width: 370px;
            flex-shrink: 0;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 20px;
            background: linear-gradient(90deg, transparent, var(--card-accent, var(--accent)), transparent);
            opacity: 0.9;
        }

        .card-label {
            font-size: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .card-value {
            font-family: var(--font-head);
            font-size: 3.5rem;
            letter-spacing: 1px;
            color: var(--card-accent, var(--accent));
        }

        .card-unit {
            font-family: var(--font-body);
            font-size: 2.4rem;
            font-weight: 300;
            color: var(--muted);
            margin-left: 4px;
        }

        .card-sub {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 8px;
        }

        /* ── Min / Max ── */
        .minmax {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(0,0,0,0.07);
        }

        .minmax-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .minmax-arrow {
            font-size: 1.20rem;
            line-height: 1;
        }

        .minmax-min .minmax-arrow { color: #38bdf8; }
        .minmax-max .minmax-arrow { color: #f91616; }

        .minmax-val {
            font-family: var(--font-head);
            font-size: 1.15rem;
            letter-spacing: 0.5px;
        }

        .minmax-min .minmax-val { color: #38bdf8; }
        .minmax-max .minmax-val { color: #f97316; }

        .minmax-unit {
            font-size: 0.70rem;
            color: var(--muted);
            opacity: 0.6;
        }

        .minmax-sep {
            width: 1px;
            height: 16px;
            background: rgba(0,0,0,0.12);
        }

        .card-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 28px;
            opacity: 5.50;
            margin-top: 15px;
        }

        .card-temp   { --card-accent: #f97316; }
        .card-hum    { --card-accent: #38bdf8; }
        .card-pluie  { --card-accent: #818cf8; }
        .card-vent   { --card-accent: #34d399; }
        .card-aqi    { --card-accent: var(--aqi-color, #00c9a7); }
        .card-lux    { --card-accent: #fbbf24; }
        .card-dir    { --card-accent: #fb7185; }

        .aqi-bar-wrap {
            margin-top: 12px;
            background: rgba(255,255,255,0.06);
            border-radius: 4px;
            height: 4px;
            overflow: hidden;
        }

        .aqi-bar {
            height: 100%;
            border-radius: 4px;
            width: 0%;
            transition: width 0.8s ease;
            background: var(--aqi-color, var(--accent));
        }

        .compass {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 1px solid var(--border);
            margin-top: 8px;
            position: relative;
        }

        .compass-needle {
            width: 2px;
            height: 28px;
            background: linear-gradient(to bottom, #fb7185 50%, var(--muted) 50%);
            border-radius: 2px;
            transform-origin: center center;
            transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .img-station {

        }

        /* ── Carte image ── */
        .card-img {
            padding: 0;
            overflow: hidden;
            min-height: 200px;
        }

        .card-img::before {
            display: none;
        }

        .card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 16px;
        }

        footer {
            margin-top: 48px;
            padding-top: 24px;
            text-align: center;
            font-size: 0.90rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .donne {
            font-size: 0.70rem;
            color: #818cf8;
        }

        @media (max-width: 600px) {
            header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .header-center { position: static; transform: none; text-align: left; }
            #clock { font-size: 2rem; }
            .card { width: 100%; }
            .card-value { font-size: 2.2rem; }
        }
    </style>
</head>
<body>

<video autoplay muted loop id="background-video" playsinline>
    <source src="meteo/meteo.mp4" type="video/mp4">
</video>

<div class="wrap">

    <header>
        <!-- Logo — gauche -->
        <div class="logo">
            <div class="logo-icon"><img src="meteo/logo.png" width="150"></div>
        </div>

        <!-- Titre — centre absolu -->
        <div class="header-center">
            <h1>CAMPUS LA MENNAIS MÉTÉO</h1>
            <div class="logo-text">
                <p>Station en direct</p>
            </div>
        </div>

        <!-- Horloge — droite -->
        <div class="header-right">
            <div id="clock">--:--:--</div>
            <div id="date-display">--</div>
        </div>
    </header>

    <div class="grid">

        <!-- 1. Température -->
        <div class="card card-temp">
            <div class="card-icon">🌡</div>
            <div class="card-label">Température</div>
            <div class="card-value">
                <span id="temp"><?= val($data, 'temp') ?></span><span class="card-unit">°C</span>
            </div>
            <div class="card-sub" id="temp-feel">--</div>
            <div class="minmax">
                <div class="minmax-item minmax-min">
                    <span class="minmax-arrow">▼</span>
                    <span class="minmax-val" id="temp-min">--</span>
                    <span class="minmax-unit">°C</span>
                </div>
                <div class="minmax-sep"></div>
                <div class="minmax-item minmax-max">
                    <span class="minmax-arrow">▲</span>
                    <span class="minmax-val" id="temp-max">--</span>
                    <span class="minmax-unit">°C</span>
                </div>
            </div>
        </div>

        <!-- 2. Humidité -->
        <div class="card card-hum">
            <div class="card-icon">💧</div>
            <div class="card-label">Humidité</div>
            <div class="card-value">
                <span id="hum"><?= val($data, 'hum') ?></span><span class="card-unit">%</span>
            </div>
            <div class="minmax">
                <div class="minmax-item minmax-min">
                    <span class="minmax-arrow">▼</span>
                    <span class="minmax-val" id="hum-min">--</span>
                    <span class="minmax-unit">%</span>
                </div>
                <div class="minmax-sep"></div>
                <div class="minmax-item minmax-max">
                    <span class="minmax-arrow">▲</span>
                    <span class="minmax-val" id="hum-max">--</span>
                    <span class="minmax-unit">%</span>
                </div>
            </div>
        </div>

        <!-- 3. Qualité de l'air -->
        <div class="card card-aqi">
            <div class="card-icon">🍃</div>
            <div class="card-label">Qualité de l'air</div>
            <div class="card-value">
                <span id="aqi"><?= val($data, 'aqi') ?></span><span class="card-unit">AQI</span>
            </div>
            <div class="card-sub" id="aqi-label"><?= aqiLabel(val($data, 'aqi')) ?></div>
            <div class="aqi-bar-wrap">
                <div class="aqi-bar" id="aqi-bar"></div>
            </div>
            <p><div class="donne">0-50 👍 &nbsp; 151-200 👎</div></p>
            <p><div class="donne">La qualité de l’air, c’est à quel point l’air est propre ou pollué et s’il est bon ou mauvais pour la santé.</div></p>
        </div>
        <!-- 4. Luminosité -->
        <div class="card card-lux">
            <div class="card-icon">☀️</div>
            <div class="card-label">Luminosité</div>
            <div class="card-value">
                <span id="lux"><?= val($data, 'lux') ?></span><span class="card-unit">lux</span>
            </div>
            <div class="card-sub" id="lux-label">--</div>
            <div class="minmax">
                <div class="minmax-item minmax-min">
                    <span class="minmax-arrow">▼</span>
                    <span class="minmax-val" id="lux-min">--</span>
                    <span class="minmax-unit">lux</span>
                </div>
                <div class="minmax-sep"></div>
                <div class="minmax-item minmax-max">
                    <span class="minmax-arrow">▲</span>
                    <span class="minmax-val" id="lux-max">--</span>
                    <span class="minmax-unit">lux</span>
                </div>
            </div>
        </div>

        <!-- 5. Pluviométrie -->
        <div class="card card-pluie">
            <div class="card-icon">🌧</div>
            <div class="card-label">Pluviométrie</div>
            <div class="card-value">
                <span id="pluie"><?= val($data, 'pluie') ?></span><span class="card-unit">mm</span>
            </div>
        </div>

        <!-- 6. Vitesse du vent -->
        <div class="card card-vent">
            <div class="card-icon">💨</div>
            <div class="card-label">Vitesse du vent</div>
            <div class="card-value">
                <span id="vent"><?= val($data, 'vent') ?></span><span class="card-unit">km/h</span>
            </div>
            <div class="card-sub" id="vent-beaufort">--</div>
            <div class="minmax">
                <div class="minmax-item minmax-min">
                    <span class="minmax-arrow">▼</span>
                    <span class="minmax-val" id="vent-min">--</span>
                    <span class="minmax-unit">km/h</span>
                </div>
                <div class="minmax-sep"></div>
                <div class="minmax-item minmax-max">
                    <span class="minmax-arrow">▲</span>
                    <span class="minmax-val" id="vent-max">--</span>
                    <span class="minmax-unit">km/h</span>
                </div>
            </div>
        </div>

        <!-- 7. Direction du vent -->
        <div class="card card-dir">
            <div class="card-icon">🧭</div>
            <div class="card-label">Direction du vent</div>
            <div class="card-value" id="dir"><?= val($data, 'dir') ?></div>
            <div class="compass">
                <div class="compass-needle" id="compass-needle"></div>
            </div>
        </div>

        <!-- 8. Image station -->
        <div class="card card-img">
            <img src="meteo/station.jpeg">
        </div>

    </div><!-- /grid -->

    <footer>
        Terminal STI2D spécialité SIN (Systèmes Informations Numériques) 2025-2026 &nbsp;⮕&nbsp;
        Alwyn Le Barbier &nbsp;·&nbsp; Kalvin Hoffmann &nbsp;·&nbsp; Noë Vallée &nbsp;·&nbsp; Noah Nouvel
    </footer>

</div><!-- /wrap -->

<script>
/* ── Horloge ── */
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent =
        now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById('date-display').textContent =
        now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
setInterval(updateClock, 1000);
updateClock();

/* ── Min / Max journaliers ──
    Stockés dans localStorage avec la date du jour.
    Si la date change (nouveau jour), les valeurs sont réinitialisées automatiquement. */
const MINMAX_KEY = 'clm_minmax';

function todayStr() {
    return new Date().toISOString().slice(0, 10); // "YYYY-MM-DD"
}

function loadMinMax() {
    try {
        const raw = localStorage.getItem(MINMAX_KEY);
        if (!raw) return null;
        const obj = JSON.parse(raw);
        if (obj.date !== todayStr()) return null; // nouveau jour → reset
        return obj;
    } catch(e) { return null; }
}

function saveMinMax(obj) {
    try { localStorage.setItem(MINMAX_KEY, JSON.stringify(obj)); } catch(e) {}
}

let mm = loadMinMax() || {
    date: todayStr(),
    temp: { min: null, max: null },
    hum:  { min: null, max: null },
    lux:  { min: null, max: null },
    vent: { min: null, max: null }
};

function updateMinMax(field, value) {
    if (value === null || isNaN(value)) return;
    // Réinitialisation si on est passé à un nouveau jour en cours de session
    if (mm.date !== todayStr()) {
        mm = { date: todayStr(),
        temp: { min: null, max: null },
        hum:  { min: null, max: null },
        lux:  { min: null, max: null },
        vent: { min: null, max: null } };
    }
    if (mm[field].min === null || value < mm[field].min) mm[field].min = value;
    if (mm[field].max === null || value > mm[field].max) mm[field].max = value;
    saveMinMax(mm);
}

function renderMinMax(field, decimals) {
    const minEl = document.getElementById(field + '-min');
    const maxEl = document.getElementById(field + '-max');
    if (!minEl || !maxEl) return;
    minEl.textContent = mm[field].min !== null ? mm[field].min.toFixed(decimals) : '--';
    maxEl.textContent = mm[field].max !== null ? mm[field].max.toFixed(decimals) : '--';
}

/* ── Utilitaires ── */
const DIR_DEG = { N:0, NNE:22.5, NE:45, ENE:67.5, E:90, ESE:112.5, SE:135, SSE:157.5,
        S:180, SSO:202.5, SO:225, OSO:247.5, O:270, ONO:292.5, NO:315, NNO:337.5 };

function beaufort(kmh) {
    if (kmh === null) return '--';
    if (kmh < 1)   return 'Calme';
    if (kmh < 6)   return 'Très légère brise';
    if (kmh < 12)  return 'Légère brise';
    if (kmh < 20)  return 'Petite brise';
    if (kmh < 29)  return 'Jolie brise';
    if (kmh < 39)  return 'Brise fraîche';
    if (kmh < 50)  return 'Vent frais';
    if (kmh < 62)  return 'Grand frais';
    if (kmh < 75)  return 'Coup de vent';
    if (kmh < 89)  return 'Fort coup de vent';
    if (kmh < 103) return 'Tempête';
    if (kmh < 118) return 'Violente tempête';
    return 'Ouragan';
}

function ressenti(temp, hum) {
    if (temp === null) return '';
    if (temp >= 27 && hum !== null) {
        const humidex = temp + 0.5555 * (6.11 * Math.exp(5417.7530 * (1/273.16 - 1/(273.15 + temp))) * hum/100 - 10);
        return `Ressenti ${humidex.toFixed(1)} °C (humidex)`;
    }
    return '';
}

function aqiStyle(aqi) {
    if (aqi <= 50)  return { color: '#00c9a7', label: 'Bonne' };
    if (aqi <= 100) return { color: '#fbbf24', label: 'Modérée' };
    if (aqi <= 150) return { color: '#f97316', label: 'Mauvaise pour groupes sensibles' };
    if (aqi <= 200) return { color: '#ef4444', label: 'Mauvaise' };
    return { color: '#9333ea', label: 'Très mauvaise' };
}

function luxLabel(lux) {
    if (lux === null) return '--';
    if (lux < 1)    return 'Nuit noire';
    if (lux < 50)   return 'Intérieur sombre';
    if (lux < 500)  return 'Ciel couvert';
    if (lux < 1000) return 'Ciel nuageux';
    if (lux < 5000) return 'Ciel partiellement nuageux';
    if (lux < 20000) return 'Ciel dégagé';
    return 'Plein soleil';
}

/* ── Mise à jour UI ── */
function updateUI(d) {
    const set = (id, val, fallback = '--') =>
        document.getElementById(id).textContent = (val !== null && val !== undefined) ? val : fallback;

    const tempVal = d.temp !== null ? parseFloat(d.temp) : null;
    const humVal  = d.hum  !== null ? parseFloat(d.hum)  : null;
    const luxVal  = d.lux  !== null ? parseInt(d.lux)    : null;
    const ventVal = d.vent !== null ? parseFloat(d.vent) : null;

    set('temp',  tempVal !== null ? tempVal.toFixed(1) : null);
    set('hum',   humVal  !== null ? humVal.toFixed(0)  : null);
    set('lux',   luxVal  !== null ? luxVal             : null);
    set('aqi',   d.aqi);
    set('pluie', d.pluie !== null ? parseFloat(d.pluie).toFixed(1) : null);
    set('vent',  ventVal !== null ? ventVal.toFixed(1) : null);
    set('dir',   d.dir);
    set('last-update', d.timestamp);

    document.getElementById('temp-feel').textContent = ressenti(tempVal, humVal);
    document.getElementById('vent-beaufort').textContent = beaufort(ventVal);

    const aqiVal = parseInt(d.aqi);
    if (!isNaN(aqiVal)) {
        const s = aqiStyle(aqiVal);
        document.getElementById('aqi-label').textContent = s.label;
        document.documentElement.style.setProperty('--aqi-color', s.color);
        const pct = Math.min(aqiVal / 200 * 100, 100);
        document.getElementById('aqi-bar').style.width = pct + '%';
    }

    document.getElementById('lux-label').textContent = luxLabel(luxVal);

    const deg = DIR_DEG[d.dir];
    if (deg !== undefined) {
        document.getElementById('compass-needle').style.transform = `rotate(${deg}deg)`;
    }

    const dot = document.getElementById('status-dot');
    const ts = d.timestamp ? new Date(d.timestamp.replace(' ', 'T')) : null;
    const age = ts ? (Date.now() - ts.getTime()) / 1000 : Infinity;
    dot.className = 'status-dot' + (age > 120 ? ' offline' : '');

    /* ── Calcul et affichage des min/max ── */
    updateMinMax('temp', tempVal);
    updateMinMax('hum',  humVal);
    updateMinMax('lux',  luxVal !== null ? luxVal : null);
    updateMinMax('vent', ventVal);
    renderMinMax('temp', 1);
    renderMinMax('hum',  0);
    renderMinMax('lux',  0);
    renderMinMax('vent', 1);
}

/* ── Fetch ── */
async function fetchData() {
    try {
        const res = await fetch('data.json?t=' + Date.now());
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const d = await res.json();
        updateUI(d);
    } catch (e) {
        console.warn('Impossible de récupérer data.json :', e.message);
        document.getElementById('status-dot').className = 'status-dot offline';
    }
}

fetchData();
setInterval(fetchData, 5000);
</script>

</body>
</html>