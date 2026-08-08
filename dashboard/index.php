<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Auto Dashboard</title>
    <link rel="manifest" href="data:application/json;base64,ewogICJuYW1lIjogIkF1dG8gRGFzaGJvYXJkIiwKICAic2hvcnRfbmFtZSI6ICJEYXNoYm9hcmQiLAogICJzdGFydF91cmwiOiAiLiIsCiAgImRpc3BsYXkiOiAic3RhbmRhbG9uZSIsCiAgImJhY2tncm91bmRfY29sb3IiOiAiIzAwMDAwMCIsCiAgInRoZW1lX2NvbG9yIjogIiMwMDAwMDAiLAogICJpY29ucyI6IFsKICAgIHsKICAgICAgInNyYyI6ICJkYXRhOmltYWdlL3N2Zyt4bWw7YmFzZTY0LEFCQyIsCiAgICAgICJzaXplcyI6ICIxOTJ4MTkyIiwKICAgICAgInR5cGUiOiAiaW1hZ2Uvc3ZnK3htbCIKICAgIH0KICBdCn0=">
    <!-- Meta tag per impedire allo schermo di andare in standby -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes"
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body, html {
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: 'Rajdhani', 'Orbitron', sans-serif;
            background-color: #000;
            color: #fff;
        }
        #map {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        .dashboard {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
        }
        .gauge {
            width: 220px;
            height: 220px;
            position: relative;
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0 0 20px rgba(0, 70, 255, 0.7);
            overflow: hidden;
        }
        .gauge::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border-radius: 50%;
            background: radial-gradient(circle at center, #000000, #111827);
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.9);
            z-index: -1;
        }
        .gauge::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid rgba(0, 70, 255, 0.6);
            box-sizing: border-box;
        }
        .gauge-outer-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(0, 20, 50, 0.8), rgba(0, 0, 0, 0.5));
            top: 0;
            left: 0;
            z-index: -2;
        }
        .gauge-title {
            position: absolute;
            width: 100%;
            text-align: center;
            top: 60%;
            left: 0;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(0, 140, 255, 0.8);
            text-shadow: 0 0 5px rgba(0, 140, 255, 0.5);
        }
        .gauge-value {
            position: absolute;
            width: 100%;
            text-align: center;
            top: 72%;
            left: 0;
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 0 10px rgba(0, 140, 255, 0.8);
        }
        .gauge-unit {
            position: absolute;
            width: 100%;
            text-align: center;
            top: 85%;
            left: 0;
            font-size: 12px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.7);
        }
        .gauge-center {
            position: absolute;
            width: 20px;
            height: 20px;
            background: radial-gradient(circle at center, #ffffff, #00a2ff);
            border-radius: 50%;
            top: calc(50% - 10px);
            left: calc(50% - 10px);
            box-shadow: 0 0 10px rgba(0, 140, 255, 0.8);
            z-index: 10;
        }
        .gauge-needle {
            width: 6px;
            height: 50%;
            background: linear-gradient(to top, #ff3300, #ff0000);
            position: absolute;
            left: calc(50% - 3px);
            bottom: 50%;
            transform-origin: center bottom;
            border-radius: 3px 3px 0 0;
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 0 5px rgba(255, 0, 0, 0.7);
            z-index: 9;
            /* Posizione iniziale della lancetta a 225 gradi (posizione dello zero) */
            transform: rotate(225deg);
        }
        .gauge-needle::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            background: radial-gradient(circle at center, #ff3300, #990000);
            border-radius: 50%;
            bottom: -8px;
            left: -5px;
        }
        .gauge-ticks {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
        }
        .gauge-tick {
            position: absolute;
            width: 2px;
            height: 10px;
            background-color: rgba(255, 255, 255, 0.6);
            left: calc(50% - 1px);
            top: 10px;
            transform-origin: center 90px;
            box-shadow: 0 0 2px rgba(0, 140, 255, 0.5);
        }
        .gauge-tick.major {
            height: 15px;
            width: 3px;
            background-color: rgba(0, 140, 255, 0.8);
            box-shadow: 0 0 5px rgba(0, 140, 255, 0.8);
        }
        .gauge-tick-label {
            position: absolute;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
            transform-origin: center 75px;
            width: 30px;
            text-align: center;
            left: calc(50% - 15px);
            top: 30px;
            text-shadow: 0 0 3px rgba(0, 0, 0, 0.9);
        }
        .gauge-background {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -3;
        }
        .gauge-progress {
            position: absolute;
            width: 100%;
            height: 100%;
            clip-path: polygon(50% 50%, 50% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 0%, 50% 0%);
            background: conic-gradient(from 225deg, transparent 0%, rgba(0, 140, 255, 0.1) 25%, rgba(0, 140, 255, 0.2) 50%, rgba(255, 0, 0, 0.3) 75%, rgba(255, 0, 0, 0.4) 100%);
            border-radius: 50%;
            top: 0;
            left: 0;
            z-index: -1;
        }
        
        /* Specifico per i contatori */
        #rpmGauge .gauge-progress {
            background: conic-gradient(from 225deg, transparent 0%, rgba(0, 140, 255, 0.1) 25%, rgba(0, 255, 255, 0.2) 50%, rgba(255, 255, 0, 0.3) 75%, rgba(255, 0, 0, 0.4) 100%);
        }
        #speedGauge .gauge-progress {
            background: conic-gradient(from 225deg, transparent 0%, rgba(0, 140, 255, 0.1) 30%, rgba(0, 255, 0, 0.2) 60%, rgba(255, 255, 0, 0.3) 80%, rgba(255, 0, 0, 0.4) 100%);
        }

        .message {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            text-align: center;
            background-color: rgba(0, 10, 20, 0.8);
            color: #00a2ff;
            padding: 12px;
            z-index: 3;
            font-size: 16px;
            border-radius: 10px;
            margin: 0 20px;
            box-shadow: 0 0 15px rgba(0, 140, 255, 0.5);
            border: 1px solid rgba(0, 140, 255, 0.3);
        }
        .hidden {
            display: none;
        }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="map"></div>
    <div id="message" class="message">Attendere mentre si carica la mappa e si ottiene la posizione GPS...</div>
    
    <div class="dashboard">
        <div class="gauge" id="rpmGauge">
            <div class="gauge-outer-ring"></div>
            <div class="gauge-progress"></div>
            <div class="gauge-ticks" id="rpmTicks"></div>
            <div class="gauge-needle" id="rpmNeedle"></div>
            <div class="gauge-center"></div>
            <div class="gauge-title">GIRI/MIN</div>
            <div class="gauge-value" id="rpmValue">0</div>
            <div class="gauge-unit">RPM</div>
        </div>
        
        <div class="gauge" id="speedGauge">
            <div class="gauge-outer-ring"></div>
            <div class="gauge-progress"></div>
            <div class="gauge-ticks" id="speedTicks"></div>
            <div class="gauge-needle" id="speedNeedle"></div>
            <div class="gauge-center"></div>
            <div class="gauge-title">VELOCITÀ</div>
            <div class="gauge-value" id="speedValue">0</div>
            <div class="gauge-unit">KM/H</div>
        </div>
    </div>

    <script>
        // Registra il service worker per la PWA
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').then(function(registration) {
                console.log('ServiceWorker registrato con successo:', registration.scope);
            }).catch(function(error) {
                console.log('Registrazione ServiceWorker fallita:', error);
            });
        }

        // Impedisce allo schermo di andare in sospensione
        function preventSleep() {
            if (navigator.wakeLock) {
                navigator.wakeLock.request('screen').then(lock => {
                    console.log('Wake Lock attivo!');
                    // Rilascia il wakeLock se la pagina diventa invisibile
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible' && !lock.released) {
                            navigator.wakeLock.request('screen');
                        }
                    });
                }).catch(err => {
                    console.error('Wake Lock non attivato:', err.message);
                    // Metodo di fallback per mantere lo schermo attivo
                    useWakeLockFallback();
                });
            } else {
                console.warn('Wake Lock API non supportata');
                // Metodo di fallback per mantenere lo schermo attivo
                useWakeLockFallback();
            }
        }

        // Fallback per browser che non supportano l'API Wake Lock
        function useWakeLockFallback() {
            // Crea un video invisibile che riproduce contenuto in modo silenzioso
            const video = document.createElement('video');
            video.setAttribute('loop', '');
            video.setAttribute('playsinline', '');
            video.setAttribute('muted', '');
            video.setAttribute('width', '1');
            video.setAttribute('height', '1');
            video.style.position = 'absolute';
            video.style.opacity = '0.01';
            document.body.appendChild(video);

            // Aggiungi un "blob video" vuoto
            const videoSource = URL.createObjectURL(new Blob([new Uint8Array([0, 0])], {type: 'video/mp4'}));
            const source = document.createElement('source');
            source.setAttribute('src', videoSource);
            video.appendChild(source);

            // Avvia la riproduzione
            video.play().catch(e => console.error('Errore fallback video:', e));

            // Teniamo anche il dispositivo "attivo" tramite un intervallo
            setInterval(() => {
                if (document.hidden) return;
                // Esegui un'azione leggera per mantenere il dispositivo attivo
                window.dispatchEvent(new Event('resize'));
            }, 30000);
        }

        // Variabili globali
        let map, marker, circle;
        let currentSpeed = 0;
        let currentRPM = 0;
        let watchId = null;
        let heading = 0;
        let markerIcon = null;
        
        // Crea l'icona per il marker
        function createMarkerIcon() {
            return L.divIcon({
                html: `<div style="
                    width: 24px;
                    height: 24px;
                    background-color: blue;
                    border: 2px solid white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transform: rotate(${heading}deg);
                ">
                <div style="
                    width: 0;
                    height: 0;
                    border-left: 6px solid transparent;
                    border-right: 6px solid transparent;
                    border-bottom: 12px solid white;
                    transform: translateY(-4px);
                "></div>
                </div>`,
                className: '',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });
        }

        // Inizializza la mappa
        function initMap() {
            map = L.map('map', {
                zoomControl: false,
                attributionControl: false
            }).setView([0, 0], 18);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(map);
            
            markerIcon = createMarkerIcon();
            marker = L.marker([0, 0], {icon: markerIcon}).addTo(map);
            circle = L.circle([0, 0], {
                color: 'blue',
                fillColor: '#30f',
                fillOpacity: 0.3,
                radius: 10
            }).addTo(map);
            
            startTracking();
        }

        // Inizializza i contatori
        function initGauges() {
            // Contagiri (0-5000 rpm)
            createGaugeTicks('rpmTicks', 0, 5000, 500);
            
            // Tachimetro (0-110 km/h)
            createGaugeTicks('speedTicks', 0, 110, 10);
        }

        // Crea i segni sui contatori
        function createGaugeTicks(elementId, min, max, step) {
            const element = document.getElementById(elementId);
            const range = max - min;
            const angleRange = 270; // 270 gradi di rotazione
            const startAngle = 225; // Inizia dal basso-sinistra
            
            for (let i = min; i <= max; i += step) {
                const isMajor = i % (step * 5) === 0 || i === min || i === max;
                // Invertito il segno per cambiare il senso di rotazione
                const angle = startAngle + ((i - min) / range) * angleRange;
                
                // Crea tacca
                const tick = document.createElement('div');
                tick.className = isMajor ? 'gauge-tick major' : 'gauge-tick';
                tick.style.transform = `rotate(${angle}deg)`;
                element.appendChild(tick);
                
                // Crea etichetta per le tacche principali
                if (isMajor) {
                    const label = document.createElement('div');
                    label.className = 'gauge-tick-label';
                    label.style.transform = `rotate(${angle}deg)`;
                    label.textContent = i;
                    element.appendChild(label);
                }
            }
        }

        // Aggiorna gli indicatori
        function updateGauges(rpm, speed) {
            // Contagiri - senso invertito
            const rpmRange = 5000;
            // Calcola l'angolo con un offset iniziale di 225 gradi (posizione dello zero)
            // Poi ruota in senso orario (positivo) di un angolo proporzionale al valore
            const rpmAngle = 225 + ((rpm / rpmRange) * 270);
            document.getElementById('rpmNeedle').style.transform = `rotate(${rpmAngle}deg)`;
            document.getElementById('rpmValue').textContent = Math.round(rpm);
            
            // Tachimetro - senso invertito
            const speedRange = 110;
            // Stesso principio per il tachimetro
            const speedAngle = 225 + ((speed / speedRange) * 270);
            document.getElementById('speedNeedle').style.transform = `rotate(${speedAngle}deg)`;
            document.getElementById('speedValue').textContent = Math.round(speed);
        }

        // Simula i giri motore in base alla velocità
        function simulateRPM(speed) {
            // Simulazione semplice: giri motore correlati alla velocità
            if (speed < 5) {
                return 800 + Math.random() * 200; // Motore al minimo
            } else if (speed < 20) {
                return 1000 + (speed * 50) + (Math.random() * 100 - 50);
            } else if (speed < 50) {
                return 2000 + ((speed - 20) * 40) + (Math.random() * 200 - 100);
            } else {
                return 3200 + ((speed - 50) * 20) + (Math.random() * 300 - 150);
            }
        }

        // Inizia il tracciamento della posizione
        function startTracking() {
            if (navigator.geolocation) {
                document.getElementById('message').textContent = "Ottenimento posizione GPS...";
                
                // Opzioni per la geolocalizzazione
                const options = {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                };
                
                watchId = navigator.geolocation.watchPosition(
                    positionUpdate,
                    positionError,
                    options
                );
                
                // Simulazione dei giri motore
                setInterval(() => {
                    const targetRPM = simulateRPM(currentSpeed);
                    // Avvicina gradualmente i giri al target
                    currentRPM = currentRPM + (targetRPM - currentRPM) * 0.2;
                    updateGauges(currentRPM, currentSpeed);
                }, 200);
            } else {
                document.getElementById('message').textContent = "La geolocalizzazione non è supportata dal tuo browser.";
            }
        }

        // Callback in caso di aggiornamento della posizione
        function positionUpdate(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            // Aggiorna la posizione sulla mappa
            const latLng = L.latLng(lat, lng);
            map.setView(latLng);
            marker.setLatLng(latLng);
            circle.setLatLng(latLng);
            circle.setRadius(accuracy);
            
            // Aggiorna la direzione se disponibile
            if (position.coords.heading !== null && !isNaN(position.coords.heading)) {
                heading = position.coords.heading;
                marker.setIcon(createMarkerIcon());
            }
            
            // Aggiorna la velocità se disponibile, altrimenti calcola la velocità media
            if (position.coords.speed !== null && !isNaN(position.coords.speed)) {
                currentSpeed = position.coords.speed * 3.6; // Converti da m/s a km/h
            }
            
            document.getElementById('message').classList.add('hidden');
        }

        // Callback in caso di errore della geolocalizzazione
        function positionError(error) {
            let message = "Errore durante l'ottenimento della posizione: ";
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += "L'utente ha negato l'accesso alla geolocalizzazione.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += "Le informazioni sulla posizione non sono disponibili.";
                    break;
                case error.TIMEOUT:
                    message += "La richiesta di ottenere la posizione è scaduta.";
                    break;
                case error.UNKNOWN_ERROR:
                    message += "Si è verificato un errore sconosciuto.";
                    break;
            }
            
            document.getElementById('message').textContent = message;
        }

        // Inizializza tutto quando la pagina è caricata
        window.onload = function() {
            initMap();
            initGauges();
            // Impostiamo esplicitamente la posizione iniziale delle lancette a zero
            updateGauges(0, 0);
            
            // Aggiungiamo un piccolo ritardo per assicurarci che le lancette siano correttamente posizionate all'inizio
            setTimeout(() => {
                // Forza il riposizionamento delle lancette con un trigger di layout
                document.getElementById('rpmNeedle').style.transform = 'rotate(225deg)';
                document.getElementById('speedNeedle').style.transform = 'rotate(225deg)';
            }, 100);
            
            // Previeni che lo schermo vada in sospensione
            preventSleep();
        };
    </script>
</body>
</html>
