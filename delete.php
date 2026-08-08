<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

include('config/db.php');
require_once('config/auth.php');

$OTT = isset($_POST['OTT']) ? (string) $_POST['OTT'] : '';
$token = isset($_POST['token']) ? (string) $_POST['token'] : '';
$filesInput = isset($_POST['fs']) ? (string) $_POST['fs'] : '';

$user = auth_validate_credentials($connection, $OTT, $token);
if (!$user) {
    http_response_code(401);
    echo json_encode(['code' => 401, 'message' => 'Sessione non valida o scaduta.']);
    exit;
}

if ($filesInput === '') {
    http_response_code(400);
    echo json_encode(['code' => 400, 'message' => 'Nessun video selezionato.']);
    exit;
}

$userToken = $user['token'];
$userId = (int) $user['id'];
$userDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $userToken);

if ($userDirectory === false || !is_dir($userDirectory)) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'message' => 'Archivio video non trovato.']);
    exit;
}

$requestedFiles = array_values(array_filter(explode('|', $filesInput), function ($value) {
    return $value !== '';
}));

$deleted = 0;
$rejected = 0;

foreach ($requestedFiles as $encodedPath) {
    $decodedPath = base64_decode($encodedPath, true);
    if ($decodedPath === false) {
        $rejected++;
        continue;
    }

    $decodedPath = str_replace('\\', '/', $decodedPath);
    $expectedPrefix = 'upload/uploads/' . $userToken . '/';
    $fileName = basename($decodedPath);

    // I replay selezionabili dalla UI sono playlist HLS direttamente nella
    // cartella dell'utente. Non sono ammessi sottopercorsi o altre estensioni.
    if ($decodedPath !== $expectedPrefix . $fileName || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'm3u8') {
        $rejected++;
        continue;
    }

    $playlistPath = $userDirectory . DIRECTORY_SEPARATOR . $fileName;
    if (file_exists($playlistPath) && (!is_file($playlistPath) || !@unlink($playlistPath))) {
        $rejected++;
        continue;
    }

    $stem = substr($fileName, 0, -strlen('.m3u8'));
    $sidecars = [
        $userDirectory . DIRECTORY_SEPARATOR . $stem . '_wall.jpg',
        $userDirectory . DIRECTORY_SEPARATOR . $stem . '.mp4',
        $userDirectory . DIRECTORY_SEPARATOR . $stem . '.json'
    ];

    foreach ($sidecars as $sidecar) {
        if (is_file($sidecar)) {
            @unlink($sidecar);
        }
    }

    foreach (glob($userDirectory . DIRECTORY_SEPARATOR . $stem . '*.ts') ?: [] as $segment) {
        if (is_file($segment)) {
            @unlink($segment);
        }
    }

    // Rimuove il percorso solo dalle condivisioni appartenenti allo stesso
    // utente, trattandolo come elemento delimitato e non come sottostringa.
    $stmt = mysqli_prepare(
        $connection,
        "UPDATE url_shorter
         SET long_link = TRIM(BOTH '|' FROM REPLACE(
             CONCAT('|', TRIM(BOTH '|' FROM long_link), '|'),
             CONCAT('|', ?, '|'),
             '|'
         ))
         WHERE iduser = ?"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $encodedPath, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $deleted++;
}

if ($deleted === 0) {
    http_response_code(400);
    echo json_encode([
        'code' => 400,
        'message' => 'Nessun video eliminato: richiesta non valida.',
        'rejected' => $rejected
    ]);
    exit;
}

echo json_encode([
    'code' => 200,
    'message' => $deleted === 1 ? 'Video selezionato eliminato.' : 'Video selezionati eliminati.',
    'deleted' => $deleted,
    'rejected' => $rejected
]);

