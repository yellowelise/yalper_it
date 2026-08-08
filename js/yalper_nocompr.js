(function(){
	let oldLog = console.log;
	console.log = function (message, dest) {
		if ($("#log").is(":visible")) {
			if (typeof(dest) === "undefined")
				$("#log").append(message + "<br />");
			else {
				if ($("#" + dest).length)
					$("#" + dest).html(message);
				else {
					$("#log").append("<div id='"+dest+"'></div><br />");
					$("#" + dest).html(message);
				}
			}
		}
		oldLog.apply(console, arguments);
	};
})();

// Carica il Worker Manager
let blobWorkerScript = document.createElement('script');
blobWorkerScript.src = 'js/worker-manager.js';
document.head.appendChild(blobWorkerScript);

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

    peek() {
        return this.count ? this.get(this.count - 1) : undefined;
    }

    peek_and_remove() {
        if (!this.count) return undefined;
        const oldestIndex = (this.head - this.count + this.size) % this.size;
        const element = this.buffer[oldestIndex];
        this.buffer[oldestIndex] = null;
        this.count--;
        return element;
    }

    toArray() {
        return Array.from({ length: this.count }, (_, i) => this.get(i));
    }

    isFull() {
        return this.count === this.size;
    }

    areAllElementsNonEmpty() {
        return this.isFull() && this.toArray().every(element => element && element.length > 0);
    }

    *[Symbol.iterator]() {
        for (let i = 0; i < this.count; i++) yield this.get(i);
    }
}

