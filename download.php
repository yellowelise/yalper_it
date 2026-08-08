<?php
session_start();

function failDownload($status, $message)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

function sendVideoFile($filePath)
{
    if (!is_file($filePath) || filesize($filePath) <= 0) {
        failDownload(404, 'Video non trovato.');
    }

    header('Content-Type: video/mp4');
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    readfile($filePath);
    exit;
}

$requestedPath = isset($_POST['fn']) ? (string) $_POST['fn'] : '';
$requestedPath = str_replace('\\', '/', trim($requestedPath));

if ($requestedPath === '' || strpos($requestedPath, "\0") !== false) {
    failDownload(400, 'Percorso video non valido.');
}

$parts = explode('/', $requestedPath);
if (count($parts) !== 4 || $parts[0] !== 'upload' || $parts[1] !== 'uploads') {
    failDownload(400, 'Percorso video non valido.');
}

$userDirectoryName = $parts[2];
$playlistName = $parts[3];
if (
    $userDirectoryName === '' ||
    basename($userDirectoryName) !== $userDirectoryName ||
    basename($playlistName) !== $playlistName ||
    strtolower(pathinfo($playlistName, PATHINFO_EXTENSION)) !== 'm3u8'
) {
    failDownload(400, 'Percorso video non valido.');
}

$uploadsDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'uploads');
if ($uploadsDirectory === false) {
    failDownload(500, 'Archivio video non disponibile.');
}

$userDirectory = realpath($uploadsDirectory . DIRECTORY_SEPARATOR . $userDirectoryName);
if (
    $userDirectory === false ||
    strpos($userDirectory, $uploadsDirectory . DIRECTORY_SEPARATOR) !== 0
) {
    failDownload(404, 'Archivio video non trovato.');
}

$playlistPath = realpath($userDirectory . DIRECTORY_SEPARATOR . $playlistName);
if ($playlistPath === false || dirname($playlistPath) !== $userDirectory || !is_file($playlistPath)) {
    failDownload(404, 'Playlist video non trovata.');
}

$stem = pathinfo($playlistName, PATHINFO_FILENAME);
$outputPath = $userDirectory . DIRECTORY_SEPARATOR . $stem . '.mp4';

if (is_file($outputPath) && filesize($outputPath) > 0) {
    sendVideoFile($outputPath);
}

$segments = glob($userDirectory . DIRECTORY_SEPARATOR . $stem . '_*.ts') ?: [];
natsort($segments);
$segments = array_values($segments);

if (count($segments) === 0) {
    failDownload(404, 'Segmenti video non trovati.');
}

// Concatena i segmenti direttamente da PHP: nessun nome file controllato dal
// client viene più inserito in un comando "cat" della shell.
$temporaryTs = tempnam(sys_get_temp_dir(), 'yalper_ts_');
if ($temporaryTs === false) {
    failDownload(500, 'Impossibile preparare il download.');
}

$destination = fopen($temporaryTs, 'wb');
if ($destination === false) {
    @unlink($temporaryTs);
    failDownload(500, 'Impossibile preparare il download.');
}

$copyFailed = false;
foreach ($segments as $segmentPath) {
    $resolvedSegment = realpath($segmentPath);
    if ($resolvedSegment === false || dirname($resolvedSegment) !== $userDirectory || !is_file($resolvedSegment)) {
        $copyFailed = true;
        break;
    }

    $source = fopen($resolvedSegment, 'rb');
    if ($source === false || stream_copy_to_stream($source, $destination) === false) {
        if ($source !== false) {
            fclose($source);
        }
        $copyFailed = true;
        break;
    }
    fclose($source);
}
fclose($destination);

if ($copyFailed || filesize($temporaryTs) <= 0) {
    @unlink($temporaryTs);
    failDownload(500, 'Impossibile concatenare i segmenti video.');
}

$fontPath = __DIR__ . DIRECTORY_SEPARATOR . 'ttf' . DIRECTORY_SEPARATOR . 'iceland.ttf';
$drawText = "drawtext=text='YalpeR.it':fontfile='" . str_replace("'", "\\'", $fontPath) .
    "':fontcolor=#260E5190:fontsize=140:box=1:boxcolor=white@0.2:boxborderw=5:x=(w-text_w)/2:y=h-th-50";

$logoPath = null;
if (isset($_SESSION['id']) && ctype_digit((string) $_SESSION['id'])) {
    $candidateLogo = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $_SESSION['id'] . DIRECTORY_SEPARATOR . 'logo.png');
    $imagesDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'img');
    if ($candidateLogo !== false && $imagesDirectory !== false && strpos($candidateLogo, $imagesDirectory . DIRECTORY_SEPARATOR) === 0) {
        $logoPath = $candidateLogo;
    }
}

if ($logoPath === null) {
    $ffmpegCommand = 'ffmpeg -y -i ' . escapeshellarg($temporaryTs) .
        ' -vf ' . escapeshellarg($drawText) .
        ' -acodec copy ' . escapeshellarg($outputPath) . ' 2>&1';
} else {
    $filter = '[1:v]scale=iw/2:-1[logo];[0:v][logo]overlay=W-w-10:10,' . $drawText;
    $ffmpegCommand = 'ffmpeg -y -i ' . escapeshellarg($temporaryTs) .
        ' -i ' . escapeshellarg($logoPath) .
        ' -filter_complex ' . escapeshellarg($filter) .
        ' -acodec copy ' . escapeshellarg($outputPath) . ' 2>&1';
}

$ffmpegOutput = shell_exec($ffmpegCommand);
@unlink($temporaryTs);

if (!is_file($outputPath) || filesize($outputPath) <= 0) {
    error_log('Download FFmpeg failed for ' . $playlistName . ': ' . (string) $ffmpegOutput);
    failDownload(500, 'Errore durante la conversione del video.');
}

sendVideoFile($outputPath);

