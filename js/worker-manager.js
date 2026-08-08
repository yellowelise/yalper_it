/**
 * BlobWorkerManager - Gestisce la comunicazione con il Web Worker per blob video
 * Singleton pattern per avere un'unica istanza condivisa
 */
class BlobWorkerManager {
    constructor() {
        this.worker = null;
        this.pendingRequests = new Map();
        this.requestId = 0;
        this.isReady = false;
        this.workerPath = 'js/blob-worker.js';
        this.defaultTimeout = 30000; // 30 secondi
    }

    /**
     * Inizializza il Web Worker
     * @returns {Promise<void>}
     */
    init() {
        return new Promise((resolve, reject) => {
            // Se già inizializzato, risolvi subito
            if (this.isReady && this.worker) {
                resolve();
                return;
            }

            try {
                // Verifica supporto Web Workers
                if (typeof Worker === 'undefined') {
                    console.warn('Web Workers non supportati, uso fallback');
                    this.isReady = false;
                    resolve();
                    return;
                }

                this.worker = new Worker(this.workerPath);

                this.worker.onmessage = (e) => this.handleMessage(e);
                this.worker.onerror = (e) => this.handleError(e);

                this.isReady = true;
                console.log('BlobWorkerManager inizializzato con successo');
                resolve();

            } catch (error) {
                console.warn('Errore inizializzazione Web Worker, uso fallback:', error);
                this.isReady = false;
                resolve(); // Non rejectiamo, useremo il fallback
            }
        });
    }

    /**
     * Gestisce i messaggi in arrivo dal Worker
     * @param {MessageEvent} e
     */
    handleMessage(e) {
        const { action, requestId, payload, error } = e.data;
        const pending = this.pendingRequests.get(requestId);

        if (!pending) {
            console.warn('Ricevuta risposta per richiesta sconosciuta:', requestId);
            return;
        }

        // Pulisci timeout e rimuovi dalla mappa
        if (pending.timeoutId) {
            clearTimeout(pending.timeoutId);
        }
        this.pendingRequests.delete(requestId);

        // Risolvi o rigetta la promise
        if (action === 'ERROR') {
            pending.reject(new Error(error));
        } else {
            pending.resolve(payload);
        }
    }

    /**
     * Gestisce errori del Worker
     * @param {ErrorEvent} e
     */
    handleError(e) {
        console.error('Worker error:', e.message, e);

        // Reject tutte le richieste pending
        for (const [id, pending] of this.pendingRequests) {
            if (pending.timeoutId) {
                clearTimeout(pending.timeoutId);
            }
            pending.reject(new Error(`Worker error: ${e.message}`));
        }
        this.pendingRequests.clear();

        // Prova a riavviare il worker
        this.restart();
    }

    /**
     * Riavvia il Worker dopo un errore
     */
    async restart() {
        console.log('Tentativo riavvio Worker...');
        this.terminate();
        await new Promise(resolve => setTimeout(resolve, 100));
        await this.init();
    }

    /**
     * Invia un messaggio al Worker
     * @param {string} action - Azione da eseguire
     * @param {Object} payload - Dati da inviare
     * @param {Array} transferables - Oggetti trasferibili (opzionale)
     * @param {number} timeout - Timeout in ms (opzionale)
     * @returns {Promise<any>}
     */
    sendMessage(action, payload, transferables = [], timeout = this.defaultTimeout) {
        return new Promise((resolve, reject) => {
            // Se Worker non disponibile, usa fallback
            if (!this.isReady || !this.worker) {
                return this.fallbackExecute(action, payload)
                    .then(resolve)
                    .catch(reject);
            }

            const requestId = ++this.requestId;

            // Setup timeout
            const timeoutId = setTimeout(() => {
                if (this.pendingRequests.has(requestId)) {
                    this.pendingRequests.delete(requestId);
                    reject(new Error(`Worker timeout per azione: ${action}`));
                }
            }, timeout);

            // Salva la richiesta pending
            this.pendingRequests.set(requestId, { resolve, reject, timeoutId });

            // Invia messaggio al Worker
            try {
                this.worker.postMessage(
                    { action, payload, requestId },
                    transferables
                );
            } catch (error) {
                clearTimeout(timeoutId);
                this.pendingRequests.delete(requestId);

                // Fallback in caso di errore postMessage
                return this.fallbackExecute(action, payload)
                    .then(resolve)
                    .catch(reject);
            }
        });
    }

    /**
     * Esecuzione fallback nel main thread (per browser senza Web Worker)
     * @param {string} action
     * @param {Object} payload
     * @returns {Promise<any>}
     */
    async fallbackExecute(action, payload) {
        console.log(`Fallback execution per: ${action}`);

        switch (action) {
            case 'PREPARE_REPLAY':
                return this.fallbackPrepareReplay(payload);
            case 'MERGE_BLOBS':
                return this.fallbackMergeBlobs(payload);
            case 'CALCULATE_STATS':
                return this.fallbackCalculateStats(payload);
            default:
                throw new Error(`Azione fallback non implementata: ${action}`);
        }
    }