class UploadDatabase {
    constructor() {
        this.dbName = 'uploadQueue';
        this.storeName = 'pendingUploads';
        this.db = null;
        this.init();
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

    async addUpload(uploadData) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.add({
                ...uploadData,
                timestamp: Date.now(),
                status: 'pending'
            });

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getNextUpload() {
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

    async removeUpload(id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.delete(id);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }
}

// Cache per selettori DOM frequentemente utilizzati
const domCache = {
    saveButton: null,
    videoElement: null,
    log: null,
    creditsDisplay: null,
    uploadStatusDisplay: null,
    connButton: null,
    init() {
        this.saveButton = document.getElementById("salva_replay");
        this.videoElement = document.getElementById("main_video");
        this.log = document.getElementById("log");
        this.creditsDisplay = document.getElementById("crediti_residui");
        this.uploadStatusDisplay = document.getElementById("coda_file");
        this.connButton = document.getElementById("btn_conn");
    }
};

// Variabili globali
let camera_deviceID = localStorage.getItem("cameraID");
let audio_deviceID = localStorage.getItem("audioID");
let saveReplay = false;
let stopRecording;
let recorder;
let duration = localStorage.getItem('duration') || 15;
let replayTime;
let slice_time = 1312; // settare per avere slice tutti uguali
let max_bblen; // Assicurati che questa variabile sia definita altrove
let bblen;

if (slice_time > 0) {
    bblen = Math.round(((duration * 1000) + 1000) / slice_time);
    replayTime = slice_time;
} else {
    bblen = max_bblen;
    replayTime = Math.round(((duration * 1000) + 1000) / (bblen - 1));
}

let blobBuffer = new CircularBuffer(bblen);
let tipologia = "";
let videoElement;
let localStream;
let bu, ul;
const uniqueid = makeid(30);
let hls;
let wakeLock = null;

let file_da_caricare = 0;
let file_errore = 0;
let file_totali = 0;
let codecs = localStorage.getItem('codecs');
let vbps = localStorage.getItem('vbps');
let event_push = localStorage.getItem('event') || '';
let RL;
let audioSource = localStorage.getItem('audioSource') || 'none';
let version = localStorage.getItem('version') || '3';
let autore = localStorage.getItem('firstname') + " " + localStorage.getItem('lastname');
const container = document.getElementById('padre_di_tutto');
const message = document.getElementById('ruota');

const SERVER = "https://yalper.it/";
let OTT = localStorage.getItem("OTT");
let token = localStorage.getItem("token");

let last_video = "";
let storedId = localStorage.getItem('iduser');
let resolutionById;

const uploadDB = new UploadDatabase();

const coefficiente = 1.0;
let minutaggio;

let vbps_val;
let ccau;
let fill = 0;
let isUploading = false;
let action_timer_interval;
let action_timer_seconds = 0;
let action_timer_running = false;

//let txt_home, txt_away, home_goal, away_goal, curr_minutes, indicatore_tempo;

// Definizione delle risoluzioni
/*
const resolutions = [
    {
        text: '640x360',
        width: 640,
        height: 360,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(800000*coefficiente),        // 800Kbps
            standardPlus: Math.round(1200000*coefficiente),   // 1.2Mbps
            highQuality: Math.round(1600000*coefficiente),    // 1.6Mbps
            bestQuality: Math.round(2000000*coefficiente),    // 2Mbps
            ultraQuality: Math.round(4000000*coefficiente)    // 2.4Mbps
        }
    },
    {
        text: '1024x576',
        width: 1024,
        height: 576,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(1500000*coefficiente),       // 1.5Mbps
            standardPlus: Math.round(2000000*coefficiente),   // 2Mbps
            highQuality: Math.round(2500000*coefficiente),    // 2.5Mbps
            bestQuality: Math.round(3000000*coefficiente),    // 3Mbps
            ultraQuality: Math.round(6000000*coefficiente)    // 3.5Mbps
        }
    },
    {
        text: '1152x648',
        width: 1152,
        height: 648,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(2000000*coefficiente),       // 2Mbps
            standardPlus: Math.round(2500000*coefficiente),   // 2.5Mbps
            highQuality: Math.round(3000000*coefficiente),    // 3Mbps
            bestQuality: Math.round(3500000*coefficiente),    // 3.5Mbps
            ultraQuality: Math.round(7000000*coefficiente)    // 4Mbps
        }
    },
    {
        text: '1280x720 HD',
        width: 1280,
        height: 720,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(2500000*coefficiente),       // 2.5Mbps
            standardPlus: Math.round(3000000*coefficiente),   // 3Mbps
            highQuality: Math.round(3500000*coefficiente),    // 3.5Mbps
            bestQuality: Math.round(4000000*coefficiente),    // 4Mbps
            ultraQuality: Math.round(8000000*coefficiente)    // 4.5Mbps
        }
    },
    {
        text: '1366x768',
        width: 1366,
        height: 768,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(3000000*coefficiente),       // 3Mbps
            standardPlus: Math.round(3500000*coefficiente),   // 3.5Mbps
            highQuality: Math.round(4000000*coefficiente),    // 4Mbps
            bestQuality: Math.round(4500000*coefficiente),    // 4.5Mbps
            ultraQuality: Math.round(9000000*coefficiente)    // 5Mbps
        }
    },
    {
        text: '1600x900',
        width: 1600,
        height: 900,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(3500000*coefficiente),       // 3.5Mbps
            standardPlus: Math.round(4000000*coefficiente),   // 4Mbps
            highQuality: Math.round(4500000*coefficiente),    // 4.5Mbps
            bestQuality: Math.round(5000000*coefficiente),    // 5Mbps
            ultraQuality: Math.round(10000000*coefficiente)    // 5.5Mbps
        }
    },
    {
        text: '1920x1080 Full HD',
        width: 1920,
        height: 1080,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(4000000*coefficiente),       // 4Mbps
            standardPlus: Math.round(4500000*coefficiente),   // 4.5Mbps
            highQuality: Math.round(5000000*coefficiente),    // 5Mbps
            bestQuality: Math.round(5500000*coefficiente),    // 5.5Mbps
            ultraQuality: Math.round(11000000*coefficiente)    // 6Mbps
        }
    },
    {
        text: '2560x1440',
        width: 2560,
        height: 1440,
        resizeMode: 'none',
        frameRate: { min: 25, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(6000000*coefficiente),       // 6Mbps
            standardPlus: Math.round(7000000*coefficiente),   // 7Mbps
            highQuality: Math.round(8000000*coefficiente),    // 8Mbps
            bestQuality: Math.round(9000000*coefficiente),    // 9Mbps
            ultraQuality: Math.round(18000000*coefficiente)   // 10Mbps
        }
    },
    {
        text: '3840x2160 4K UHD',
        width: 3840,
        height: 2160,
        resizeMode: 'none',
        frameRate: { min: 20, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(13000000*coefficiente),      // 13Mbps
            standardPlus: Math.round(15000000*coefficiente),  // 15Mbps
            highQuality: Math.round(17000000*coefficiente),   // 17Mbps
            bestQuality: Math.round(20000000*coefficiente),   // 20Mbps
            ultraQuality: Math.round(25000000*coefficiente)   // 23Mbps
        }
    },
    {
        text: 'IPhone / iOS (FullHD)',
        width: 1920,
        height: 1080,
        resizeMode: 'none',
        frameRate: { min: 30, ideal: 30, max: 60 },
        bitrates: {
            standard: Math.round(5000000*coefficiente),       // 5Mbps
            standardPlus: Math.round(5500000*coefficiente),   // 5.5Mbps
            highQuality: Math.round(6000000*coefficiente),    // 6Mbps
            bestQuality: Math.round(6500000*coefficiente),    // 6.5Mbps
            ultraQuality: Math.round(13000000*coefficiente)    // 7Mbps
        }
    }
];
*/

 // Definizione delle risoluzioni
  const resolutions = [
      {
          text: '640x360',
          width: 640,
          height: 360,
          resizeMode: 'none',
          frameRate: { min: 25, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(500000*coefficiente),        // 500Kbps
              standardPlus: Math.round(700000*coefficiente),    // 700Kbps
              highQuality: Math.round(1000000*coefficiente),    // 1Mbps
              bestQuality: Math.round(1300000*coefficiente),    // 1.3Mbps
              ultraQuality: Math.round(1600000*coefficiente)    // 1.6Mbps
          }
      },
      {
          text: '1024x576',
          width: 1024,
          height: 576,
          resizeMode: 'none',
          frameRate: { min: 25, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(1000000*coefficiente),       // 1Mbps
              standardPlus: Math.round(1300000*coefficiente),   // 1.3Mbps
              highQuality: Math.round(1600000*coefficiente),    // 1.6Mbps
              bestQuality: Math.round(2000000*coefficiente),    // 2Mbps
              ultraQuality: Math.round(2500000*coefficiente)    // 2.5Mbps
          }
      },
      {
          text: '1152x648',
          width: 1152,
          height: 648,
          resizeMode: 'none',
          frameRate: { min: 25, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(1200000*coefficiente),       // 1.2Mbps
              standardPlus: Math.round(1500000*coefficiente),   // 1.5Mbps
              highQuality: Math.round(1800000*coefficiente),    // 1.8Mbps
              bestQuality: Math.round(2200000*coefficiente),    // 2.2Mbps
              ultraQuality: Math.round(2800000*coefficiente)    // 2.8Mbps
          }
      },
      {
          text: '1280x720 HD',
          width: 1280,
          height: 720,
          resizeMode: 'none',
          frameRate: { min: 25, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(1500000*coefficiente),       // 1.5Mbps
              standardPlus: Math.round(2000000*coefficiente),   // 2Mbps
              highQuality: Math.round(2500000*coefficiente),    // 2.5Mbps
              bestQuality: Math.round(3000000*coefficiente),    // 3Mbps
              ultraQuality: Math.round(4000000*coefficiente)    // 4Mbps
          }
      },
      {
          text: '1366x768',
          width: 1366,
          height: 768,
          resizeMode: 'none',
          frameRate: { min: 25, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(1800000*coefficiente),       // 1.8Mbps
              standardPlus: Math.round(2300000*coefficiente),   // 2.3Mbps
              highQuality: Math.round(2800000*coefficiente),    // 2.8Mbps
              bestQuality: Math.round(3500000*coefficiente),    // 3.5Mbps
              ultraQuality: Math.round(4500000*coefficiente)    // 4.5Mbps
          }
      },
      {
          text: '1600x900',
          width: 1600,
          height: 900,
          resizeMode: 'none',
          frameRate: { min: 25, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(2200000*coefficiente),       // 2.2Mbps
              standardPlus: Math.round(2800000*coefficiente),   // 2.8Mbps
              highQuality: Math.round(3500000*coefficiente),    // 3.5Mbps
              bestQuality: Math.round(4000000*coefficiente),    // 4Mbps
              ultraQuality: Math.round(5000000*coefficiente)    // 5Mbps
          }
      },
      {
          text: '1920x1080 Full HD',
          width: 1920,
          height: 1080,
          resizeMode: 'none',
          frameRate: { min: 25, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(3000000*coefficiente),       // 3Mbps
              standardPlus: Math.round(3500000*coefficiente),   // 3.5Mbps
              highQuality: Math.round(4500000*coefficiente),    // 4.5Mbps
              bestQuality: Math.round(5500000*coefficiente),    // 5.5Mbps
              ultraQuality: Math.round(7000000*coefficiente)    // 7Mbps
          }
      },
      {
          text: '2560x1440',
          width: 2560,
          height: 1440,
          resizeMode: 'none',
          frameRate: { min: 25, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(5000000*coefficiente),       // 5Mbps
              standardPlus: Math.round(6000000*coefficiente),   // 6Mbps
              highQuality: Math.round(7000000*coefficiente),    // 7Mbps
              bestQuality: Math.round(8000000*coefficiente),    // 8Mbps
              ultraQuality: Math.round(10000000*coefficiente)   // 10Mbps
          }
      },
      {
          text: '3840x2160 4K UHD',
          width: 3840,
          height: 2160,
          resizeMode: 'none',
          frameRate: { min: 20, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(8000000*coefficiente),       // 8Mbps
              standardPlus: Math.round(10000000*coefficiente),  // 10Mbps
              highQuality: Math.round(13000000*coefficiente),   // 13Mbps
              bestQuality: Math.round(16000000*coefficiente),   // 16Mbps
              ultraQuality: Math.round(20000000*coefficiente)   // 20Mbps
          }
      },
      {
          text: 'IPhone / iOS (FullHD)',
          width: 1920,
          height: 1080,
          resizeMode: 'none',
          frameRate: { min: 30, ideal: 30, max: 60 },
          bitrates: {
              standard: Math.round(3000000*coefficiente),       // 3Mbps
              standardPlus: Math.round(3500000*coefficiente),   // 3.5Mbps
              highQuality: Math.round(4500000*coefficiente),    // 4.5Mbps
              bestQuality: Math.round(5500000*coefficiente),    // 5.5Mbps
              ultraQuality: Math.round(7000000*coefficiente)    // 7Mbps
          }
      }
  ];

// Funzioni di utility
function makeid(length) {
    return Array.from(crypto.getRandomValues(new Uint8Array(length)))
        .map(n => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'[n % 62])
        .join('');
}

const requestWakeLock = async () => {
    try {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', () => {
            console.log('Screen Wake Lock released:' + wakeLock.released, "info");
        });
        console.log('Screen Wake Lock acquired', "info");
    } catch (err) {
        console.error(`${err.name}, ${err.message}`);
    }
};

// Funzione ottimizzata per avviare l'applicazione
function boot_up() {
    clearTimeout(RL);
    cleanupResources();

    recorder = null;
    blobBuffer = null;
    blobBuffer = new CircularBuffer(bblen);

    if (slice_time > 0) {
        bblen = Math.round(((duration * 1000) + 1000) / slice_time);
        replayTime = slice_time;
    } else {
        bblen = max_bblen;
        replayTime = Math.round(((duration * 1000) + 1000) / (bblen - 1));
    }

    $("#duration").val(duration);
    $("#version").val(version);
    $("#vbps").val(vbps);

    console.log("bootUP /// BBLEN: " + bblen + " - duration: " + duration + " - replayTime: " + replayTime);

    blobBuffer = new CircularBuffer(bblen);

    if (camera_deviceID != null) {
        domCache.saveButton.disabled = true;
        saveReplay = false;
        tipologia = "";

        if (localStream != null) {
            localStream.getVideoTracks()[0].stop();
        }

        if (camera_deviceID != null) {
            $("#message_source").html("").hide();
        } else {
            $("#message_source").html("<h4>^ - Seleziona Ingresso Video</h4>").show();
        }

        let video_opt = {
            deviceId: camera_deviceID,
            aspectRatio: { ideal: 16/9 },
            powerEfficient: false,
            latency: { ideal: 0 }
        };

        resolutionById = resolutions[version];

        video_opt = {
            ...video_opt,
            width: resolutionById.width,
            height: resolutionById.height,
            resizeMode: resolutionById.resizeMode,
            frameRate: resolutionById.frameRate
        };

        let mediaConstraints = {
            video: video_opt,
            audio: audioSource === 'ambient' ? {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
                ideal: 'user'
            } : audioSource === 'esultanza' ? {
                echoCancellation: false,
                noiseSuppression: false,
                autoGainControl: true,
                ideal: 'environment'
            } : false
        };

        navigator.mediaDevices.getUserMedia(mediaConstraints)
            .then(mediaStream => {
                videoElement = document.getElementById('main_video');
                videoElement.srcObject = mediaStream;
                videoElement.muted = true;

                try {
                    const [track] = mediaStream.getVideoTracks();
                    const capabilities = track.getCapabilities();
                    const settings = track.getSettings();
                    $("#btn_impostazioni").html("Impostazioni: " + settings.width + "x" + settings.height + "@" + Math.round(settings.frameRate) + "fps");
                    localStream = mediaStream;

                    const input = document.querySelector('input[type="range"]');

                    if ('zoom' in settings) {
                        input.min = capabilities.zoom.min;
                        input.max = capabilities.zoom.max;
                        input.step = capabilities.zoom.step;
                        input.value = settings.zoom;
                        input.oninput = function(event) {
                            track.applyConstraints({
                                deviceId: { exact: camera_deviceID }, 
                                advanced: [{ zoom: event.target.value }]
                            });
                        };
                        input.hidden = false;
                    }
                } catch (error) {
                    console.log(error);
                }

                startRecording(mediaStream);
            })
            .catch(error => {
                console.error('Errore nell\'acquisizione del flusso video:', error);
            });

        clearTimeout(ul);
        ul = setTimeout(sblocca, replayTime * (bblen + 2));
    }

    $(".full_time").html(Math.round((replayTime * bblen) / 1000) + " Sec.");
    $(".half_time").html(Math.round(((replayTime * bblen) / 1000) / 2) + " Sec.");
}

// Ottimizzazione della funzione startRecording
function startRecording(stream) {
    console.log("startRecording!!!");
    try {
        if (vbps == "-1") {
            vbps_val = -1;
            recorder = new MediaRecorder(stream, {
                type: codecs,
                audioBitsPerSecond: 128000
            });
        } else {
            vbps_val = Math.round(resolutionById.bitrates[vbps]);
            console.log("vbps_val: " + vbps_val);
            
            recorder = new MediaRecorder(stream, {
                type: codecs,
                videoBitsPerSecond: vbps_val,
                audioBitsPerSecond: 128000
            });
        }

        recorder.addEventListener("dataavailable", (evt) => {
            blobBuffer.push(evt.data);
        });

        recorder.addEventListener("error", (error) => {
            console.error('Errore MediaRecorder:', error);
        });

        if (recorder.state === "inactive") {
            recorder.start();
        }

        stopRecording = recordLoop();

    } catch (error) {
        console.error('Errore avvio registrazione:', error);
    }
}

// Ottimizzazione del loop di registrazione
function recordLoop() {
    RL = setTimeout(() => {
        requestAnimationFrame(() => {
            if (recorder && recorder.state === "recording") {
                recorder.stop();
                recorder.start();
            }
            recordLoop();
        });
    }, replayTime);
    
    return () => {
        clearTimeout(RL);
        RL = null;
    };
}

// Ottimizzazione della pulizia delle risorse
function cleanupResources() {
    try {
        // Ferma e pulisci il recorder
        if (recorder) {
            if (recorder.state === "recording") {
                recorder.stop();
            }
            recorder = null;
        }
        
        // Ferma e pulisci tutti i track dello stream
        if (localStream) {
            localStream.getTracks().forEach(track => {
                track.stop();
                track.enabled = false;
            });
            localStream = null;
        }
        
        // Pulisci il buffer e libera memoria
        if (blobBuffer) {
            blobBuffer.clear();
            blobBuffer = null;
        }
        
        // Pulisci i timer
        if (RL) clearTimeout(RL);
        if (bu) clearTimeout(bu);
        if (ul) clearTimeout(ul);
        
        // Forza la garbage collection se disponibile
        if (window.gc) window.gc();
        
    } catch (e) {
        console.error('Errore durante la pulizia:', e);
    }
}

function unlock() {
    if (domCache.saveButton) {
        domCache.saveButton.innerHTML = "Salva Replay<br />Azione";
        domCache.saveButton.disabled = false;
    }
}

function lock() {
    if (domCache.saveButton) {
        domCache.saveButton.innerHTML = "Salva Replay<br />Azione";
        domCache.saveButton.disabled = true;
    }
}

async function salvaReplay(hmc) {
    console.log("salvaReplay con Web Worker");
    lock();

    if (tipologia !== "undefined" && typeof(tipologia) !== "undefined") {
        const nomeFile = new Date().toLocaleString("it-IT", {timeZone: "Europe/Rome"}).replaceAll(/[:, /]/g, "_");
        const partita = new Date().toLocaleString("it-IT", {timeZone: "Europe/Rome", day: "2-digit", month: "2-digit", year: "numeric"});

        const metadata = {
            nomeFile: nomeFile,
            tipologia: tipologia,
            bblen: hmc,
            OTT: OTT,
            token: token,
            vbps: vbps_val,
            version: version,
            hmc: hmc,
            text_home: txt_home + " " + home_goal,
            text_away: away_goal + " " + txt_away,
            minutes: ' @ Min. ' + curr_minutes + " " + indicatore_tempo,
            partita: txt_home + ' - ' + txt_away + ' (' + partita + ')',
            autore: autore
        };

        try {
            // Verifica se il Worker Manager è disponibile
            if (typeof blobWorkerInstance !== 'undefined' && blobWorkerInstance.isAvailable()) {
                // Usa Web Worker per preparare il replay (non blocca UI)
                console.log("Uso Web Worker per preparare replay...");
                const prepared = await blobWorkerInstance.prepareReplay(
                    [...blobBuffer],
                    metadata,
                    hmc
                );

                // Riconverti ArrayBuffer in Blob per IndexedDB
                const chunksAsBlobs = prepared.chunks.map(chunk =>
                    chunk.blob || new Blob([chunk.data], { type: chunk.type || 'video/webm' })
                );

                console.log(`Worker: preparati ${chunksAsBlobs.length} chunks, totale ${prepared.metadata.totalSize} bytes`);

                await uploadDB.addUpload({
                    video: chunksAsBlobs,
                    ...prepared.metadata
                });
            } else {
                // Fallback: esecuzione diretta nel main thread
                console.log("Fallback: preparazione replay nel main thread");
                let bufferCopy;

                if (hmc == bblen) {
                    bufferCopy = [...blobBuffer];
                } else {
                    bufferCopy = [...blobBuffer];
                    bufferCopy = bufferCopy.slice(-hmc);
                }

                await uploadDB.addUpload({
                    video: bufferCopy,
                    ...metadata
                });
            }

            if (ccau == null) {
                ccau = setInterval(checkConnectionAndUpload, 4000);
                fill = 0;
                console.log("ccau era null, riavvio checkConnectionAndUpload ogni 4 sec.");
            }

            updateUploadStatus();

        } catch (error) {
            console.error('Errore preparazione replay:', error);

            // Fallback in caso di errore del Worker
            try {
                let bufferCopy = hmc == bblen ? [...blobBuffer] : [...blobBuffer].slice(-hmc);
                await uploadDB.addUpload({
                    video: bufferCopy,
                    ...metadata
                });
                updateUploadStatus();
            } catch (fallbackError) {
                console.error('Errore anche nel fallback:', fallbackError);
            }
        }
    }
    unlock();
}

function updateUploadStatus() {
    try {
        if (!uploadDB.db) return;
        
        const transaction = uploadDB.db.transaction([uploadDB.storeName], 'readonly');
        const store = transaction.objectStore(uploadDB.storeName);
        const countRequest = store.count();
        
        countRequest.onsuccess = () => {
            const pendingUploads = countRequest.result;
            if (pendingUploads > 0) {
                $("#coda_file").html(`Upload in attesa: ${pendingUploads}`);
            } else {
                $("#coda_file").html("");
            }
        };
    } catch (error) {
        console.error('Errore aggiornamento stato upload:', error);
    }
}

// Ottimizzazione del processo di upload
async function salvaReplay_fromDB(dbitem) {
    saveReplay = false;
    const uploadPromises = [];
    
    try {
        // Invia prima la richiesta di creazione del job
        const formData = new FormData();
        formData.append('folder', dbitem.nomeFile + dbitem.tipologia);
        formData.append('chunk_num', dbitem.hmc);
        formData.append('vbps', dbitem.vbps);
        formData.append('version', dbitem.version);
        formData.append('OTT', dbitem.OTT);
        formData.append('token', dbitem.token);
        formData.append('text_home', dbitem.text_home);
        formData.append('text_away', dbitem.text_away);
        formData.append('minutes', dbitem.minutes);
        formData.append('partita', dbitem.partita);
        formData.append('autore', dbitem.autore);

        await fetch(SERVER + "/jobs.php", {
            method: 'POST',
            body: formData
        });

        // Carica tutti i chunk in parallelo con limite di concorrenza
        const chunkUploader = async (chunk, index) => {
            return upload(
                chunk, 
                `part${index}.webm`, 
                dbitem.nomeFile + dbitem.tipologia
            );
        };
        
        // Crea un array di promesse per ogni chunk
        for (let i = 0; i < dbitem.video.length; i++) {
            uploadPromises.push(chunkUploader(dbitem.video[i], i));
        }
        
        // Esegui tutte le promesse in parallelo con un limite di concorrenza
        const concurrencyLimit = 3; // Limita a 3 upload contemporanei
        for (let i = 0; i < uploadPromises.length; i += concurrencyLimit) {
            const batch = uploadPromises.slice(i, i + concurrencyLimit);
            await Promise.all(batch);
        }
        
    } catch (error) {
        console.error('Errore upload da DB:', error);
    }
}

// Manteniamo questa funzione per compatibilità, ma usiamo quella nuova
function salvaReplay_as(saveBuffer, nomeFile, tipologia, hmc) {
    file_da_caricare += hmc;
    file_totali += file_da_caricare;

    saveReplay = false;

    const uploadQueue = [];

    // Prepara la coda di upload
    for (let i = 0; i < saveBuffer.length; i++) {
        uploadQueue.push({
            blob: saveBuffer[i],
            fileName: `part${i}.webm`,
            folder: nomeFile + tipologia,
            OOT: OTT,
            token: token,
            chunk_num: hmc,
            vbps: vbps_val,
            version: version,
            text_home: txt_home + " " + home_goal,
            text_away: away_goal + " " + txt_away,
            minutes: ' @ Min. ' + curr_minutes + " " + indicatore_tempo
        });
    }

    // Funzione per processare la coda
    async function processUploadQueue() {
        const concurrentUploads = hmc; // Numero di upload simultanei
        const currentUploads = [];

        for (let i = 0; i < uploadQueue.length; i += concurrentUploads) {
            const batch = uploadQueue.slice(i, i + concurrentUploads);
            const uploadPromises = batch.map(item => {
                return upload(item.blob, item.fileName, item.folder);
            });

            try {
                await Promise.all(uploadPromises);
            } catch (error) {
                console.error('Errore durante l\'upload:', error);
            }
        }
    }

    // Avvia gli upload
    try {
        const formData = new FormData();
        formData.append('folder', nomeFile + tipologia);
        formData.append('chunk_num', hmc);
        formData.append('vbps', vbps_val);
        formData.append('version', version);
        formData.append('OTT', OTT);
        formData.append('token', token);
        formData.append('text_home', txt_home + " " + home_goal);
        formData.append('text_away', away_goal + " " + txt_away);
        formData.append('minutes', ' @ Min. ' + curr_minutes + " " + indicatore_tempo);

        fetch(SERVER + "/jobs.php", {
            method: 'POST',
            body: formData
        })
        .then(() => processUploadQueue())
        .catch(error => {
            console.error('Errore generale:', error);
        });
    } catch (error) {
        console.error('Errore generale:', error);
    }
}

function upload(blob, fileName, folder) {
    return new Promise((resolve, reject) => {
        const serverUrl = SERVER + 'upload/save.php';
        const formData = new FormData();
        formData.append('video-filename', fileName);
        formData.append('folder', folder);
        formData.append('OTT', OTT);
        formData.append('token', token);
        formData.append('video-blob', blob);

        fetch(serverUrl, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (!response.ok) {
                throw new Error('Errore nella risposta del server');
            }
            file_da_caricare--;
            resolve();
        }).catch(error => {
            file_da_caricare--;
            file_errore++;
            reject(error);
        });
    });
}

