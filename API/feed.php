<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once('../config/db.php');
require_once('../config/media.php');

$viewerId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$filterValue = isset($_GET['maker_token']) ? trim((string) $_GET['maker_token']) : '';
$creatorId = null;

if ($filterValue !== '') {
    if (ctype_digit($filterValue)) {
        $creatorId = (int) $filterValue;
    } elseif (strlen($filterValue) <= 255) {
        // Compatibilità per i vecchi link che contenevano users.token.
        $resolve = mysqli_prepare($connection, 'SELECT id FROM users WHERE token = ? LIMIT 1');
        mysqli_stmt_bind_param($resolve, 's', $filterValue);
        mysqli_stmt_execute($resolve);
        mysqli_stmt_bind_result($resolve, $resolvedId);
        if (mysqli_stmt_fetch($resolve)) {
            $creatorId = (int) $resolvedId;
        }
        mysqli_stmt_close($resolve);
    }

    if ($creatorId === null) {
        $creatorId = -1;
    }
}

$sql = "SELECT us.id, us.title, us.shorter, us.click, us.long_link,
               us.creation_date, us.visibility,
               CONCAT_WS(' ', u.firstname, u.lastname) AS utente,
               u.id AS maker_id
        FROM url_shorter us
        INNER JOIN users u ON us.iduser = u.id
        WHERE us.long_link <> ''
          AND (us.iduser = ? OR us.visibility = 4)";

if ($creatorId !== null) {
    $sql .= ' AND us.iduser = ?';
}
$sql .= ' ORDER BY us.creation_date DESC';

$stmt = mysqli_prepare($connection, $sql);
if ($creatorId === null) {
    mysqli_stmt_bind_param($stmt, 'i', $viewerId);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $viewerId, $creatorId);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$videos = [];
$filterMap = [];
$visibilityLabels = [
    0 => 'Solo io',
    1 => 'Solo Amici',
    2 => 'Solo Amici di Amici',
    3 => 'Basta il link',
    4 => 'Tutti'
];

while ($result && ($row = mysqli_fetch_assoc($result))) {
    $videoPaths = [];
    foreach (array_filter(explode('|', $row['long_link'])) as $encodedPath) {
        $decodedPath = base64_decode($encodedPath, true);
        $validatedPath = $decodedPath === false ? null : media_validate_video_relative_path($decodedPath);
        if ($validatedPath !== null) {
            $videoPaths[] = $validatedPath;
        }
    }

    if (count($videoPaths) === 0) {
        continue;
    }

    $row['video_paths'] = $videoPaths;
    $row['creation_date'] = date('d/m/Y H:i', strtotime($row['creation_date']));
    $row['visibility_text'] = $visibilityLabels[(int) $row['visibility']] ?? 'Sconosciuto';

    // Nome mantenuto per compatibilità col frontend, valore non più segreto.
    $row['maker_token'] = (string) $row['maker_id'];
    unset($row['maker_id'], $row['long_link']);

    if ($creatorId !== null) {
        $filterMap[$row['maker_token']] = [
            'utente' => $row['utente'],
            'token' => $row['maker_token']
        ];
    }

    $videos[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'filter' => array_values($filterMap),
    'videos' => $videos
]);
