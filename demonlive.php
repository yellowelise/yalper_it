<?php
/**
 * DemonLive - Elaboratore per segmenti LiveStream
 * Ottimizzato per gestire segmenti lunghi (60+ secondi) con molti chunk
 * Usa il concat demuxer di FFmpeg invece di filter_complex per maggiore affidabilità
 */

$resolutions = [
    ['width' => 640, 'height' => 360],
    ['width' => 1024, 'height' => 576],
    ['width' => 1152, 'height' => 648],
    ['width' => 1280, 'height' => 720],
    ['width' => 1366, 'height' => 768],
    ['width' => 1600, 'height' => 900],
    ['width' => 1920, 'height' => 1080],
    ['width' => 2560, 'height' => 1440],
    ['width' => 3840, 'height' => 2160],
    ['width' => 1920, 'height' => 1080]
];

include(__DIR__ . '/config/db.php');
set_time_limit(3600);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('PATH', __DIR__ . '/upload/uploads/');
define('PATH_INTRO', __DIR__ . '/upload/');

function generateRandomString($length = 30) {
    return bin2hex(random_bytes($length));
}

function logMessage($folder, $message) {
    $logFile = PATH . $folder . 'process_live.log';
    $dir = PATH . $folder;
    if (is_dir($dir)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }
    echo date('Y-m-d H:i:s') . " - " . $message . "\n";
}

function executeFFmpeg($command, $folder, $step) {
    logMessage($folder, "Step $step: $command");
    $output = [];
    $returnVar = null;
    exec($command . " 2>&1", $output, $returnVar);
    $outputStr = implode("\n", $output);
    logMessage($folder, "Return: $returnVar");
    if ($returnVar !== 0) {
        logMessage($folder, "Output: $outputStr");
    }
    return $returnVar === 0;
}

function convertToHLS($mp4Path, $path_dest, $crf) {
    if (!file_exists($mp4Path)) {
        return "File non trovato: $mp4Path";
    }

    if (!is_dir($path_dest)) {
        mkdir($path_dest, 0755, true);
        if (file_exists(PATH_INTRO . "index.php")) {
            copy(PATH_INTRO . "index.php", $path_dest . "index.php");
        }
    }

    $fileName = pathinfo($mp4Path, PATHINFO_FILENAME);

    // Comando HLS semplificato e robusto
    $command = sprintf(
        'ffmpeg -y -i %s ' .
        '-c:v libx264 -crf %d -preset fast ' .
        '-c:a aac -b:a 128k ' .
        '-hls_time 4 -hls_list_size 0 ' .
        '-hls_segment_filename %s/%s_%%03d.ts ' .
        '-f hls %s/%s.m3u8',
        escapeshellarg($mp4Path),
        $crf,
        escapeshellarg($path_dest),
        $fileName,
        escapeshellarg($path_dest),
        $fileName
    );

    $output = [];
    $returnVal = 0;
    exec($command . " 2>&1", $output, $returnVal);

    if ($returnVal !== 0) {
        return "Errore HLS: " . implode("\n", $output);
    }

    return "OK";
}

/**
 * Concatena i chunk usando filter_complex (stesso metodo di demondb.php)
 * Questo produce file di dimensioni corrette
 */
