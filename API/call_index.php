<?php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

$iduser = session_id();
if (isset($_SESSION['id'])) {
    $iduser = $_SESSION['id'];
}


$input = json_decode(file_get_contents('php://input'), true);
$event = $input['event'];
$token = $input['token'];
$OTT = $input['OTT'];



/*if (!isset($_SESSION['logged'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}*/

include('../config/db.php');
require_once('../config/auth.php');

$user = auth_validate_credentials($connection, $OTT, $token);
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessione non valida o scaduta']);
    exit;
}

$iduser = $user['id'];
$token = $user['token'];

// Get credits information
$credits_left = 0;
$credits_used = 0;
if ($stmt = $connection->prepare("SELECT left_credits, used_credits FROM user_credits WHERE user_token = ?")) {
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($credits_left, $credits_used);
    $stmt->fetch();
    $stmt->close();
}

// Return initial data needed by frontend
echo json_encode([
    'iduser' => $iduser,
    'credits_left' => $credits_left,
    'credits_used' => $credits_used,
    'event_push' => $event
]);
?>
