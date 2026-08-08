/**
 * YalpeR LiveStream - Registrazione continua con auto-save ogni N secondi
 * Salva automaticamente segmenti di video durante tutta la partita
 */

// ============ CLASSI DI SUPPORTO ============

class CircularBuffer {
    constructor(size) {
        this.buffer = new Array(size);
        this.size = size;
        this.head = 0;
        this.count = 0;
    }

    push(element) {
        this.buffer[this.head] = element;
        this.head = (this.head + 1) % this.size;
        this.count = Math.min(this.count + 1, this.size);
    }

    clear() {
        this.buffer.fill(null);
        this.head = 0;
        this.count = 0;
    }

    get(index) {
        if (index >= this.count) return undefined;
        return this.buffer[(this.head - this.count + index + this.size) % this.size];
    }

    toArray() {
        return Array.from({ length: this.count }, (_, i) => this.get(i));
    }

    isFull() {
        return this.count === this.size;
    }

    getCount() {
        return this.count;
    }

    *[Symbol.iterator]() {
        for (let i = 0; i < this.count; i++) yield this.get(i);
    }
}

class LiveUploadDatabase {
    constructor() {
        this.dbName = 'liveUploadQueue';
        this.storeName = 'pendingSegments';
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, 1);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains(this.storeName)) {
                    db.createObjectStore(this.storeName, { keyPath: 'id', autoIncrement: true });
                }
            };
        });
    }

    async addSegment(segmentData) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.add({
                ...segmentData,
                timestamp: Date.now(),
                status: 'pending'
            });

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getNextSegment() {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.openCursor();

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                resolve(cursor ? cursor.value : null);
            };
            request.onerror = () => reject(request.error);
        });
    }

    async removeSegment(id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.delete(id);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getCount() {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.count();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
}

// ============ CONFIGURAZIONE ============

const SERVER = "https://yalper.it/";
const SLICE_TIME = 1312; // ms per ogni slice del buffer

// Risoluzioni con bitrates calibrati per ogni risoluzione (copiato da yalper_nocompr.js)
const resolutions = [
    {
        text: '640x360',
        width: 640,
        height: 360,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: 800000,
            standardPlus: 1200000,
            highQuality: 1600000,
            bestQuality: 2000000,
            ultraQuality: 4000000
        }
    },
    {
        text: '1024x576',
        width: 1024,
        height: 576,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: 1500000,
            standardPlus: 2000000,
            highQuality: 2500000,
            bestQuality: 3000000,
            ultraQuality: 6000000
        }
    },
    {
        text: '1152x648',
        width: 1152,
        height: 648,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: 2000000,
            standardPlus: 2500000,
            highQuality: 3000000,
            bestQuality: 3500000,
            ultraQuality: 7000000
        }
    },
    {
        text: '1280x720 HD',
        width: 1280,
        height: 720,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: 2500000,
            standardPlus: 3000000,
            highQuality: 3500000,
            bestQuality: 4000000,
            ultraQuality: 8000000
        }
    },
    {
        text: '1366x768',
        width: 1366,
        height: 768,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: 3000000,
            standardPlus: 3500000,
            highQuality: 4000000,
            bestQuality: 4500000,
            ultraQuality: 9000000
        }
    },
    {
        text: '1600x900',
        width: 1600,
        height: 900,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: 3500000,
            standardPlus: 4000000,
            highQuality: 4500000,
            bestQuality: 5000000,
            ultraQuality: 10000000
        }
    },
    {
        text: '1920x1080 Full HD',
        width: 1920,
        height: 1080,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: 4000000,
            standardPlus: 4500000,
            highQuality: 5000000,
            bestQuality: 5500000,
            ultraQuality: 11000000
        }
    }
];

// Codecs supportato (verrà rilevato all'avvio)
let codecs = localStorage.getItem('codecs') || 'video/webm;codecs=vp9';

// ============ STATO GLOBALE ============

let isLiveActive = false;
let recorder = null;
let localStream = null;
let blobBuffer = null;
let recordLoopTimer = null;
let autoSaveTimer = null;
let liveTimer = null;
let liveSeconds = 0;
let segmentNumber = 0;
let nextSaveCountdown = 0;
let wakeLock = null;
let isUploading = false;
let uploadCheckInterval = null;
let liveStartTime = null; // Ora di inizio della sessione live
let previewStream = null; // Stream per preview (prima di andare live)

