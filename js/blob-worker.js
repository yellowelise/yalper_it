/**
 * Web Worker per gestione blob video
 * Gestisce operazioni pesanti sui blob in background per non bloccare l'UI
 */

self.onmessage = async function(e) {
    const { action, payload, requestId } = e.data;

    try {
        switch (action) {
            case 'PREPARE_REPLAY':
                const result = await prepareReplayForUpload(payload);
                self.postMessage({
                    action: 'REPLAY_READY',
                    requestId,
                    payload: result
                });
                break;

            case 'COMPRESS_CHUNKS':
                const compressed = await compressChunks(payload);
                self.postMessage({
                    action: 'CHUNKS_COMPRESSED',
                    requestId,
                    payload: compressed
                });
                break;

            case 'MERGE_BLOBS':
                const merged = await mergeBlobs(payload);
                self.postMessage({
                    action: 'BLOBS_MERGED',
                    requestId,
                    payload: merged
                });
                break;

            case 'CALCULATE_STATS':
                const stats = calculateBufferStats(payload);
                self.postMessage({
                    action: 'STATS_READY',
                    requestId,
                    payload: stats
                });
                break;

            default:
                throw new Error(`Azione sconosciuta: ${action}`);
        }
    } catch (error) {
        self.postMessage({
            action: 'ERROR',
            requestId,
            error: error.message
        });
    }
};

/**
 * Prepara i dati del replay per l'upload
 * @param {Object} params - Parametri con bufferArray, metadata, sliceCount
 * @returns {Object} Chunks processati e metadata
 */
async function prepareReplayForUpload({ bufferArray, metadata, sliceCount }) {
    // Prendi solo gli ultimi N elementi se richiesto
    const chunks = sliceCount < bufferArray.length
        ? bufferArray.slice(-sliceCount)
        : [...bufferArray];

    // Processa ogni chunk
    const processedChunks = await Promise.all(
        chunks.map(async (blob, index) => {
            if (!blob || blob.size === 0) {
                return null;
            }

            try {
                const arrayBuffer = await blob.arrayBuffer();
                return {
                    index,
                    data: arrayBuffer,
                    size: blob.size,
                    type: blob.type || 'video/webm'
                };
            } catch (err) {
                console.error(`Errore processamento chunk ${index}:`, err);
                return null;
            }
        })
    );

    // Filtra chunk nulli
    const validChunks = processedChunks.filter(c => c !== null);

    // Calcola dimensione totale
    const totalSize = validChunks.reduce((sum, c) => sum + c.size, 0);
    const avgChunkSize = validChunks.length > 0 ? Math.round(totalSize / validChunks.length) : 0;

    return {
        chunks: validChunks,
        metadata: {
            ...metadata,
            totalSize,
            avgChunkSize,
            chunkCount: validChunks.length,
            originalChunkCount: chunks.length,
            preparedAt: Date.now()
        }
    };
}

/**
 * Comprime i chunk usando CompressionStream (se disponibile)
 * @param {Object} params - chunks e tipo compressione
 * @returns {Object} Chunks compressi o originali
 */
async function compressChunks({ chunks, compressionType = 'gzip' }) {
    // Verifica supporto CompressionStream
    if (typeof CompressionStream === 'undefined') {
        return {
            chunks,
            compressed: false,
            reason: 'CompressionStream non supportato'
        };
    }

    const compressedChunks = await Promise.all(
        chunks.map(async (chunk) => {
            try {
                const blob = new Blob([chunk.data], { type: chunk.type });
                const stream = blob.stream().pipeThrough(
                    new CompressionStream(compressionType)
                );
                const compressedBlob = await new Response(stream).blob();
                const compressedBuffer = await compressedBlob.arrayBuffer();

                // Usa compressione solo se effettivamente riduce la dimensione
                if (compressedBuffer.byteLength < chunk.size) {
                    return {
                        ...chunk,
                        data: compressedBuffer,
                        originalSize: chunk.size,
                        size: compressedBuffer.byteLength,
                        compressed: true,
                        compressionRatio: (compressedBuffer.byteLength / chunk.size).toFixed(2)
                    };
                } else {
                    return {
                        ...chunk,
                        compressed: false
                    };
                }
            } catch (err) {
                console.error(`Errore compressione chunk ${chunk.index}:`, err);
                return {
                    ...chunk,
                    compressed: false,
                    error: err.message
                };
            }
        })
    );

    const compressedCount = compressedChunks.filter(c => c.compressed).length;
    const totalOriginal = chunks.reduce((sum, c) => sum + c.size, 0);
    const totalCompressed = compressedChunks.reduce((sum, c) => sum + c.size, 0);

    return {
        chunks: compressedChunks,
        compressed: compressedCount > 0,
        stats: {
            compressedCount,
            totalCount: chunks.length,
            originalSize: totalOriginal,
            compressedSize: totalCompressed,
            savedBytes: totalOriginal - totalCompressed,
            overallRatio: (totalCompressed / totalOriginal).toFixed(2)
        }
    };
}

/**
 * Unisce più blob in uno solo (utile per preview)
 * @param {Object} params - chunks e mimeType
 * @returns {Object} Blob unificato
 */
async function mergeBlobs({ chunks, mimeType = 'video/webm' }) {
    if (!chunks || chunks.length === 0) {
        throw new Error('Nessun chunk da unire');
    }

    // Ordina per index
    const sortedChunks = [...chunks].sort((a, b) => a.index - b.index);

    // Crea blob da ogni chunk
    const blobs = sortedChunks.map(c => new Blob([c.data], { type: c.type || mimeType }));

    // Unisci tutti i blob
    const merged = new Blob(blobs, { type: mimeType });
    const arrayBuffer = await merged.arrayBuffer();

    return {
        data: arrayBuffer,
        size: merged.size,
        type: mimeType,
        chunksCount: chunks.length
    };
}

/**
 * Calcola statistiche sul buffer
 * @param {Object} params - bufferArray
 * @returns {Object} Statistiche
 */
function calculateBufferStats({ bufferArray }) {
    if (!bufferArray || bufferArray.length === 0) {
        return {
            count: 0,
            totalSize: 0,
            avgSize: 0,
            minSize: 0,
            maxSize: 0,
            emptyCount: 0
        };
    }

    const sizes = bufferArray
        .filter(b => b && b.size)
        .map(b => b.size);

    const emptyCount = bufferArray.filter(b => !b || b.size === 0).length;
    const totalSize = sizes.reduce((sum, s) => sum + s, 0);

    return {
        count: bufferArray.length,
        validCount: sizes.length,
        emptyCount,
        totalSize,
        avgSize: sizes.length > 0 ? Math.round(totalSize / sizes.length) : 0,
        minSize: sizes.length > 0 ? Math.min(...sizes) : 0,
        maxSize: sizes.length > 0 ? Math.max(...sizes) : 0,
        estimatedDuration: `${(sizes.length * 1.312).toFixed(1)}s` // basato su slice_time
    };
}