function coda_file() {
    if (file_da_caricare > 0) {
        $("#coda_file").html("Upload: " + (100 - Math.round((file_da_caricare * 100) / file_totali)) + "%");
    } else {
        $("#coda_file").html("");
        file_totali = 0;
    }
}

function save_Replay(tags, home, away, hmc) {
    hide_tags();
    resetActionTimer();

    domCache.saveButton.disabled = true;
    domCache.saveButton.innerHTML = "Caricamento in<br />corso...";
    home_goal = parseInt(home_goal) + parseInt(home);
    away_goal = parseInt(away_goal) + parseInt(away);
    
    $("#home_goal").val(home_goal);
    $("#away_goal").val(away_goal);

    aggiorna_tabellone();
    
    setTimeout(() => {
        saveReplay = true;
        tipologia = tags + (audioSource === 'ambient' ? '-audio' : '') + (audioSource === 'esultanza' ? '-esultanza' : '');

        salvaReplay(hmc);
        if (event_push != "") {
            propagaSalva(tags);
        }
    }, replayTime);
    
    $(".primo_piano").show();
}

function resetActionTimer() {
    clearInterval(action_timer_interval);
    action_timer_running = false;
    action_timer_seconds = 0;
    document.getElementById('action_timer').textContent = '00:00';
    document.getElementById('timer_button').textContent = 'Avvia Timer';
}