// Impostazioni
let settings = {
    cameraId: localStorage.getItem('live_cameraId') || null,
    resolution: parseInt(localStorage.getItem('live_resolution')) || 3,
    quality: localStorage.getItem('live_quality') || 'highQuality',
    audio: localStorage.getItem('live_audio') || 'none',
    segmentDuration: parseInt(localStorage.getItem('live_segmentDuration')) || 60,
    matchName: localStorage.getItem('live_matchName') || ''
};

// Credenziali
const OTT = localStorage.getItem("OTT");
const token = localStorage.getItem("token");
const autore = localStorage.getItem('firstname') + " " + localStorage.getItem('lastname');

// Database
const uploadDB = new LiveUploadDatabase();

// Calcola dimensione buffer in base alla durata segmento
function calculateBufferSize() {
    return Math.ceil((settings.segmentDuration * 1000) / SLICE_TIME) + 2;
}

// Lista segmenti per UI
let segmentsList = [];

// ============ FUNZIONI UTILITY ============

function makeid(length) {
    return Array.from(crypto.getRandomValues(new Uint8Array(length)))
        .map(n => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'[n % 62])
        .join('');
}

function formatTime(seconds) {
    const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
    const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
    const s = (seconds % 60).toString().padStart(2, '0');
    return `${h}:${m}:${s}`;
}

async function requestWakeLock() {
    try {
        wakeLock = await navigator.wakeLock.request('screen');
        console.log('Wake Lock acquisito');
    } catch (err) {
        console.warn('Wake Lock non disponibile:', err);
    }
}

function releaseWakeLock() {
    if (wakeLock) {
        wakeLock.release();
        wakeLock = null;
        console.log('Wake Lock rilasciato');
    }
}

// ============ GESTIONE VIDEO ============

/**
 * Inizializza solo la preview della camera (senza registrazione)
 */
async function initCameraPreview() {
    const resolutionById = resolutions[settings.resolution];

    let video_opt = {
        deviceId: settings.cameraId ? { exact: settings.cameraId } : undefined,
        aspectRatio: { ideal: 16/9 },
        powerEfficient: false,
        latency: { ideal: 0 },
        width: resolutionById.width,
        height: resolutionById.height,
        resizeMode: resolutionById.resizeMode,
        frameRate: resolutionById.frameRate
    };

    try {
        // Ferma eventuali stream precedenti
        if (previewStream) {
            previewStream.getTracks().forEach(track => track.stop());
        }

        previewStream = await navigator.mediaDevices.getUserMedia({
            video: video_opt,
            audio: false // No audio per la preview
        });

        const videoElement = document.getElementById('main_video');
        videoElement.srcObject = previewStream;
        videoElement.muted = true;

        // Mostra zoom se disponibile
        const [track] = previewStream.getVideoTracks();
        const capabilities = track.getCapabilities();
        const videoSettings = track.getSettings();

        console.log(`Preview camera: ${videoSettings.width}x${videoSettings.height}@${Math.round(videoSettings.frameRate)}fps`);

        if ('zoom' in videoSettings && capabilities.zoom) {
            const input = document.getElementById('zoomer');
            input.min = capabilities.zoom.min;
            input.max = capabilities.zoom.max;
            input.step = capabilities.zoom.step;
            input.value = videoSettings.zoom;
            input.oninput = (event) => {
                track.applyConstraints({ advanced: [{ zoom: event.target.value }] });
            };
            input.style.display = 'block';
        }

        return true;
    } catch (error) {
        console.error('Errore inizializzazione preview camera:', error);
        return false;
    }
}

/**
 * Ferma la preview della camera
 */
function stopCameraPreview() {
    if (previewStream) {
        previewStream.getTracks().forEach(track => track.stop());
        previewStream = null;
    }
}

async function initCamera() {
    const resolutionById = resolutions[settings.resolution];

    let video_opt = {
        deviceId: settings.cameraId ? { exact: settings.cameraId } : undefined,
        aspectRatio: { ideal: 16/9 },
        powerEfficient: false,
        latency: { ideal: 0 },
        width: resolutionById.width,
        height: resolutionById.height,
        resizeMode: resolutionById.resizeMode,
        frameRate: resolutionById.frameRate
    };

    // Audio constraints come yalper_nocompr.js
    let audioConstraints = false;
    if (settings.audio === 'ambient') {
        audioConstraints = {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
            ideal: 'user'
        };
    } else if (settings.audio === 'esultanza') {
        audioConstraints = {
            echoCancellation: false,
            noiseSuppression: false,
            autoGainControl: true,
            ideal: 'environment'
        };
    }

    try {
        localStream = await navigator.mediaDevices.getUserMedia({
            video: video_opt,
            audio: audioConstraints
        });

        const videoElement = document.getElementById('main_video');
        videoElement.srcObject = localStream;
        videoElement.muted = true;

        // Mostra zoom se disponibile
        const [track] = localStream.getVideoTracks();
        const capabilities = track.getCapabilities();
        const videoSettings = track.getSettings();

        console.log(`Camera inizializzata: ${videoSettings.width}x${videoSettings.height}@${Math.round(videoSettings.frameRate)}fps`);

        if ('zoom' in videoSettings && capabilities.zoom) {
            const input = document.getElementById('zoomer');
            input.min = capabilities.zoom.min;
            input.max = capabilities.zoom.max;
            input.step = capabilities.zoom.step;
            input.value = videoSettings.zoom;
            input.oninput = (event) => {
                track.applyConstraints({ advanced: [{ zoom: event.target.value }] });
            };
            input.style.display = 'block';
        }

        return true;
    } catch (error) {
        console.error('Errore inizializzazione camera:', error);
        alert('Errore accesso alla camera: ' + error.message);
        return false;
    }
}

// Variabile per il valore bitrate effettivo (per il salvataggio)
let vbps_val = -1;

function startRecording() {
    const bufferSize = calculateBufferSize();
    blobBuffer = new CircularBuffer(bufferSize);

    const resolutionById = resolutions[settings.resolution];

    try {
        // Logica identica a yalper_nocompr.js
        if (settings.quality === '-1') {
            vbps_val = -1;
            recorder = new MediaRecorder(localStream, {
                type: codecs,
                audioBitsPerSecond: 128000
            });
        } else {
            // Usa il bitrate calibrato per la risoluzione corrente
            vbps_val = Math.round(resolutionById.bitrates[settings.quality] || resolutionById.bitrates.highQuality);
            console.log("vbps_val: " + vbps_val + " per risoluzione " + resolutionById.text);

            recorder = new MediaRecorder(localStream, {
                type: codecs,
                videoBitsPerSecond: vbps_val,
                audioBitsPerSecond: 128000
            });
        }

        recorder.addEventListener("dataavailable", (evt) => {
            if (evt.data && evt.data.size > 0) {
                blobBuffer.push(evt.data);
                updateStats();
            }
        });

        recorder.addEventListener("error", (error) => {
            console.error('Errore MediaRecorder:', error);
        });

        if (recorder.state === "inactive") {
            recorder.start();
        }

        recordLoop();

        console.log('Registrazione avviata, buffer size:', bufferSize, 'bitrate:', vbps_val);

    } catch (error) {
        console.error('Errore avvio registrazione:', error);
    }
}

function recordLoop() {
    recordLoopTimer = setTimeout(() => {
        requestAnimationFrame(() => {
            if (recorder && recorder.state === 'recording') {
                recorder.stop();
                recorder.start();
            }
            if (isLiveActive) {
                recordLoop();
            }
        });
    }, SLICE_TIME);
}

function stopRecording() {
    if (recordLoopTimer) {
        clearTimeout(recordLoopTimer);
        recordLoopTimer = null;
    }

    if (recorder && recorder.state === 'recording') {
        recorder.stop();
    }
    recorder = null;

    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }

    if (blobBuffer) {
        blobBuffer.clear();
        blobBuffer = null;
    }
}

