<?php
session_start();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

include('config/db.php');
require_once('config/auth.php');
require_once('config/media.php');

function shorterFail($status, $message)
{
    http_response_code($status);
    echo $message;
    exit;
}

function generateShortCode($length = 30)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $result = '';
    $lastIndex = strlen($characters) - 1;

    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[random_int(0, $lastIndex)];
    }

    return $result;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    shorterFail(405, 'Metodo non consentito.');
}

$OTT = isset($_POST['OTT']) ? (string) $_POST['OTT'] : '';
$token = isset($_POST['token']) ? (string) $_POST['token'] : '';
$user = auth_validate_credentials($connection, $OTT, $token);

if (!$user) {
    shorterFail(401, 'Sessione non valida o scaduta.');
}

$encodedFiles = isset($_POST['files']) ? array_values(array_filter(explode('|', (string) $_POST['files']))) : [];
if (count($encodedFiles) === 0 || count($encodedFiles) > 100) {
    shorterFail(422, 'Selezione video non valida.');
}

$validatedFiles = [];
foreach ($encodedFiles as $encodedFile) {
    $decodedPath = base64_decode($encodedFile, true);
    $validatedPath = $decodedPath === false
        ? null
        : media_validate_video_relative_path($decodedPath, $user['token']);

    if ($validatedPath === null || !is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $validatedPath))) {
        shorterFail(422, 'Uno dei video selezionati non è valido.');
    }

    $validatedFiles[] = base64_encode($validatedPath);
}

$title = isset($_POST['title']) && trim((string) $_POST['title']) !== ''
    ? media_clean_metadata($_POST['title'], 255)
    : 'Condivisione ' . date('d/m/Y H:i');

$visibility = isset($_POST['visibility']) ? (int) $_POST['visibility'] : 4;
if ($visibility < 0 || $visibility > 4) {
    shorterFail(422, 'Visibilità non valida.');
}

$shortCode = generateShortCode();
$longLink = implode('|', $validatedFiles) . '|';
$userToken = $user['token'];
$userId = (int) $user['id'];

$stmt = mysqli_prepare(
    $connection,
    'INSERT INTO url_shorter (shorter, click, long_link, user_token, title, iduser, creation_date, visibility)
     VALUES (?, 0, ?, ?, ?, ?, NOW(), ?)'
);

if (!$stmt) {
    shorterFail(500, 'Impossibile creare la condivisione.');
}

mysqli_stmt_bind_param($stmt, 'ssssii', $shortCode, $longLink, $userToken, $title, $userId, $visibility);
if (!mysqli_stmt_execute($stmt)) {
    error_log('Short link insert failed: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    shorterFail(500, 'Impossibile creare la condivisione.');
}

mysqli_stmt_close($stmt);
echo $shortCode;