function updateActionTimer() {
    action_timer_seconds++;
    const minutes = Math.floor(action_timer_seconds / 60).toString().padStart(2, '0');
    const seconds = (action_timer_seconds % 60).toString().padStart(2, '0');
    document.getElementById('action_timer').textContent = `${minutes}:${seconds}`;
}

function toggleActionTimer() {
    if (action_timer_running) {
        // Reset del timer (non pausa)
        resetActionTimer();
    } else {
        // Avvia il timer
        action_timer_interval = setInterval(updateActionTimer, 1000);
        document.getElementById('timer_button').textContent = 'Ferma Timer';
        action_timer_running = true;
    }
}

function hide_tags() {
    $("#zoomer").show();
    $("#replay_div").hide();
    $(".tags").hide();
}

function aggiorna_crediti() {
    const formData = new FormData();
    formData.append('OTT', OTT);
    formData.append('token', token);
        
    fetch(SERVER + "crediti_residui.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(response => {
        $("#crediti_residui").html("<a href='"+SERVER+"ricarica' class='btn btn-sm btn-light'><img src='favicons/buy.png'>&nbsp;" + response + "</a>");
    })
    .catch(error => {
        console.error('Errore aggiornamento crediti:', error);
    });
}

function is_logged() {
    const formData = new FormData();
    formData.append('OTT', OTT);
    formData.append('token', token);
        
    fetch(SERVER + "API/is_logged.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(response => {
        if (response.code == "401") {
            location.href = `login.html?v=${new Date().getTime()}`;
        }
    })
    .catch(error => {
        console.error('Errore verifica login:', error);
    });
}

