<?php
session_start();

    include('config/db.php');
	require_once('config/auth.php');
	require_once('config/media.php');
	error_reporting(E_ALL);
	ini_set("display_errors", 1);

if (!isset($_POST['OTT']) || !isset($_POST['token'])) {
    die("Manca Autorizzazione!");
}

$OTT = $_POST['OTT'];
$token = $_POST['token'];

$userRow = auth_validate_credentials($connection, $OTT, $token);
if (!$userRow) {
    http_response_code(401);
    die("Sessione non valida o scaduta.");
}

$userId = $userRow['id'];
$userEmail = $userRow['email'];
$userToken = $userRow['token'];

// Popola la sessione per coerenza
$_SESSION['id'] = $userId;
$_SESSION['email'] = $userEmail;
$_SESSION['token'] = $userToken;


$folder = "";
if (isset($_POST["folder"]))
{
	$folderName = media_normalize_folder_name($_POST['folder']);
	if ($folderName === null) {
		http_response_code(422);
		die("Nome cartella non valido.");
	}

	$folder = $userToken . DIRECTORY_SEPARATOR . $folderName;
}


$chunk_num = 16;
if (isset($_POST["chunk_num"]))
	$chunk_num = (int) $_POST["chunk_num"];

if ($chunk_num < 1 || $chunk_num > 512) {
	http_response_code(422);
	die("Numero chunk non valido.");
}

$version = "3";
if (isset($_POST["version"]))
	$version = (string) $_POST["version"];

if (!preg_match('/^[0-9]$/', $version)) {
	http_response_code(422);
	die("Versione video non valida.");
}

$vbps = -1;
if (isset($_POST["vbps"]))
	$vbps = (int) $_POST["vbps"];

if ($vbps < -1 || $vbps > 100000000) {
	http_response_code(422);
	die("Bitrate non valido.");
}

$partita = "";
if (isset($_POST["partita"]))
	$partita = media_clean_metadata($_POST["partita"]);
	

$autore = "";
if (isset($_POST["autore"]))
	$autore = media_clean_metadata($_POST["autore"]);
	

$from = "";
if (isset($_POST["from"]))
	$from = media_clean_metadata($_POST["from"]);
	

$text_home = "";
if (isset($_POST["text_home"]))
	$text_home = media_clean_metadata($_POST["text_home"]);
	
	

$text_away = "";
if (isset($_POST["text_away"]))
	$text_away = media_clean_metadata($_POST["text_away"]);
	
	

$minutes = "";
if (isset($_POST["minutes"]))
	$minutes = media_clean_metadata($_POST["minutes"]);
	
	
if (($folder != ""))
	{
	$newTime = date("Y-m-d H:i:s",strtotime(date("Y-m-d H:i:s")." +1 minutes"));
	$deleteJob = mysqli_prepare($connection, 'DELETE FROM jobs WHERE iduser = ? AND user_token = ? AND folder = ?');
	mysqli_stmt_bind_param($deleteJob, 'iss', $userId, $userToken, $folder);
	mysqli_stmt_execute($deleteJob);
	mysqli_stmt_close($deleteJob);

	$insertJob = mysqli_prepare($connection, "INSERT INTO jobs (iduser, email, user_token, folder, maked, demon, data_creation, priority, passing, credits_used, credits_left, dont_elaborate_before) VALUES (?, ?, ?, ?, 0, '', NOW(), 1, 1, 0, 0, ?)");
	mysqli_stmt_bind_param($insertJob, 'issss', $userId, $userEmail, $userToken, $folder, $newTime);
	$insert = mysqli_stmt_execute($insertJob);
	mysqli_stmt_close($insertJob);


		//$folder = $_SESSION['token'].DIRECTORY_SEPARATOR . $folder;

		$filePath = 'upload/uploads/' . $folder . DIRECTORY_SEPARATOR;
		  if ( ! is_dir('upload/uploads/' . $folder . DIRECTORY_SEPARATOR)) {
				if (!mkdir('upload/uploads/' . $folder . DIRECTORY_SEPARATOR,0755,true) && !is_dir('upload/uploads/' . $folder . DIRECTORY_SEPARATOR)) {
					http_response_code(500);
					die("Impossibile creare la cartella del job.");
				}
			}
			
		
		if (file_exists($filePath . "detail.json"))
			unlink($filePath . "detail.json");
			
		file_put_contents($filePath . "detail.json", json_encode(array("chunk_num"=>$chunk_num,"folder"=>$folderName,"vbps"=>$vbps,"version"=>$version,"text_home"=>$text_home,"text_away"=>$text_away,"minutes"=>$minutes,"partita"=>$partita,"autore"=>$autore), JSON_UNESCAPED_UNICODE));
//		echo $filePath . "detail.json";
	}

?>
