<?php

require_once __DIR__ . '/../config/db.php';

$success_msg = '';
$email_exist = '';
$f_NameErr = '';
$l_NameErr = '';
$_emailErr = '';
$_mobileErr = '';
$_passwordErr = '';
$fNameEmptyErr = '';
$lNameEmptyErr = '';
$emailEmptyErr = '';
$mobileEmptyErr = '';
$passwordEmptyErr = '';
$email_verify_err = '';
$email_verify_success = '';

$countResult = mysqli_query($connection, 'SELECT COUNT(*) AS total FROM users');
$countRow = $countResult ? mysqli_fetch_assoc($countResult) : array('total' => 0);
$restanti = max(0, 100 - (int) $countRow['total']);

if (isset($_POST['submit'])) {
    $firstname = trim((string) ($_POST['firstname'] ?? ''));
    $lastname = trim((string) ($_POST['lastname'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $mobilenumber = trim((string) ($_POST['mobilenumber'] ?? ''));
    $plainPassword = (string) ($_POST['password'] ?? '');

    if ($firstname === '') {
        $fNameEmptyErr = '<div class="alert alert-danger">Obbligatorio.</div>';
    } elseif (strlen($firstname) > 100 || preg_match('/[\x00-\x1F\x7F]/', $firstname)) {
        $f_NameErr = '<div class="alert alert-danger">Nome non valido.</div>';
    }
    if ($lastname === '') {
        $lNameEmptyErr = '<div class="alert alert-danger">Obbligatorio.</div>';
    } elseif (strlen($lastname) > 100 || preg_match('/[\x00-\x1F\x7F]/', $lastname)) {
        $l_NameErr = '<div class="alert alert-danger">Cognome non valido.</div>';
    }
    if ($email === '') {
        $emailEmptyErr = '<div class="alert alert-danger">Obbligatorio.</div>';
    } elseif (strlen($email) > 50 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_emailErr = '<div class="alert alert-danger">Email non valida.</div>';
    }
    if (strlen($mobilenumber) > 50 || preg_match('/[^0-9+ ().-]/', $mobilenumber)) {
        $_mobileErr = '<div class="alert alert-danger">Numero di telefono non valido.</div>';
    }
    if ($plainPassword === '') {
        $passwordEmptyErr = '<div class="alert alert-danger">Obbligatorio.</div>';
    } elseif (
        strlen($plainPassword) > 72 ||
        !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,72}$/u', $plainPassword)
    ) {
        $_passwordErr = '<div class="alert alert-danger">La password deve avere da 8 a 72 caratteri, con maiuscola, minuscola, numero e carattere speciale.</div>';
    }

    $hasErrors = $fNameEmptyErr !== '' || $f_NameErr !== '' ||
        $lNameEmptyErr !== '' || $l_NameErr !== '' ||
        $emailEmptyErr !== '' || $_emailErr !== '' ||
        $_mobileErr !== '' || $passwordEmptyErr !== '' || $_passwordErr !== '';

    if (!$hasErrors) {
        $check = mysqli_prepare($connection, 'SELECT id FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($check, 's', $email);
        mysqli_stmt_execute($check);
        $existing = mysqli_stmt_get_result($check);
        $emailExists = $existing && mysqli_num_rows($existing) > 0;
        mysqli_stmt_close($check);

        if ($emailExists) {
            $email_exist = '<div class="alert alert-danger" role="alert">Email gia in uso.</div>';
        } else {
            $activationToken = bin2hex(random_bytes(16));
            $legacyOtt = substr(strtoupper(bin2hex(random_bytes(20))), 0, 28);
            $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

            mysqli_begin_transaction($connection);
            try {
                $insertUser = mysqli_prepare(
                    $connection,
                    "INSERT INTO users
                     (firstname, lastname, email, mobilenumber, password, token, is_active, date_time, OTT)
                     VALUES (?, ?, ?, ?, ?, ?, '0', CURDATE(), ?)"
                );
                mysqli_stmt_bind_param(
                    $insertUser,
                    'sssssss',
                    $firstname,
                    $lastname,
                    $email,
                    $mobilenumber,
                    $passwordHash,
                    $activationToken,
                    $legacyOtt
                );
                if (!mysqli_stmt_execute($insertUser)) {
                    throw new RuntimeException('Creazione utente fallita.');
                }
                $userId = mysqli_insert_id($connection);
                mysqli_stmt_close($insertUser);

                $insertCredits = mysqli_prepare(
                    $connection,
                    'INSERT INTO user_credits (id_user, user_token, used_credits, left_credits) VALUES (?, ?, 0, 25)'
                );
                mysqli_stmt_bind_param($insertCredits, 'is', $userId, $activationToken);
                if (!mysqli_stmt_execute($insertCredits)) {
                    throw new RuntimeException('Creazione crediti fallita.');
                }
                mysqli_stmt_close($insertCredits);
                mysqli_commit($connection);

                $message = "Ad un passo dalla meta\r\nhttps://yalper.it/user_verification.php?token=" . rawurlencode($activationToken);
                if (mail($email, 'Registrazione YalpeR.it', $message)) {
                    $email_verify_success = '<div class="alert alert-success">Email per verifica inviata.</div>';
                } else {
                    $email_verify_err = '<div class="alert alert-warning">Registrazione completata, ma non e stato possibile inviare la mail di verifica.</div>';
                }
            } catch (Throwable $exception) {
                mysqli_rollback($connection);
                error_log('Registrazione Yalper: ' . $exception->getMessage());
                $email_verify_err = '<div class="alert alert-danger">Registrazione non riuscita. Riprova.</div>';
            }
        }
    }
}