// Ottimizzazione per il controllo della connessione e l'upload
async function checkConnectionAndUpload() {
    updateUploadStatus();
    
    if (isUploading) return;
    
    const isGoodConnection = () => {
        if (!navigator.onLine) return false;
        
        if ('connection' in navigator && 'effectiveType' in navigator.connection) {
            const connection = navigator.connection;
            $("#btn_conn").html(connection.effectiveType);
            return connection.effectiveType === '3g' || connection.effectiveType === '4g';
        }
        
        return true; // Se non possiamo determinare, assumiamo che sia buona
    };
    
    if (isGoodConnection()) {
        $("#btn_conn").removeClass("btn-light").removeClass("btn-danger").addClass("btn-success");
        isUploading = true;
        
        try {
            const nextUpload = await uploadDB.getNextUpload();
            if (nextUpload) {
                await salvaReplay_fromDB(nextUpload);
                await uploadDB.removeUpload(nextUpload.id);
                fill = 0; // Reset del contatore quando abbiamo successo
            } else {
                fill++;
                console.log("fill: " + fill);
                if (fill > 1) {
                    clearInterval(ccau);
                    console.log("provato " + fill + " volte, sempre nessun upload, stoppo controllo.");
                    fill = 0;
                    ccau = null;
                }
            }
        } catch (error) {
            console.error('Upload failed:', error);
        } finally {
            isUploading = false;
        }
    } else {
        $("#btn_conn").html("Bad");
        $("#btn_conn").removeClass("btn-light").removeClass("btn-success").addClass("btn-danger");
    }
}

