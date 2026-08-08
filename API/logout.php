<?php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

// Leggi i dati JSON inviati
$json = file_get_contents('php://input');
$data = json_decode($json, true);

//print_r($data);


function generateRandomString($length = 10) {
    $characters = '0BIOabcdefghijklmnopqrstuvwxyz';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}


// Database connection
include('../config/db.php');
require_once('../config/auth.php');

$response = array(
    'success' => false,
    'message' => '',
    'redirect' => '',
    'remainingSpots' => 0
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	
	
	
	
$OTT = $data['OTT'] ?? '';
auth_revoke_session($connection, $OTT);


	
	unset($_SESSION);


	$response['success'] = true;
	$response['redirect'] = "./login.html?rrr=". rand(50000, 1500000);
	$response['OTT'] = $OTT;
}

echo json_encode($response);