// ============ AUTO-SAVE SEGMENTI ============

function startAutoSave() {
    nextSaveCountdown = settings.segmentDuration;

    autoSaveTimer = setInterval(async () => {
        nextSaveCountdown--;
        updateStats();

        if (nextSaveCountdown <= 0) {
            await saveCurrentSegment();
            nextSaveCountdown = settings.segmentDuration;
        }
    }, 1000);
}

function stopAutoSave() {
    if (autoSaveTimer) {
        clearInterval(autoSaveTimer);
        autoSaveTimer = null;
    }
}

async function saveCurrentSegment() {
    if (!blobBuffer || blobBuffer.getCount() === 0) {
        console.warn('Buffer vuoto, skip salvataggio');
        return;
    }

    segmentNumber++;
    const segmentId = `seg_${segmentNumber}`;

    console.log(`Salvataggio segmento #${segmentNumber}...`);

    // Copia il buffer corrente
    const bufferCopy = [...blobBuffer];

    // Usa l'ora di inizio della sessione live per tutti i segmenti
    const sessionStartTime = liveStartTime || new Date();
    const nomeFile = sessionStartTime.toLocaleString("it-IT", { timeZone: "Europe/Rome" }).replaceAll(/[:, /]/g, "_");

    const partita = settings.matchName || sessionStartTime.toLocaleString("it-IT", {
        timeZone: "Europe/Rome",
        day: "2-digit",
        month: "2-digit",
        year: "numeric"
    });

    // Formatta l'ora di inizio sessione per il display
    const oraInizio = sessionStartTime.toLocaleString("it-IT", {
        timeZone: "Europe/Rome",
        hour: "2-digit",
        minute: "2-digit"
    });

    // Tipologia: Live-Seg1, Live-Seg1-audio, Live-Seg1-esultanza
    const audioSuffix = settings.audio === 'ambient' ? '-audio' : (settings.audio === 'esultanza' ? '-esultanza' : '');
    const tipologia = `Live-Seg${String(segmentNumber).padStart(3, '0')}${audioSuffix}`;

    // Aggiungi alla lista UI
    addSegmentToList(segmentId, segmentNumber, 'pending');

    try {
        // Salva in IndexedDB
        await uploadDB.addSegment({
            video: bufferCopy,
            nomeFile: nomeFile,
            tipologia: tipologia,
            bblen: bufferCopy.length,
            OTT: OTT,
            token: token,
            vbps: vbps_val,
            version: settings.resolution,
            hmc: bufferCopy.length,
            text_home: settings.matchName || 'LIVE',
            text_away: `Seg ${segmentNumber} (${oraInizio})`,
            minutes: ` @ ${formatTime(liveSeconds)}`,
            partita: partita,
            autore: autore,
            segmentNumber: segmentNumber,
            segmentId: segmentId,
            sessionStartTime: sessionStartTime.toISOString()
        });

        console.log(`Segmento #${segmentNumber} salvato in coda (sessione iniziata: ${oraInizio})`);
        updateSegmentStatus(segmentId, 'pending');

    } catch (error) {
        console.error('Errore salvataggio segmento:', error);
        updateSegmentStatus(segmentId, 'error');
    }

    // Svuota il buffer per il prossimo segmento
    blobBuffer.clear();
}

