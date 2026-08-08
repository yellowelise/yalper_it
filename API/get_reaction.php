<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/media.php';

$rawLink = isset($_GET['link']) ? trim((string) $_GET['link']) : '';
$decodedLink = base64_decode($rawLink, true);
if ($decodedLink !== false && base64_encode($decodedLink) === $rawLink) {
    $rawLink = $decodedLink;
}

$link = media_validate_video_relative_path($rawLink);
$iduser = isset($_GET['iduser']) ? trim((string) $_GET['iduser']) : '';
if ($link === null || !preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $iduser)) {
    http_response_code(422);
    echo json_encode(array(
        'view' => 0,
        'liked' => 0,
        'disliked' => 0,
        'user_reaction' => '0',
    ));
    exit;
}

$stmt = mysqli_prepare($connection, 'SELECT liked, disliked FROM social_link WHERE link = ? AND iduser = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ss', $link, $iduser);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$userReaction = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

$totalsStmt = mysqli_prepare(
    $connection,
    'SELECT COALESCE(SUM(view), 0) AS total_views,
            COALESCE(SUM(liked), 0) AS total_likes,
            COALESCE(SUM(disliked), 0) AS total_dislikes
     FROM social_link WHERE link = ?'
);
mysqli_stmt_bind_param($totalsStmt, 's', $link);
mysqli_stmt_execute($totalsStmt);
$totalsResult = mysqli_stmt_get_result($totalsStmt);
$totals = $totalsResult ? mysqli_fetch_assoc($totalsResult) : array();
mysqli_stmt_close($totalsStmt);

$reaction = '0';
if ($userReaction && (int) $userReaction['liked'] === 1) {
    $reaction = '1';
} elseif ($userReaction && (int) $userReaction['disliked'] === 1) {
    $reaction = '2';
}

echo json_encode(array(
    'view' => (int) ($totals['total_views'] ?? 0),
    'liked' => (int) ($totals['total_likes'] ?? 0),
    'disliked' => (int) ($totals['total_dislikes'] ?? 0),
    'user_reaction' => $reaction,
));