$(document).ready(async function() {
    // Inizializza la cache DOM
    domCache.init();

    // Inizializza Web Worker per elaborazione blob (non blocca UI)
    if (typeof blobWorkerInstance !== 'undefined') {
        try {
            await blobWorkerInstance.init();
            console.log('Web Worker inizializzato:', blobWorkerInstance.isAvailable() ? 'attivo' : 'fallback mode');
        } catch (e) {
            console.warn('Web Worker non disponibile, uso fallback:', e);
        }
    }

    // Gestione della visibilità per ottimizzare le risorse
/*    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            console.log("App in background, ottimizzazione risorse...");
            if (recorder && recorder.state === "recording") {
                recorder.pause();
            }
        } else if (document.visibilityState === 'visible') {
            console.log("App in primo piano, ripristino...");
            if (recorder && recorder.state === "paused") {
                recorder.resume();
            } else if (!recorder || recorder.state === "inactive") {
                boot_up();
            }
        }
    });
   */
    // Verifica login
    is_logged();

const mediaQuery = window.matchMedia("(orientation: portrait)");
	// Verifica l'orientamento iniziale
	handleOrientationChange(mediaQuery);
	// Aggiungi il listener per i cambiamenti
	mediaQuery.addListener(handleOrientationChange);
	if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
	{
		navigator.mediaDevices.getUserMedia({ video: true })
			.then(function() {
				//video.srcObject = stream;
			})
			.catch(function(error) {
				console.error("Errore nell'accesso alla fotocamera:", error);
				alert("Non è stato possibile accedere alla fotocamera");
			});
	} else {
		alert("Il tuo browser non supporta l'accesso alla fotocamera");
	}





    
    // Inizializzazione del video
    if (document.getElementById('main_video') && document.getElementById('main_video').style.display != 'none') {
        videoElement = document.getElementById('main_video');

        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("service-worker.js");
        }
        
        requestWakeLock();
        
        if (camera_deviceID != null) {
            $("#message_source").html("").hide();
        } else {
            $("#message_source").html("<h4>^ - Seleziona Ingresso Video</h4>").show();
        }
        
        $("#audio_source").val(audioSource);
        
        if (bu) clearTimeout(bu);
        bu = setTimeout(function(){
            document.getElementById('main_video').srcObject = null;
            boot_up();
        }, 500);
        
        // Imposta intervalli per funzioni periodiche
        setInterval(aggiorna_crediti, 30000);
        
        // Inizializza il controllo degli upload periodici
        ccau = setInterval(checkConnectionAndUpload, 4000);
        
        // Gestione eventi online/offline
        window.addEventListener('online', function() {
            console.log("Back On-Line!");
            if (ccau == null) {
                console.log("start checkConnectionAndUpload after Back On-Line!");
                $("#btn_conn").removeClass("btn-light").removeClass("btn-danger").addClass("btn-success");
                $("#btn_conn").html("...");
                ccau = setInterval(checkConnectionAndUpload, 4000);
                fill = 0;
            }
        });
        
        window.addEventListener('offline', function() {
            console.log("Go to Off-Line!");
            if (ccau != null) {
                console.log("STOP checkConnectionAndUpload after Off-Line!");
                $("#btn_conn").removeClass("btn-light").removeClass("btn-success").addClass("btn-danger");
                $("#btn_conn").html("Bad");
                clearInterval(ccau);
                ccau = null;
                fill = 0;
            }
        });
        
        // Inizializza i campi del modulo
        $("#txt_home").val(txt_home);
        $("#home_goal").val(home_goal);
        $("#txt_away").val(txt_away);
        $("#away_goal").val(away_goal);
        $(".home_tags").html(txt_home);
        $(".away_tags").html(txt_away);
        
        // Imposta indicatori di tempo
        $(".full_time").html(Math.round((replayTime * bblen)/1000) + " Sec.");
        $(".half_time").html(Math.round(((replayTime * bblen)/1000)/2) + " Sec.");
        
        // Imposta l'intervallo per il minutaggio
        minutaggio = setInterval(aggiungi_minuto, 60000);
        aggiorna_tabellone();
    }
    
    // Recupera le informazioni dal server all'avvio
    fetch(SERVER + 'API/call_index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            OTT: OTT,
            token: token,
            event: event_push
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!storedId) {
            storedId = data.iduser;
            localStorage.setItem('iduser', storedId);
        }
        
        // Aggiorna crediti
        $("#crediti_residui").html("<a href='"+SERVER+"ricarica' class='btn btn-sm btn-light'><img src='favicons/buy.png'>&nbsp;" + `Crediti: ${data.credits_left}` + "</a>");
        
        // Inizializza valori del form
        const durationEl = document.getElementById('duration');
        if (durationEl) durationEl.value = duration;
        
        const versionEl = document.getElementById('version');
        if (versionEl) versionEl.value = version;

        const vbpsEl = document.getElementById('vbps');
        if (vbpsEl) vbpsEl.value = vbps;

        const audioSourceEl = document.getElementById('audio_source');
        if (audioSourceEl) audioSourceEl.value = audioSource;
    })
    .catch(error => {
        console.error('Errore recupero dati iniziali:', error);
    });
    
    // Inizializza Pusher se event_push è definito
    if (event_push !== "") {
        const publicConfig = window.YALPER_PUBLIC_CONFIG || { pusherKey: '2da066d21277e3a81c67', pusherCluster: 'eu' };
        const pusher = new Pusher(publicConfig.pusherKey, {
            cluster: publicConfig.pusherCluster
        });
        
        const channel = pusher.subscribe('yalper');
        channel.bind('save-replay-event['+event_push+']', function(data) {
            if (OTT != data.sender) {
                saveReplay = true;
                tipologia = data.tag;
                domCache.saveButton.disabled = true;
                domCache.saveButton.innerHTML = "Caricamento in<br />corso...";
                salvaReplay(data.tag);
            }
        });
    }

 // Add event listeners for reactions and download
    document.getElementById('like-btn').addEventListener('click', () => sendReaction('1'));
    document.getElementById('dislike-btn').addEventListener('click', () => sendReaction('2'));
    document.getElementById('download').addEventListener('click', () => download());
    
    document.getElementById('timer_button').addEventListener('click', toggleActionTimer);


});

function aggiungi_minuto() {
    curr_minutes = parseInt(curr_minutes) + 1;
    sessionStorage.setItem("curr_minutes", curr_minutes);
    
    $("#minutes").val(curr_minutes);
    aggiorna_tabellone();
}

function aggiorna_tabellone() {
    $("#overlay").html(`<span class='btn btn-light' style='opacity: 0.7;'><img src='img/pencil.png' style='height:17px;'>&nbsp;&nbsp;${txt_home} ${home_goal} - ${away_goal} ${txt_away} @ Min. ${curr_minutes} ${indicatore_tempo}</span>`);
}

// Gestione eventi touch con delegate pattern
function getTouches(evt) {
    return evt.touches || evt.originalEvent.touches;
}

let xDown = null;
let yDown = null;

function handleTouchStart(evt) {
    const firstTouch = getTouches(evt)[0];
    xDown = firstTouch.clientX;
    yDown = firstTouch.clientY;
}

function handleTouchMove(evt) {
    if (!xDown || !yDown) return;

    const xUp = evt.touches[0].clientX;
    const yUp = evt.touches[0].clientY;
    const xDiff = xDown - xUp;
    const yDiff = yDown - yUp;

    if (Math.abs(yDiff) > Math.abs(xDiff) && !$("#salva_replay").is(':disabled')) {
        if (yDiff > 0) {
            if (!$("#replay_div").is(':visible'))
                $(".tags").show();
        } else {
            $(".tags").hide();
        }
    }

    xDown = yDown = null;
}

// Ottimizzazione per la gestione della lista dei dispositivi
function device_list() {
	
	console.log("DEVICE_LIST");
    $("#settings").show();
    const cameraListContainer = document.getElementById('camera_list');
    cameraListContainer.innerHTML = '';
    
    // Crea un DocumentFragment per migliorare le performance del DOM
    const fragment = document.createDocumentFragment();
    
    navigator.mediaDevices.enumerateDevices()
        .then(devices => {
            // Filtra subito per ridurre il numero di operazioni
            const videoDevices = devices.filter(d => d.kind === "videoinput" && d.label);
            
            videoDevices.forEach(device => {
                const isSelected = camera_deviceID === device.deviceId;
                const wrapper = document.createElement('div');
                
                wrapper.innerHTML = `
                    <a class='btn btn-xs btn-info'
                       style='width:100%;
                              font-size:${isSelected ? 14 : 14}px;
                              padding:2px;
                              margin-bottom:3px;
                              text-align:${isSelected ? 'left' : 'right'};'
                       href='javascript:;'
                       onclick='select_camera("${device.deviceId}")'>
                       ${isSelected ? `<b>>>${device.label}<<</b>` : device.label}
                    </a><br />`;
                
                fragment.appendChild(wrapper);
            });
            
            // Aggiungi il fragment al DOM in una singola operazione
            cameraListContainer.appendChild(fragment);
        })
        .catch(err => console.error(`${err.name}: ${err.message}`));
}