function concatenateChunks($folder, $validParts, $hasAudio, $crf) {
    $outputFile = PATH . $folder . 'output_intro.mp4';

    // Costruisci il comando come in demondb.php
    $input_string = '';
    $filter_complex = '';

    if ($hasAudio) {
        // Con audio: concatena video e audio
        for ($i = 0; $i < count($validParts); $i++) {
            $partNum = $validParts[$i];
            $input_string .= ' -i ' . escapeshellarg(PATH . $folder . 'part' . $partNum . '.webm');
            $filter_complex .= '[' . $i . ':v][' . $i . ':a]';
        }
        $filter_complex .= 'concat=n=' . count($validParts) . ':v=1:a=1';
    } else {
        // Senza audio: concatena solo video
        for ($i = 0; $i < count($validParts); $i++) {
            $partNum = $validParts[$i];
            $input_string .= ' -i ' . escapeshellarg(PATH . $folder . 'part' . $partNum . '.webm');
            $filter_complex .= '[' . $i . ':v]';
        }
        $filter_complex .= 'concat=n=' . count($validParts) . ':v=1';
    }

    $command = 'ffmpeg -y ' . $input_string . ' -filter_complex "' . $filter_complex . '" -vsync vfr -c:v libx264 -preset medium -crf ' . $crf . ' ' . escapeshellarg($outputFile);

    $success = executeFFmpeg($command, $folder, "concat");

    if (!$success || !file_exists($outputFile)) {
        return false;
    }

    // Step 2: Generate PTS (come demondb.php)
    $outputGenpts = PATH . $folder . 'output_intro_genpts.mp4';
    $command2 = 'ffmpeg -y -i ' . escapeshellarg($outputFile) .
        ' -c copy' .
        ' -fflags +genpts' .
        ' -movflags +faststart' .
        ' -avoid_negative_ts make_zero' .
        ' -async 1' .
        ' ' . escapeshellarg($outputGenpts);

    executeFFmpeg($command2, $folder, "genpts");

    // Pulisci file intermedio
    if (file_exists($outputFile)) @unlink($outputFile);

    if (file_exists($outputGenpts)) {
        return $outputGenpts;
    }

    return false;
}

/**
 * Aggiunge overlay di testo e audio di sottofondo (come demondb.php)
 */
function processVideo($inputFile, $outputFile, $folder, $jsonData, $resolutions, $crf, $hasAudio) {
    global $resolutions;

    $height = isset($resolutions[$jsonData['version']])
        ? $resolutions[$jsonData['version']]
        : ['height' => 720, 'width' => 1280];

    $textTop = isset($jsonData['text_home']) && isset($jsonData['text_away'])
        ? $jsonData['text_home'] . ' - ' . $jsonData['text_away']
        : 'Live Stream';

    if (isset($jsonData['minutes'])) {
        $textTop .= ' ' . $jsonData['minutes'];
    }

    $fontSize = max(16, round($height['height'] / 24));
    $fontSizeLogo = max(24, round($height['height'] / 12));

    $overlayTextPath = PATH . $folder . 'overlay_text.txt';
    if (file_put_contents($overlayTextPath, $textTop) === false) {
        logMessage($folder, 'Impossibile creare overlay_text.txt');
        return false;
    }

    // Il testo dell'utente resta in un file e non viene interpretato dalla shell.
    $filterTextPath = str_replace(['\\', ':', "'"], ['\\\\', '\\:', "\\'"], $overlayTextPath);
    $videoFilter = "drawtext=textfile='" . $filterTextPath . "'" .
        ":fontcolor=#000000" .
        ":fontsize=" . $fontSize .
        ":box=1" .
        ":boxcolor=white@0.5" .
        ":boxborderw=5" .
        ":x=(w-text_w)/2" .
        ":y=th+2," .
        "drawtext=text='YalpeR.it'" .
        ":fontcolor=#000000" .
        ":fontsize=" . $fontSizeLogo .
        ":box=1" .
        ":boxcolor=white@0.5" .
        ":boxborderw=5" .
        ":x=(w-text_w)/2" .
        ":y=h-th-10";

    if ($hasAudio) {
        // Video con audio originale - copia audio, overlay video
        $command = 'ffmpeg -y -i ' . escapeshellarg($inputFile) .
            ' -vf ' . escapeshellarg($videoFilter) .
            ' -crf ' . (int) $crf .
            ' -acodec copy ' . escapeshellarg($outputFile);
    } else {
        // Video senza audio - aggiungi musica di sottofondo (come demondb.php)
        $musicFile = PATH_INTRO . 'base' . rand(0, 12) . '.mp3';

        // Prima aggiungi la musica
        $outputWithAudio = PATH . $folder . 'output_with_audio.mp4';
        $commandAudio = 'ffmpeg -y -i ' . escapeshellarg($inputFile) . ' -stream_loop -1 -i ' . escapeshellarg($musicFile) . ' -c copy -map 0:v:0 -map 1:a:0 -shortest ' . escapeshellarg($outputWithAudio);
        executeFFmpeg($commandAudio, $folder, "add_audio");

        $inputForOverlay = file_exists($outputWithAudio) ? $outputWithAudio : $inputFile;

        // Poi aggiungi overlay
        $command = 'ffmpeg -y -i ' . escapeshellarg($inputForOverlay) .
            ' -vf ' . escapeshellarg($videoFilter) .
            ' -crf ' . (int) $crf .
            ' -acodec copy ' . escapeshellarg($outputFile);

        $result = executeFFmpeg($command, $folder, "overlay");

        // Pulisci file temporaneo
        if (file_exists($outputWithAudio)) @unlink($outputWithAudio);

        return $result;
    }

    return executeFFmpeg($command, $folder, "process");
}

