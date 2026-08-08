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
    $characters = '12345679ACDEFGHJKLMNPQRSTUVWXYZ';
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

// Calculate remaining spots
$quanti_posti = mysqli_query($connection, "SELECT * FROM users");
$q_p = mysqli_num_rows($quanti_posti);
$response['remainingSpots'] = 100 - $q_p;


//echo $_SERVER['REQUEST_METHOD'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	
	
    // Usa i dati dal JSON invece che da $_POST
    $email_signin = $data['email_signin'] ?? '';
    $password_signin = $data['password_signin'] ?? '';
    $version = $data['version'] ?? '';
    $event = $data['event'] ?? generateRandomString(5);
    // Debug
    //error_log("Email: " . $email_signin);
    //error_log("Password: " . $password_signin);

    if (empty($email_signin) || empty($password_signin)) {
        $response['message'] = 'Email and password are required';
        echo json_encode($response);
        exit;
    }

    // Clean data 
    
    $user_email = filter_var($email_signin, FILTER_SANITIZE_EMAIL);
    $pswd = mysqli_real_escape_string($connection, $password_signin);

	//echo "UE: " . $user_email;

    // Query if email exists in db
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email_signin);
    mysqli_stmt_execute($stmt);
    $query = mysqli_stmt_get_result($stmt);
    $rowCount = mysqli_num_rows($query);

    if ($rowCount <= 0) {
        $response['message'] = 'User account does not exist.';
    } else {
        $row = mysqli_fetch_array($query);
        
        //print_r($row);
        $password = password_verify($password_signin, $row['password']);

        if (($row['is_active'] == '0') || ($row['is_active'] == '1')) {
            if ($email_signin == $row['email'] && $password === true) {

				if (auth_session_table_available($connection)) {
					$OTT = auth_create_session($connection, (int) $row['id']);
					if ($OTT === false) {
						$response['message'] = 'Impossibile creare la sessione.';
						echo json_encode($response);
						exit;
					}
				} else {
					// Fallback durante un eventuale deploy precedente alla migration.
					$OTT = generateRandomString(28);
					$sql = "UPDATE users SET OTT = ? WHERE id = ?";
					$updateSession = mysqli_prepare($connection, $sql);
					$userId = (int) $row['id'];
					mysqli_stmt_bind_param($updateSession, "si", $OTT, $userId);
					mysqli_stmt_execute($updateSession);
					mysqli_stmt_close($updateSession);
				}

				$_SESSION['OTT'] = $OTT;
                $_SESSION['logged'] = "1";
                $_SESSION['id'] = $row['id'];
                $_SESSION['firstname'] = $row['firstname'];
                $_SESSION['lastname'] = $row['lastname'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['mobilenumber'] = $row['mobilenumber'];
                $_SESSION['token'] = $row['token'];

                $response['success'] = true;
                $response['redirect'] = "./capture.html?rrr=". rand(50000, 1500000);
                $response['OTT'] = $OTT;
                $response['token'] = $row['token'];
                $response['firstname'] = $row['firstname'];
                $response['lastname'] = $row['lastname'];
                
            } else {
                $response['message'] = 'Email o password errate.';
            }
        } else {
            $response['message'] = 'Account verification is required for login.';
        }
    }
}

echo json_encode($response);
