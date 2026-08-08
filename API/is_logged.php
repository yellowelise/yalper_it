<?php
	session_start();
	header("Access-Control-Allow-Origin: *");
	//header("Access-Control-Allow-Headers: *");
	header('Content-Type: application/json');
	
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	
	include('../config/db.php');
	require_once('../config/auth.php');

	$OTT = "";
	if (isset($_REQUEST['OTT']))
		$OTT = $_REQUEST['OTT'];
		
	$token = "";
	if (isset($_REQUEST['token']))
		$token = $_REQUEST['token'];
		
$json['code'] = "401"; // non autorizzato
$json['message'] = "Vai al login";

	if ($OTT == "")
	{
		$json['code'] = "401"; // non autorizzato
		$json['message'] = "One Time Token Assente";
		
	}
	else
	{
		$user = auth_validate_credentials($connection, $OTT, $token);
		if (!$user) {
			$json['code'] = "401";
			$json['message'] = "Sessione non valida o scaduta";
		} else {
			$json['code'] = "200";
			$json['message'] = "Autorizzato";
		}
	}


echo json_encode($json);