function select_camera(dID) {
    camera_deviceID = dID;
    localStorage.setItem("cameraID", camera_deviceID);
    device_list();
    
    if (camera_deviceID != null) {
        clearTimeout(bu);
        bu = setTimeout(function() {
            boot_up();
        }, 250);
    }
}

function select_audio(dID) {
    audio_deviceID = dID;
    localStorage.setItem("audioID", audio_deviceID);
    device_list();
    
    if (camera_deviceID != null) {
        clearTimeout(bu);
        bu = setTimeout(function() {
            boot_up();
        }, 250);
    }
}

function show_tags() {
    $("#replay_div").hide();

    if ($(".tags").is(":visible")) {
        $("#zoomer").show();
        $(".tags").hide();
    } else {
        $("#zoomer").hide();
        $(".tags").show();
    }
}

function gcd(a, b) {
    return (b == 0) ? a : gcd(b, a % b);
}

function sblocca() {
    domCache.saveButton.innerHTML = "Salva Replay<br />Azione";
    domCache.saveButton.disabled = false;
}

// Upload ottimizzato tramite Fetch API
function ajax_upload(blob, fileName, folder) {
    const serverUrl = SERVER + 'upload/save.php';
    const formData = new FormData();
    formData.append('video-filename', fileName);
    formData.append('folder', folder);
    formData.append('video-blob', blob);

    return fetch(serverUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Errore upload');
        file_da_caricare--;
        domCache.saveButton.innerHTML = "Salva Replay<br />Azione";
        domCache.saveButton.disabled = false;
    })
    .catch(error => {
        console.error('Errore upload:', error);
        file_da_caricare--;
    });
}

function toggle(dest) {
    const element = document.getElementById(dest);
    if (element) {
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
}

function gestisci_replay() {
    if ($("#replay_div").is(':visible')) {
        $("#btn_ruota").show();
        nascondi_Replay();
    } else {
        $("#btn_ruota").hide();
        vedi_Replay();
    }
}

function vedi_Replay() {
    const panel = document.getElementById("wrapper_settings_camera");
    if (panel) panel.style.display = 'none';

    $("#zoomer").hide();
    $("#timer_button").hide();
    $("#action_timer").hide();
    load_replay_btn(6);
    $("#replay_div").show();
}

function nascondi_Replay() {
    $("#zoomer").show();
    $("#timer_button").show();
    $("#action_timer").show();
    $("#replay_div").hide();
    if (hls) {
        hls.destroy();
    }
}

function visualizza_replay(obj, filename) {
    $("#download").attr('data-filename', atob(filename));

    const iduser = localStorage.getItem('iduser');
    updateReactions(filename, iduser);

    const video = document.getElementById('player');

    // Reset selezioni precedenti
    selezionati();
    $(obj).find('img').css("border-color", "#22F");
    last_video = SERVER + "sharing.php?f=" + filename;

    // Pulisci l'istanza hls precedente
    if (hls) {
        hls.destroy();
    }

    // Crea nuova istanza
    hls = new Hls({
        debug: false,
        enableWorker: true
    });
    
    hls.on(Hls.Events.ERROR, function(event, data) {
        if (data.fatal) {
            console.error('HLS error:', data);
            switch(data.type) {
                case Hls.ErrorTypes.NETWORK_ERROR:
                    console.log("Network error");
                    hls.startLoad();
                    break;
                case Hls.ErrorTypes.MEDIA_ERROR:
                    console.log("Media error");
                    hls.recoverMediaError();
                    break;
                default:
                    hls.destroy();
                    break;
            }
        }
    });

    hls.loadSource(atob(filename));
    hls.attachMedia(video);
    
    hls.on(Hls.Events.MANIFEST_PARSED, function() {
        video.play().catch(function(error) {
            console.log("Play promise failed:", error);
        });
    });
    
    $("#player_modal").show();
    $("#chiudi_player").show();
}



function load_replay_btn(div,day)
{
$("#replay_btn").html("<h1>Aggiornamento...</h1>");
			$.ajax({
				  method: "POST",
				  url: SERVER + "reload_replay_btn.php",
				  data:{div:div,day:day,OTT:OTT,token:token}
				})
				  .done(function( response ) {
					$("#replay_btn").html(response);
				  });
}


// Ottimizzazione caricamento bottoni replay
function __load_replay_btn(div, day) {
    $("#replay_btn").html("<h1>Aggiornamento...</h1>");
    
    const formData = new FormData();
    formData.append('div', div);
    formData.append('day', day || '');
    formData.append('OTT', OTT);
    formData.append('token', token);
    
    fetch(SERVER + "reload_replay_btn.php", {
        method: "POST",
        body: formData,
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.text())
    .then(response => {
        $("#replay_btn").html(response);
    })
    .catch(error => {
        console.error('Errore caricamento replay:', error);
        $("#replay_btn").html("<h1>Errore di caricamento</h1>");
    });
}

function switch_sel_desel(event) {
    event.preventDefault();
    if ($("#chk_all").is(":checked")) {
        seleziona_tutti();
    } else {
        deseleziona_tutti();
    }
    
    selezionati();
}

function seleziona_tutti() {
    document.querySelectorAll('.selezionati').forEach(el => {
        el.checked = true;
    });
}

function deseleziona_tutti() {
    document.querySelectorAll('.selezionati').forEach(el => {
        el.checked = false;
    });
}

// Ottimizzazione performance con query selettori
function selezionati() {
    // Cache dei selettori DOM
    const checkboxes = document.querySelectorAll('.selezionati');
    const condividiSingolo = document.getElementById('condividi_file_singolo');
    const condividiMultipli = document.getElementById('condividi_files_multipli');
    const eliminaVideo = document.getElementById('elimina_video');
    
    const selectedFiles = [];
    let count = 0;
    
    checkboxes.forEach(checkbox => {
        const imgElement = checkbox.closest('div').parentElement.querySelector('a img');
        
        if (checkbox.checked) {
            count++;
            selectedFiles.push(checkbox.getAttribute('file'));
            imgElement.style.borderColor = '#F22';
        } else {
            imgElement.style.borderColor = '#146c43';
        }
    });
    
    // Aggiorna visibilità controlli una sola volta
    const hasSelection = count > 0;
    condividiSingolo.style.display = hasSelection ? 'none' : 'block';
    condividiMultipli.style.display = hasSelection ? 'block' : 'none';
    eliminaVideo.style.display = hasSelection ? 'block' : 'none';
    
    return selectedFiles.join('|');
}

function hide_all() {
    nascondi_Replay();
    if ($(".tags").length) {
        $(".tags").hide();
    }
}

function propagaSalva(tags) {
    fetch('trigger_save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            event_push: event_push,
            tag: tags,
            sender: OTT
        })
    }).catch(error => {
        console.error('Errore propagazione salvataggio:', error);
    });
}