// ============ UPLOAD ============

function startUploadCheck() {
    uploadCheckInterval = setInterval(checkAndUpload, 5000);
}

function stopUploadCheck() {
    if (uploadCheckInterval) {
        clearInterval(uploadCheckInterval);
        uploadCheckInterval = null;
    }
}

async function checkAndUpload() {
    if (isUploading) return;

    // Verifica connessione
    if (!navigator.onLine) {
        document.getElementById('stat_connection').textContent = 'Offline';
        return;
    }

    if ('connection' in navigator && navigator.connection.effectiveType) {
        document.getElementById('stat_connection').textContent = navigator.connection.effectiveType.toUpperCase();
    } else {
        document.getElementById('stat_connection').textContent = 'Online';
    }

    isUploading = true;

    try {
        const segment = await uploadDB.getNextSegment();
        if (segment) {
            updateSegmentStatus(segment.segmentId, 'uploading');
            await uploadSegment(segment);
            await uploadDB.removeSegment(segment.id);
            updateSegmentStatus(segment.segmentId, 'done');
        }
    } catch (error) {
        console.error('Errore upload:', error);
    } finally {
        isUploading = false;
        updateQueueCount();
    }
}

async function uploadSegment(segment) {
    // 1. Crea il job sul server
    const jobFormData = new FormData();
    jobFormData.append('folder', segment.nomeFile + segment.tipologia);
    jobFormData.append('chunk_num', segment.hmc);
    jobFormData.append('vbps', segment.vbps);
    jobFormData.append('version', segment.version);
    jobFormData.append('OTT', segment.OTT);
    jobFormData.append('token', segment.token);
    jobFormData.append('text_home', segment.text_home);
    jobFormData.append('text_away', segment.text_away);
    jobFormData.append('minutes', segment.minutes);
    jobFormData.append('partita', segment.partita);
    jobFormData.append('autore', segment.autore);

    await fetch(SERVER + "jobs.php", {
        method: 'POST',
        body: jobFormData
    });

    // 2. Upload di tutti i chunk
    const uploadPromises = segment.video.map((chunk, index) => {
        return uploadChunk(chunk, `part${index}.webm`, segment.nomeFile + segment.tipologia);
    });

    // Upload con concorrenza limitata
    const concurrencyLimit = 3;
    for (let i = 0; i < uploadPromises.length; i += concurrencyLimit) {
        const batch = uploadPromises.slice(i, i + concurrencyLimit);
        await Promise.all(batch);
    }

    console.log(`Segmento ${segment.segmentNumber} uploadato`);
}

