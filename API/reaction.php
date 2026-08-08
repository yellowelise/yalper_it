<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(array('success' => false, 'error' => 'Metodo non consentito.'));
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/media.php';

$link = isset($_POST['link']) ? trim((string) $_POST['link']) : '';
$iduser = isset($_POST['iduser']) ? trim((string) $_POST['iduser']) : '';
$action = isset($_POST['action']) ? filter_var($_POST['action'], FILTER_VALIDATE_INT) : false;

$normalizedLink = media_validate_video_relative_path($link);
if (
    $normalizedLink === null ||
    !preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $iduser) ||
    $action === false ||
    !in_array($action, array(0, 1, 2), true)
) {
    http_response_code(422);
    echo json_encode(array('success' => false, 'error' => 'Dati non validi.'));
    exit;
}

$stmt = mysqli_prepare($connection, 'SELECT id FROM social_link WHERE link = ? AND iduser = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ss', $normalizedLink, $iduser);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$exists = $result && mysqli_num_rows($result) > 0;
mysqli_stmt_close($stmt);

if ($exists) {
    if ($action === 0) {
        $sql = 'UPDATE social_link SET view = view + 1, last_visits = NOW() WHERE link = ? AND iduser = ?';
    } elseif ($action === 1) {
        $sql = 'UPDATE social_link SET liked = 1, disliked = 0, last_visits = NOW() WHERE link = ? AND iduser = ?';
    } else {
        $sql = 'UPDATE social_link SET liked = 0, disliked = 1, last_visits = NOW() WHERE link = ? AND iduser = ?';
    }
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $normalizedLink, $iduser);
} else {
    if ($action === 0) {
        $sql = "INSERT INTO social_link (link, iduser, view, IP, agent, last_visits) VALUES (?, ?, 1, '', '', NOW())";
    } elseif ($action === 1) {
        $sql = "INSERT INTO social_link (link, iduser, liked, IP, agent, last_visits) VALUES (?, ?, 1, '', '', NOW())";
    } else {
        $sql = "INSERT INTO social_link (link, iduser, disliked, IP, agent, last_visits) VALUES (?, ?, 1, '', '', NOW())";
    }
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $normalizedLink, $iduser);
}

$success = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$success) {
    http_response_code(500);
}
echo json_encode(array('success' => $success));