// Gestione efficiente con un singolo handler per tutti gli elementi .primo_piano
$(document).on('click', '.primo_piano', function() {
    const id = $(this).attr("id");
    
    if ($(".primo_piano:visible").length > 1) {
        $(".primo_piano:not(#"+id+")").hide();
    } else {
        $(".primo_piano").show();
    }
});

function visualizza_panel(dest) {
    const panel = document.getElementById(dest);
    const otherPanels = document.querySelectorAll(`.pannello:not(#${dest})`);
    
    // Nascondi tutti gli altri pannelli
    otherPanels.forEach(p => p.style.display = 'none');

    if (dest == 'wrapper_settings_partita') {
        $("#zoomer").hide();
        clearInterval(minutaggio);
    }
    
    if (dest == 'wrapper_settings_camera') {
        device_list();
        $("#zoomer").hide();
    }

    if (panel.style.display === 'none' || !panel.style.display) {
        $("#zoomer").hide();
        panel.style.display = 'block';
    } else {
        $("#zoomer").show();
        minutaggio = setInterval(aggiungi_minuto, 60000);
        panel.style.display = 'none';
    }
}

function delete_video() {
    const consenso = confirm("Eliminare PERMANTEMENTE i file selezionati?");
    if (consenso) {
        const files = selezionati();
        if (files) {
            fetch(SERVER + 'delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `fs=${encodeURIComponent(files)}&OTT=${encodeURIComponent(OTT)}&token=${encodeURIComponent(token)}`
            })
            .then(response => response.json())
            .then(data => {
                load_replay_btn(6);
                alert(data.message);
            })
            .catch(error => {
                console.error('Errore eliminazione:', error);
                alert('Errore durante l\'eliminazione');
            });
        }
    }
}

// Gestione modale con una sola istanza
let sharingModal;

function closeModal() {
    if (sharingModal) {
        sharingModal.hide();
    }
}

function share_modal() {
    if (!sharingModal) {
        sharingModal = new bootstrap.Modal(document.getElementById('sharingModal'));
    }
    sharingModal.show();
}

async function onShare() {
    const files = selezionati();
    const title = document.getElementById('title').value;
    const visibility = document.getElementById('visibility').value;
    
    try {
        let url;
        if (files) {
            const response = await fetch(SERVER + 'shorter.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `files=${encodeURIComponent(files)}&title=${encodeURIComponent(title)}&visibility=${visibility}&OTT=${encodeURIComponent(OTT)}&token=${encodeURIComponent(token)}`
            });
            if (!response.ok) {
                throw new Error(await response.text());
            }
            const data = await response.text();
            url = `https://yalper.it/sharing.php?fs=${data}`;
        } else {
            url = last_video;
        }
        
        await shareContent(title, url);
    } catch (err) {
        alert(`Errore: ${err}`);
    }
}

async function shareContent(title, url) {
    const text = "Ti invio un video fatto con YalpeR.it ";
    try {
        await navigator.share({
            title,
            url,
            text
        });
        alert('Condiviso con successo!');
    } catch (err) {
        if (err.name !== 'AbortError') {
            alert(`Errore durante la condivisione: ${err}`);
        }
    }
}

// Ottimizzazione del gestore dell'orientamento
function handleOrientationChange(e) {
    if (document.getElementById('main_video') && document.getElementById('main_video').style.display != 'none') {
        hide_tags();
        nascondi_Replay();

        blobBuffer = new CircularBuffer(bblen);

        domCache.saveButton.disabled = true;
        saveReplay = false;
        tipologia = "";

        clearTimeout(bu);
        bu = setTimeout(function() {
            document.getElementById('main_video').srcObject = null;
            boot_up();
        }, 500);
    }
}

// Ottimizzazione download con Promise e fetch
function download() {
    const link = document.getElementById('download').getAttribute('data-filename');
    const downloadBtn = document.getElementById('download');
    
    if (downloadBtn.innerHTML !== "Attendere...") {
        downloadBtn.disabled = true;
        downloadBtn.innerHTML = "Attendere...";
        
        fetch(SERVER + 'download.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `fn=${encodeURIComponent(link)}`
        })
        .then(response => {
            if (!response.ok) throw new Error('Download failed');
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const fname = basename(link).replace('.m3u8', '.mp4');
            a.download = fname;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
        })
        .catch(error => {
            alert(`Errore durante il download: ${error.message}`);
        })
        .finally(() => {
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = "Download";
        });
    }
}

// Ottimizzazione aggiornamento reazioni
function updateReactions(link, iduser) {
    fetch(`https://yalper.it/API/get_reaction.php?link=${encodeURIComponent(link)}&iduser=${iduser}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('view-counter').textContent = data.view;
            document.getElementById('like-count').textContent = data.liked;
            document.getElementById('dislike-count').textContent = data.disliked;
            
            document.getElementById('like-btn').classList.toggle('active', data.user_reaction === '1');
            document.getElementById('dislike-btn').classList.toggle('active', data.user_reaction === '2');
        })
        .catch(error => console.error('Error:', error));
}

// Invio reazione ottimizzato
function sendReaction(action) {
    const link = document.getElementById('download').getAttribute('data-filename');
    const iduser = localStorage.getItem('iduser');

    fetch(SERVER + 'API/reaction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `link=${encodeURIComponent(link)}&iduser=${iduser}&action=${action}`
    })
    .then(response => response.json())
    .then(() => {
        updateReactions(link, iduser);
    })
    .catch(error => console.error('Error:', error));
}

function basename(path) {
    return path.split('/').reverse()[0];
}

// Funzioni di impostazione ottimizzate
function change_duration() {
    duration = document.getElementById("duration").value;
    localStorage.setItem('duration', duration);
    
    clearTimeout(bu);
    bu = setTimeout(function() {
        boot_up();
    }, 250);
}

function change_version() {
    version = document.getElementById("version").value;
    localStorage.setItem('version', version);
    
    clearTimeout(bu);
    bu = setTimeout(function() {
        boot_up();
    }, 250);
}

function change_vbps() {
    vbps = document.getElementById("vbps").value;
    localStorage.setItem('vbps', vbps);
    
    clearTimeout(bu);
    bu = setTimeout(function() {
        boot_up();
    }, 250);
}

function save_audio_source(value) {
    audioSource = value;
    localStorage.setItem('audioSource', value);

    clearTimeout(bu);
    bu = setTimeout(function() {
        boot_up();
    }, 250);
}

// Cleanup risorse quando l'utente lascia la pagina
window.addEventListener('beforeunload', function() {
    // Termina il Web Worker
    if (typeof blobWorkerInstance !== 'undefined' && blobWorkerInstance.isAvailable()) {
        blobWorkerInstance.terminate();
        console.log('Web Worker terminato');
    }

    // Pulisci altre risorse
    cleanupResources();
});

// Cleanup anche su pagehide (per dispositivi mobile)
window.addEventListener('pagehide', function() {
    if (typeof blobWorkerInstance !== 'undefined' && blobWorkerInstance.isAvailable()) {
        blobWorkerInstance.terminate();
    }
});