function uploadChunk(blob, fileName, folder) {
    return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('video-filename', fileName);
        formData.append('folder', folder);
        formData.append('OTT', OTT);
        formData.append('token', token);
        formData.append('video-blob', blob);

        fetch(SERVER + 'upload/save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Upload failed');
            resolve();
        })
        .catch(reject);
    });
}

async function updateQueueCount() {
    try {
        const count = await uploadDB.getCount();
        document.getElementById('stat_upload_queue').textContent = count;
    } catch (e) {
        console.error('Errore conteggio coda:', e);
    }
}

// ============ UI ============

function updateStats() {
    const bufferSize = blobBuffer ? calculateBufferSize() : 0;
    const bufferCount = blobBuffer ? blobBuffer.getCount() : 0;

    document.getElementById('stat_segments').textContent = segmentNumber;
    document.getElementById('stat_next_save').textContent = nextSaveCountdown + 's';
    document.getElementById('stat_buffer').textContent = `${bufferCount}/${bufferSize}`;
}

function updateLiveTimer() {
    liveSeconds++;
    document.getElementById('live_timer').textContent = formatTime(liveSeconds);
}

function addSegmentToList(segmentId, number, status) {
    segmentsList.push({ id: segmentId, number, status });
    renderSegmentsList();
}

function updateSegmentStatus(segmentId, status) {
    const segment = segmentsList.find(s => s.id === segmentId);
    if (segment) {
        segment.status = status;
        renderSegmentsList();
    }
}

function renderSegmentsList() {
    const container = document.getElementById('segments_container');
    // Mostra solo gli ultimi 10 segmenti
    const recentSegments = segmentsList.slice(-10).reverse();

    container.innerHTML = recentSegments.map(seg => `
        <div class="segment-item">
            <span>Seg #${seg.number}</span>
            <span class="status ${seg.status}"></span>
        </div>
    `).join('');
}

// ============ CONTROLLI PRINCIPALI ============

async function toggleLive() {
    if (isLiveActive) {
        stopLive();
    } else {
        await startLive();
    }
}

async function startLive() {
    console.log('Avvio LiveStream...');

    // Verifica login
    if (!OTT || !token) {
        alert('Devi effettuare il login');
        window.location.href = 'login.html';
        return;
    }

    // Inizializza database
    await uploadDB.init();

    // Ferma la preview e inizializza la camera con audio (se necessario)
    stopCameraPreview();

    // Inizializza camera per registrazione
    const cameraOk = await initCamera();
    if (!cameraOk) return;

    // === RESET STATISTICHE E SEGMENTI PER NUOVA SESSIONE ===
    liveStartTime = new Date(); // Salva ora di inizio sessione
    segmentNumber = 0;
    liveSeconds = 0;
    segmentsList = [];
    renderSegmentsList();

    // Reset UI statistiche
    document.getElementById('stat_segments').textContent = '0';
    document.getElementById('stat_next_save').textContent = '--';
    document.getElementById('stat_buffer').textContent = '0/0';
    document.getElementById('live_timer').textContent = '00:00:00';

    console.log('Nuova sessione live iniziata alle:', liveStartTime.toLocaleString("it-IT"));

    // Avvia registrazione
    startRecording();

    // Avvia auto-save
    startAutoSave();

    // Avvia timer live
    liveTimer = setInterval(updateLiveTimer, 1000);

    // Avvia controllo upload
    startUploadCheck();

    // Wake lock
    await requestWakeLock();

    // Aggiorna UI
    isLiveActive = true;
    document.getElementById('live_dot').classList.add('recording');
    document.getElementById('live_status').textContent = 'LIVE';
    document.getElementById('btn_live').textContent = 'STOP LIVE';
    document.getElementById('btn_live').classList.remove('btn-start');
    document.getElementById('btn_live').classList.add('btn-stop');
    document.getElementById('stats_panel').style.display = 'block';
    document.getElementById('segment_list').style.display = 'block';
    document.getElementById('btn_settings').style.display = 'none';

    console.log('LiveStream avviato!');
}

