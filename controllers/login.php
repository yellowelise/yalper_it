<?php
   
    // Database connection
    include('config/db.php');
    global $wrongPwdErr, $accountNotExistErr, $emailPwdErr, $verificationRequiredErr, $email_empty_err, $pass_empty_err;
    $wrongPwdErr = $accountNotExistErr = $emailPwdErr = $verificationRequiredErr = $email_empty_err = $pass_empty_err = '';
    if(isset($_POST['login'])) {
        $email_signin = strtolower(trim((string) ($_POST['email_signin'] ?? '')));
        $password_signin = (string) ($_POST['password_signin'] ?? '');
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
                if($is_active == '1') {
                    if($password === true) {
                       session_regenerate_id(true);
                       $_SESSION['id'] = $id;
                       $_SESSION['firstname'] = $firstname;
                       $_SESSION['lastname'] = $lastname;
                       $_SESSION['email'] = $email;
                       $_SESSION['mobilenumber'] = $mobilenumber;
                       $_SESSION['token'] = $token;
                       header("Location: ./dashboard.php");
                       exit;
                    } else {
                        $emailPwdErr = '<div class="alert alert-danger">
                                Either email or password is incorrect.
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
