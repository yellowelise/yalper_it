<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

include('../config/db.php');

function commentsFail($status, $message)
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function validVideoLink($link)
{
    $link = str_replace('\\', '/', $link);
    $parts = explode('/', $link);

    return count($parts) === 4
        && $parts[0] === 'upload'
        && $parts[1] === 'uploads'
        && $parts[2] !== ''
        && basename($parts[2]) === $parts[2]
        && basename($parts[3]) === $parts[3]
        && strtolower(pathinfo($parts[3], PATHINFO_EXTENSION)) === 'm3u8'
        && strlen($link) <= 255;
}

$iduser = isset($_SESSION['id']) ? (string) $_SESSION['id'] : session_id();
$ip = isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 16) : '';
$agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $videoLink = isset($_GET['link']) ? (string) $_GET['link'] : '';
    if (!validVideoLink($videoLink)) {
        commentsFail(400, 'Video non valido.');
    }

    // IP, user-agent e identificativi interni non vengono esposti al client.
    $sql = "SELECT c.id, c.comment, c.created_at,
                   COALESCE(NULLIF(CONCAT_WS(' ', u.firstname, u.lastname), ''), 'Ospite') AS username
            FROM video_comments c
            LEFT JOIN users u ON c.iduser = u.id
            WHERE c.video_link = ?
            ORDER BY c.created_at DESC";

    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 's', $videoLink);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $comments = [];
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $comments[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode($comments);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        commentsFail(400, 'Richiesta JSON non valida.');
    }

    $videoLink = isset($data['link']) ? (string) $data['link'] : '';
    $comment = isset($data['comment']) ? trim((string) $data['comment']) : '';

    if (!validVideoLink($videoLink)) {
        commentsFail(400, 'Video non valido.');
    }
    if ($comment === '') {
        commentsFail(400, 'Il commento è vuoto.');
    }
    $commentLength = function_exists('mb_strlen') ? mb_strlen($comment, 'UTF-8') : strlen($comment);
    if ($commentLength > 2000) {
        commentsFail(422, 'Il commento supera i 2000 caratteri.');
    }

    $sql = 'INSERT INTO video_comments (video_link, iduser, comment, IP, agent) VALUES (?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'sssss', $videoLink, $iduser, $comment, $ip, $agent);

    if (!mysqli_stmt_execute($stmt)) {
        error_log('Comment insert failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        commentsFail(500, 'Impossibile salvare il commento.');
    }

    mysqli_stmt_close($stmt);
    http_response_code(201);
    echo json_encode(['success' => true]);
    exit;
}

header('Allow: GET, POST');
commentsFail(405, 'Metodo non consentito.');