async function stopLive() {
    console.log('Stop LiveStream...');

    // Salva ultimo segmento prima di fermare
    if (blobBuffer && blobBuffer.getCount() > 0) {
        await saveCurrentSegment();
    }

    // Ferma tutto
    stopAutoSave();
    stopRecording();

    if (liveTimer) {
        clearInterval(liveTimer);
        liveTimer = null;
    }

    // NON fermiamo uploadCheck per permettere upload rimanenti
    // stopUploadCheck();

    releaseWakeLock();

    // Aggiorna UI
    isLiveActive = false;
    document.getElementById('live_dot').classList.remove('recording');
    document.getElementById('live_status').textContent = 'OFFLINE';
    document.getElementById('btn_live').textContent = 'Vai in LIVE';
    document.getElementById('btn_live').classList.remove('btn-stop');
    document.getElementById('btn_live').classList.add('btn-start');
    document.getElementById('btn_settings').style.display = 'block';

    console.log('LiveStream fermato. Segmenti totali:', segmentNumber);

    // Riavvia la preview della camera
    await initCameraPreview();
}

function toggleSettings() {
    const modal = document.getElementById('settings_modal');
    if (modal.style.display === 'flex') {
        modal.style.display = 'none';
    } else {
        loadSettingsToUI();
        populateCameraList();
        modal.style.display = 'flex';
    }
}

async function populateCameraList() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(d => d.kind === 'videoinput');
        const select = document.getElementById('camera_select');

        select.innerHTML = videoDevices.map(device => `
            <option value="${device.deviceId}" ${device.deviceId === settings.cameraId ? 'selected' : ''}>
                ${device.label || 'Camera ' + (videoDevices.indexOf(device) + 1)}
            </option>
        `).join('');
    } catch (e) {
        console.error('Errore lista camera:', e);
    }
}

function loadSettingsToUI() {
    document.getElementById('resolution_select').value = settings.resolution;
    document.getElementById('quality_select').value = settings.quality;
    document.getElementById('audio_select').value = settings.audio;
    document.getElementById('segment_duration').value = settings.segmentDuration;
    document.getElementById('match_name').value = settings.matchName;
}

function saveSettings() {
    settings.cameraId = document.getElementById('camera_select').value;
    settings.resolution = parseInt(document.getElementById('resolution_select').value);
    settings.quality = document.getElementById('quality_select').value;
    settings.audio = document.getElementById('audio_select').value;
    settings.segmentDuration = parseInt(document.getElementById('segment_duration').value);
    settings.matchName = document.getElementById('match_name').value;

    // Salva in localStorage
    localStorage.setItem('live_cameraId', settings.cameraId);
    localStorage.setItem('live_resolution', settings.resolution);
    localStorage.setItem('live_quality', settings.quality);
    localStorage.setItem('live_audio', settings.audio);
    localStorage.setItem('live_segmentDuration', settings.segmentDuration);
    localStorage.setItem('live_matchName', settings.matchName);

    toggleSettings();
    console.log('Impostazioni salvate:', settings);
}

// ============ INIZIALIZZAZIONE ============

$(document).ready(async function() {
    console.log('YalpeR LiveStream inizializzato');

    // Verifica login
    if (!OTT || !token) {
        console.warn('Non loggato, redirect...');
        // window.location.href = 'login.html';
        // return;
    }

    // Inizializza database
    await uploadDB.init();

    // Richiedi permessi camera e avvia subito la preview
    try {
        await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        // Avvia la preview della camera subito
        await initCameraPreview();
        console.log('Preview camera attiva');
    } catch (e) {
        console.warn('Permessi media non concessi:', e);
    }

    // Popola lista camera nelle impostazioni
    await populateCameraList();

    // Carica impostazioni
    loadSettingsToUI();

    // Avvia controllo upload per eventuali segmenti rimasti in coda
    startUploadCheck();
    updateQueueCount();

    // Gestione chiusura pagina
    window.addEventListener('beforeunload', function(e) {
        if (isLiveActive) {
            e.preventDefault();
            e.returnValue = 'Hai una registrazione live in corso. Sei sicuro di voler uscire?';
            return e.returnValue;
        }
    });

    // Gestione visibilità pagina
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden' && isLiveActive) {
            console.log('Pagina in background, live continua...');
        }
    });
});
