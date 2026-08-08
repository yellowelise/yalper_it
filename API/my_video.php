<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/media.php';

if (!isset($_SESSION['id']) || !filter_var($_SESSION['id'], FILTER_VALIDATE_INT)) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Non autorizzato'));
    exit;
}

$userId = (int) $_SESSION['id'];
$stmt = mysqli_prepare(
    $connection,
    "SELECT us.id, us.title, us.shorter, us.click, us.long_link,
            us.creation_date, us.visibility,
            CONCAT_WS(' ', u.firstname, u.lastname) AS utente,
            u.id AS maker_token
     FROM url_shorter us
     INNER JOIN users u ON u.id = us.iduser
     WHERE us.long_link <> '' AND us.iduser = ?
     ORDER BY us.creation_date DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$visibilityLabels = array(
    0 => 'Solo io',
    1 => 'Solo Amici',
    2 => 'Solo Amici di Amici',
    3 => 'Basta il link',
    4 => 'Tutti',
);
$videos = array();

while ($result && ($row = mysqli_fetch_assoc($result))) {
    $videoPaths = array();
    foreach (explode('|', (string) $row['long_link']) as $encodedPath) {
        $decodedPath = base64_decode($encodedPath, true);
        $validPath = $decodedPath === false ? null : media_validate_video_relative_path($decodedPath);
        if ($validPath !== null) {
            $videoPaths[] = $validPath;
        }
    }
    if (!$videoPaths) {
        continue;
    }

    $row['video_paths'] = $videoPaths;
    $row['creation_date'] = date('d/m/Y H:i', strtotime($row['creation_date']));
    $visibility = (int) $row['visibility'];
    $row['visibility_text'] = $visibilityLabels[$visibility] ?? 'Sconosciuta';
    $row['maker_token'] = (int) $row['maker_token'];
    unset($row['long_link']);
    $videos[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode(array('success' => true, 'videos' => $videos));