    /**
     * Fallback: prepara replay nel main thread
     */
    async fallbackPrepareReplay({ bufferArray, metadata, sliceCount }) {
        const chunks = sliceCount < bufferArray.length
            ? bufferArray.slice(-sliceCount)
            : [...bufferArray];

        const validChunks = chunks
            .map((blob, index) => {
                if (!blob || blob.size === 0) return null;
                return {
                    index,
                    blob, // Nel fallback manteniamo il blob diretto
                    size: blob.size,
                    type: blob.type || 'video/webm'
                };
            })
            .filter(c => c !== null);

        const totalSize = validChunks.reduce((sum, c) => sum + c.size, 0);

        return {
            chunks: validChunks,
            metadata: {
                ...metadata,
                totalSize,
                chunkCount: validChunks.length,
                preparedAt: Date.now(),
                fallback: true
            }
        };
    }

    /**
     * Fallback: unisci blob nel main thread
     */
    async fallbackMergeBlobs({ chunks, mimeType = 'video/webm' }) {
        const sortedChunks = [...chunks].sort((a, b) => a.index - b.index);
        const blobs = sortedChunks.map(c => c.blob || new Blob([c.data], { type: c.type }));
        const merged = new Blob(blobs, { type: mimeType });

        return {
            blob: merged,
            size: merged.size,
            type: mimeType,
            chunksCount: chunks.length
        };
    }

    /**
     * Fallback: calcola statistiche nel main thread
     */
    fallbackCalculateStats({ bufferArray }) {
        if (!bufferArray || bufferArray.length === 0) {
            return { count: 0, totalSize: 0, avgSize: 0 };
        }

        const sizes = bufferArray.filter(b => b && b.size).map(b => b.size);
        const totalSize = sizes.reduce((sum, s) => sum + s, 0);

        return {
            count: bufferArray.length,
            validCount: sizes.length,
            totalSize,
            avgSize: sizes.length > 0 ? Math.round(totalSize / sizes.length) : 0
        };
    }

    // ============ API PUBBLICHE ============

    /**
     * Prepara un replay per l'upload
     * @param {Array} bufferArray - Array di blob dal CircularBuffer
     * @param {Object} metadata - Metadati del replay
     * @param {number} sliceCount - Numero di chunk da prendere
     * @returns {Promise<Object>}
     */
    async prepareReplay(bufferArray, metadata, sliceCount) {
        return this.sendMessage('PREPARE_REPLAY', {
            bufferArray,
            metadata,
            sliceCount
        });
    }

    /**
     * Comprimi i chunk (se supportato)
     * @param {Array} chunks - Chunks da comprimere
     * @param {string} compressionType - Tipo compressione (gzip, deflate)
     * @returns {Promise<Object>}
     */
    async compressChunks(chunks, compressionType = 'gzip') {
        return this.sendMessage('COMPRESS_CHUNKS', {
            chunks,
            compressionType
        });
    }

    /**
     * Unisci più blob in uno
     * @param {Array} chunks - Chunks da unire
     * @param {string} mimeType - MIME type del risultato
     * @returns {Promise<Object>}
     */
    async mergeBlobs(chunks, mimeType = 'video/webm') {
        return this.sendMessage('MERGE_BLOBS', {
            chunks,
            mimeType
        });
    }

    /**
     * Calcola statistiche sul buffer
     * @param {Array} bufferArray - Buffer da analizzare
     * @returns {Promise<Object>}
     */
    async calculateStats(bufferArray) {
        return this.sendMessage('CALCULATE_STATS', { bufferArray });
    }

    /**
     * Verifica se il Worker è disponibile e funzionante
     * @returns {boolean}
     */
    isAvailable() {
        return this.isReady && this.worker !== null;
    }

    /**
     * Ottieni numero di richieste pending
     * @returns {number}
     */
    getPendingCount() {
        return this.pendingRequests.size;
    }

    /**
     * Termina il Worker e pulisci risorse
     */
    terminate() {
        // Pulisci tutti i timeout
        for (const [id, pending] of this.pendingRequests) {
            if (pending.timeoutId) {
                clearTimeout(pending.timeoutId);
            }
        }
        this.pendingRequests.clear();

        // Termina il worker
        if (this.worker) {
            this.worker.terminate();
            this.worker = null;
        }

        this.isReady = false;
        console.log('BlobWorkerManager terminato');
    }
}

// Crea istanza singleton
const blobWorkerInstance = new BlobWorkerManager();