/**
 * Genera thumbnail
 */
function generateThumbnail($inputFile, $outputPath, $folder) {
    // Thumbnail singola a 2 secondi
    $command = sprintf(
        'ffmpeg -y -ss 2 -i %s -frames:v 1 -q:v 2 %s',
        escapeshellarg($inputFile),
        escapeshellarg($outputPath . '.jpg')
    );
    executeFFmpeg($command, $folder, "thumb");

    // Wall thumbnail
    $commandWall = sprintf(
        'ffmpeg -y -i %s -vf "fps=1/5,scale=200:-1,tile=3x3" -frames:v 1 -q:v 3 %s',
        escapeshellarg($inputFile),
        escapeshellarg($outputPath . '_wall.jpg')
    );
    executeFFmpeg($command, $folder, "thumb_wall");
}


/**
 * Genera la master playlist _all.m3u8 che unisce tutti i segmenti.
 * Logica adattata da sharing_live.php
 */
function generateMasterPlaylist($userToken, $segmentFolder, $folder) {
    logMessage($folder, "Inizio generazione master playlist per $segmentFolder");

    // Estrai il prefisso della sessione. Es: 04_12_2025__12_30_00Live-Seg
    if (!preg_match('/^(\d{2}_\d{2}_\d{4}__\d{2}_\d{2}_\d{2}Live-Seg)/', $segmentFolder, $matches)) {
        logMessage($folder, "ERRORE: Impossibile estrarre il prefisso della sessione da $segmentFolder");
        return;
    }
    $prefix = $matches[1];

    $user_upload_dir = PATH . $userToken;
    $glob_pattern = $user_upload_dir . DIRECTORY_SEPARATOR . $prefix . '*-output.m3u8';
    
    logMessage($folder, "Glob pattern: $glob_pattern");
    $segment_m3u8_files = glob($glob_pattern);

    if (empty($segment_m3u8_files)) {
        logMessage($folder, "Nessun file m3u8 di segmento trovato per il pattern.");
        return;
    }
    
    logMessage($folder, "Trovati " . count($segment_m3u8_files) . " file m3u8.");

    // Ordina i segmenti per numero (Live-Seg1, Live-Seg2, ...)
    usort($segment_m3u8_files, function($a, $b) {
        preg_match('/Live-Seg(\d+)/', $a, $matchesA);
        preg_match('/Live-Seg(\d+)/', $b, $matchesB);
        $numA = isset($matchesA[1]) ? (int)$matchesA[1] : 0;
        $numB = isset($matchesB[1]) ? (int)$matchesB[1] : 0;
        return $numA - $numB;
    });

    $master_playlist_content = "#EXTM3U\n#EXT-X-VERSION:3\n";
    $ts_segments_content = "";
    $max_target_duration = 0;
    $is_first_segment_file = true;

    foreach ($segment_m3u8_files as $m3u8_file) {
        if (file_exists($m3u8_file)) {
            if (!$is_first_segment_file) {
                $ts_segments_content .= "#EXT-X-DISCONTINUITY\n";
            }

            $m3u8_content = file_get_contents($m3u8_file);
            
            if (preg_match('/#EXT-X-TARGETDURATION:(\d+)/', $m3u8_content, $duration_matches)) {
                if ((int)$duration_matches[1] > $max_target_duration) {
                    $max_target_duration = (int)$duration_matches[1];
                }
            }
            
            $lines = explode("\n", $m3u8_content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#EXTINF:') === 0) {
                    $ts_segments_content .= $line . "\n";
                } elseif (substr($line, -3) === '.ts') {
                    // I file .ts sono nella stessa cartella della master playlist, quindi basta il nome del file
                    $ts_segments_content .= basename($line) . "\n";
                }
            }
            $is_first_segment_file = false;
        }
    }
    
    if (!empty($ts_segments_content)) {
        if ($max_target_duration == 0) {
            $max_target_duration = 10; // Default
        }
        $master_playlist_content .= "#EXT-X-TARGETDURATION:{$max_target_duration}\n";
        $master_playlist_content .= "#EXT-X-MEDIA-SEQUENCE:0\n";
        $master_playlist_content .= $ts_segments_content;
        $master_playlist_content .= "#EXT-X-ENDLIST\n";

        $master_playlist_filename = $user_upload_dir . '/' . $prefix . '_all.m3u8';
        file_put_contents($master_playlist_filename, $master_playlist_content);
        logMessage($folder, "Master playlist generata: $master_playlist_filename");
    } else {
         logMessage($folder, "Nessun segmento .ts valido trovato per creare la master playlist.");
    }
}

