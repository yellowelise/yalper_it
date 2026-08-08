<?php
   session_start();
   
   
    // Database connection
    include('../config/db.php');
    global $wrongPwdErr, $accountNotExistErr, $emailPwdErr, $verificationRequiredErr, $email_empty_err, $pass_empty_err;
    $wrongPwdErr = $accountNotExistErr = $emailPwdErr = $verificationRequiredErr = $email_empty_err = $pass_empty_err = '';
    
    $event = "";
    if (isset($_REQUEST['event']))
		$event= $_REQUEST['event'];
    
        $quanti_posti = mysqli_query($connection, "SELECT * FROM users");
        $q_p = mysqli_num_rows($quanti_posti);
		$restanti = 100 - $q_p;


    
    if(isset($_POST['login'])) {
        $email_signin = strtolower(trim((string) ($_POST['email_signin'] ?? '')));
        $password_signin = (string) ($_POST['password_signin'] ?? '');
        $event = trim((string) ($_POST['event'] ?? ''));
        $version = trim((string) ($_POST['version'] ?? ''));
        $stmt = mysqli_prepare($connection, 'SELECT * FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email_signin);
        mysqli_stmt_execute($stmt);
        $query = mysqli_stmt_get_result($stmt);
        $rowCount = mysqli_num_rows($query);
        if(!empty($email_signin) && !empty($password_signin)){
            // Check if email exist
            if($rowCount <= 0) {
                $accountNotExistErr = '<div class="alert alert-danger">
                        User account does not exist.
                    </div>';
            } else {
                // Fetch user data and store in php session
                while($row = mysqli_fetch_array($query)) {
                    $id            = $row['id'];
                    $firstname     = $row['firstname'];
                    $lastname      = $row['lastname'];
                    $email         = $row['email'];
                    $mobilenumber   = $row['mobilenumber'];
                    $pass_word     = $row['password'];
                    $token         = $row['token'];
                    $is_active     = $row['is_active'];
                }
                // Verify password
                $password = password_verify($password_signin, $pass_word);
                // Allow only verified user
                if(($is_active == '0')||($is_active == '1')) {
                    if($password === true) {
                       session_regenerate_id(true);
                       $_SESSION['logged'] = "1";
                       $_SESSION['id'] = $id;
                       $_SESSION['firstname'] = $firstname;
                       $_SESSION['lastname'] = $lastname;
                       $_SESSION['email'] = $email;
                       $_SESSION['mobilenumber'] = $mobilenumber;
                       $_SESSION['token'] = $token;
                       header('Location: ./index.php?event=' . rawurlencode($event) . '&version=' . rawurlencode($version));
                       exit;
                    } else {
                        $emailPwdErr = '<div class="alert alert-danger">
                               Email o password errate.
                            </div>';
                    }
                } else {
                    $verificationRequiredErr = '<div class="alert alert-danger">
                            Account verification is required for login.
                        </div>';
                }
            }
        } else {
            if(empty($email_signin)){
                $email_empty_err = "<div class='alert alert-danger email_alert'>
                            Email not provided.
                    </div>";
            }
            
            if(empty($password_signin)){
                $pass_empty_err = "<div class='alert alert-danger email_alert'>
                            Password not provided.
                        </div>";
            }            
        }
    }
?>
<!doctype html>
<html lang="it">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="shortcut icon" href="../favicons/favicon.ico">
	  <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon32.png">
	  <link rel="icon" type="image/png" sizes="16x16" href="../favicons/favicon16.png">
		<link rel="manifest" href="../manifest.json?ddd" />

	  <!-- Apple -->
	  <meta name="apple-mobile-web-app-title" content="YalpeR - Live Replay System">
	
	  <link rel="apple-touch-icon" sizes="180x180" href="../favicons/favicon180.png">
<script
			  src="https://code.jquery.com/jquery-3.6.4.min.js"
			  integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8="
			  crossorigin="anonymous"></script>
<script	  src="../js/button.js"></script>
			  
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/styles.css" type="text/css">
    <title>YalpeR - Live Replay System</title>
    <!-- jQuery + Bootstrap JS -->
    <!--script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script-->
    <!--script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script-->
<script
			  src="https://code.jquery.com/jquery-3.6.4.min.js"
			  integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8="
			  crossorigin="anonymous"></script>    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <link rel="stylesheet" href="../css/styles.css?fdsfds" type="text/css">

</head>
<body>
    <!-- Login form -->
<!-- Install button, hidden by default -->
    <div id="installContainer" class="hidden" style="display:none;">
      <button id="butInstall" type="button">Installa l'applicazione sul tuo dispositivo</button>
    </div>    
    <br />
    <br />
    <div class="row justify-content-center">
		<?php
		echo $emailPwdErr . ""; ?>
            <div class="col-sm-8 col-xs-12 col-md-6" style="margin: auto;background: #ffffff;box-shadow: 0px 14px 80px rgba(34, 35, 58, 0.2);padding: 40px 55px 45px 55px;transition: all .3s;border-radius: 20px;">
				<h4>Closed Beta, mancano solo <?php echo "<b>" . $restanti . "</b>";?> posti disponibili</h4>
				<h6><b>Perché usare Yalper?</b></h6>
				<h6>Puoi salvare sul cloud solo le azioni che ti interessano senza riempire lo smartphone di video e senza doverle estrapolare dopo.</h6>
                <form action="" method="post">
                    <h3>Accedi</h3>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email_signin" id="email_signin" />
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" class="form-control" name="password_signin" id="password_signin" />
                    </div>
            
                    <br /><button type="submit" name="login" id="sign_in" class="btn btn-outline-primary btn-lg btn-block">Accedi</button>
                </form>

                    <br /><a href="signup.php"  class="btn btn-outline-danger btn-lg btn-block">Registrati</a>
					<div id="log"></div>
            </div>
    </div>
</body>
</html>
<script>

</script>	
