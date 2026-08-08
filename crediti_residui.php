<?php
include('config/db.php');
require_once('config/auth.php');
header("Access-Control-Allow-Origin: *");
$OTT = "";
if (isset($_POST['OTT']))
	$OTT = $_POST['OTT'];


$token = "";
if (isset($_POST['token']))
	$token = $_POST['token'];

$user = auth_validate_credentials($connection, $OTT, $token, false);
if (!$user) {
	http_response_code(401);
	die("Sessione non valida o scaduta.");
}

$stmt = mysqli_prepare($connection, "SELECT used_credits, left_credits FROM user_credits WHERE user_token = ?");
$authenticatedToken = $user['token'];
mysqli_stmt_bind_param($stmt, "s", $authenticatedToken);
mysqli_stmt_execute($stmt);
$sqlQuery = mysqli_stmt_get_result($stmt);
$countRow = mysqli_num_rows($sqlQuery);
if($countRow == 1){
	while($rowData = mysqli_fetch_array($sqlQuery)){
		$credits_left = $rowData['left_credits'];
		$credits_used = $rowData['used_credits'];
	}
}	

echo "Crediti: ". $credits_left;// . "/" . ($credits_left + $credits_used);
?>