// ============ MAIN ============

echo "=== DemonLive Started ===\n";

$demon_id = generateRandomString();

// Cerca job Live (contengono "Live-Seg" nel folder)
$sql = "SELECT * FROM jobs WHERE maked = '0' AND folder LIKE '%Live-Seg%' AND (dont_elaborate_before IS NULL OR dont_elaborate_before <= NOW()) ORDER BY priority, data_creation LIMIT 1";
$jobs = mysqli_query($connection, $sql);

if (mysqli_num_rows($jobs) == 0) {
    // Cerca job parzialmente falliti
    $sql = "SELECT * FROM jobs WHERE maked = '1' AND folder LIKE '%Live-Seg%' AND passing < 50 AND (dont_elaborate_before IS NULL OR dont_elaborate_before <= NOW()) ORDER BY passing, priority, data_creation LIMIT 1";
    $jobs = mysqli_query($connection, $sql);
}

if (mysqli_num_rows($jobs) == 0) {
    echo "Nessun job live da elaborare\n";
    exit;
}

while ($job = mysqli_fetch_array($jobs)) {
    $folder = $job['folder'] . DIRECTORY_SEPARATOR;

    echo "\n--- Processing: $folder ---\n";

    // Marca come in elaborazione
    $stmt = mysqli_prepare($connection, "UPDATE jobs SET passing = passing + 1, maked = 3 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $job['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (empty(trim($folder, "/"))) {
        echo "Folder vuoto, skip\n";
        continue;
    }

    logMessage($folder, "=== Inizio elaborazione ===");

    // Estrai token utente dal path
    $f_arr = explode(DIRECTORY_SEPARATOR, $folder);
    $userToken = $f_arr[0];
    $segmentFolder = isset($f_arr[1]) ? $f_arr[1] : basename($folder);

    // Leggi detail.json
    $jsonPath = PATH . $folder . 'detail.json';
    if (!file_exists($jsonPath)) {
        logMessage($folder, "detail.json non trovato: $jsonPath");
        $stmt = mysqli_prepare($connection, "UPDATE jobs SET passing = passing + 1, maked = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        continue;
    }

    $json = file_get_contents($jsonPath);
    $jsonData = json_decode($json, true);

    if (!$jsonData) {
        logMessage($folder, "Errore parsing JSON");
        continue;
    }

    logMessage($folder, "JSON: " . print_r($jsonData, true));

    $MAX_CHUNK = intval($jsonData['chunk_num']);
    $vbps = isset($jsonData['vbps']) ? intval($jsonData['vbps']) : 0;

    // Calcola CRF
    $M_VBPS = $vbps / 1000000;
    if ($vbps == -1 || $vbps == 0) {
        $crf = 23;
    } else {
        $crf = 26;
        if ($M_VBPS > 2.5) $crf = 24;
        if ($M_VBPS > 4.5) $crf = 22;
        if ($M_VBPS > 6.0) $crf = 20;
    }

    logMessage($folder, "MAX_CHUNK: $MAX_CHUNK, VBPS: $vbps, CRF: $crf");

    // Trova tutti i chunk validi
    $validParts = [];
    for ($i = 0; $i < $MAX_CHUNK; $i++) {
        $partFile = PATH . $folder . 'part' . $i . '.webm';
        if (file_exists($partFile) && filesize($partFile) > 0) {
            $validParts[] = $i;
        } else {
            logMessage($folder, "Chunk mancante o vuoto: part$i.webm");
        }
    }

    logMessage($folder, "Chunk validi: " . count($validParts) . "/$MAX_CHUNK");

    if (empty($validParts)) {
        logMessage($folder, "Nessun chunk valido!");
        $stmt = mysqli_prepare($connection, "UPDATE jobs SET maked = 1, passing = passing + 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        continue;
    }

    // Procedi se abbiamo almeno il 70% dei chunk o dopo molti tentativi
    $minChunks = max(1, floor($MAX_CHUNK * 0.7));
    if (count($validParts) < $minChunks && $job['passing'] < 30) {
        logMessage($folder, "Chunk insufficienti (" . count($validParts) . "/$minChunks), attendo...");
        $stmt = mysqli_prepare($connection, "UPDATE jobs SET maked = 1, passing = passing + 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        continue;
    }

    // Determina se c'è audio
    $hasAudio = (strpos($folder, 'audio') !== false) || (strpos($folder, 'esultanza') !== false);
    logMessage($folder, "Has audio: " . ($hasAudio ? "YES" : "NO"));

    // Step 1: Concatena tutti i chunk
    logMessage($folder, "Step 1: Concatenazione chunk...");
    $concatenatedFile = concatenateChunks($folder, $validParts, $hasAudio, $crf);

    if (!$concatenatedFile || !file_exists($concatenatedFile)) {
        logMessage($folder, "ERRORE: Concatenazione fallita!");
        $stmt = mysqli_prepare($connection, "UPDATE jobs SET maked = 1, passing = passing + 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        continue;
    }

    $concatSize = filesize($concatenatedFile);
    logMessage($folder, "Concatenato: $concatenatedFile ($concatSize bytes)");

    if ($concatSize == 0) {
        logMessage($folder, "ERRORE: File concatenato vuoto!");
        $stmt = mysqli_prepare($connection, "UPDATE jobs SET maked = 1, passing = passing + 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        continue;
    }

    // Step 2: Aggiungi overlay e audio
    logMessage($folder, "Step 2: Processing video...");
    $outputBasename = rtrim($segmentFolder, DIRECTORY_SEPARATOR) . '-output';
    $finalOutput = PATH . $userToken . '/' . $outputBasename . '.mp4';

    // Assicurati che la cartella utente esista
    $userDir = PATH . $userToken . '/';
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }

    $processSuccess = processVideo($concatenatedFile, $finalOutput, $folder, $jsonData, $resolutions, $crf, $hasAudio);

    if (!$processSuccess || !file_exists($finalOutput) || filesize($finalOutput) == 0) {
        logMessage($folder, "ERRORE: Processing fallito!");

        // Prova a copiare direttamente il file concatenato come fallback
        logMessage($folder, "Tentativo fallback: copia diretta...");
        if (copy($concatenatedFile, $finalOutput)) {
            logMessage($folder, "Fallback OK");
        } else {
            $stmt = mysqli_prepare($connection, "UPDATE jobs SET maked = 1, passing = passing + 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $job['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            continue;
        }
    }

    $finalSize = filesize($finalOutput);
    logMessage($folder, "Output finale: $finalOutput ($finalSize bytes)");

    if ($finalSize == 0) {
        logMessage($folder, "ERRORE: Output finale vuoto!");
        $stmt = mysqli_prepare($connection, "UPDATE jobs SET maked = 1, passing = passing + 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $job['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        continue;
    }

    // Step 3: Genera thumbnail
    logMessage($folder, "Step 3: Generazione thumbnail...");
    generateThumbnail($finalOutput, $userDir . $outputBasename, $folder);

    // Step 4: Converti in HLS
    logMessage($folder, "Step 4: Conversione HLS...");
    $hlsResult = convertToHLS($finalOutput, $userDir, $crf);
    logMessage($folder, "HLS Result: $hlsResult");

    // Verifica che l'HLS sia stato creato
    $hlsFile = $userDir . $outputBasename . '.m3u8';
    if (!file_exists($hlsFile)) {
        logMessage($folder, "WARNING: File HLS non creato, ma MP4 esiste");
    } else {
        logMessage($folder, "HLS creato: $hlsFile");
        // Rigenera la master playlist
        generateMasterPlaylist($userToken, $segmentFolder, $folder);
    }

    // Step 5: Elimina MP4 (teniamo solo HLS e thumbnail)
    if (file_exists($hlsFile) && file_exists($finalOutput)) {
        logMessage($folder, "Eliminazione MP4 (HLS creato con successo)...");
        @unlink($finalOutput);
    }

    // Step 6: Aggiorna database
    logMessage($folder, "Step 6: Aggiornamento database...");

    // Aggiorna crediti
    $stmt = mysqli_prepare($connection, "UPDATE user_credits SET used_credits = used_credits + 1, left_credits = left_credits - 1 WHERE user_token = ?");
    mysqli_stmt_bind_param($stmt, "s", $userToken);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Marca job come completato
    $stmt = mysqli_prepare($connection, "UPDATE jobs SET maked = 2 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $job['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Step 7: Pulizia
    logMessage($folder, "Step 7: Pulizia...");

    // Copia detail.json nella cartella output
    copy($jsonPath, $userDir . $outputBasename . '.json');

    // Elimina i chunk
    foreach ($validParts as $partNum) {
        $partFile = PATH . $folder . 'part' . $partNum . '.webm';
        if (file_exists($partFile)) {
            @unlink($partFile);
        }
    }

    // Elimina file temporanei (aggiornato con i nuovi nomi)
    $tempFiles = [
        'output_intro.mp4',
        'output_intro_genpts.mp4',
        'output_trim.mp4',
        'output_with_audio.mp4',
        'concatenated.mp4',
        'concat_list.txt',
        'overlay_text.txt',
        'detail.json',
        'process_live.log'
    ];

    foreach ($tempFiles as $file) {
        $fullPath = PATH . $folder . $file;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    // Rimuovi la cartella se vuota
    if (is_dir(PATH . $folder)) {
        @rmdir(PATH . $folder);
    }

    logMessage($folder, "=== Elaborazione completata! ===");
    echo "Job {$job['id']} completato!\n";
}

echo "\n=== DemonLive Finished ===\n";
?>
