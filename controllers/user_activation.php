<?php

require_once __DIR__ . '/../config/db.php';

$email_verified = '';
$email_already_verified = '';
$activation_error = '';
$token = trim((string) ($_GET['token'] ?? ''));

if ($token !== '') {
    if (!preg_match('/\A[a-f0-9]{32}\z/D', $token)) {
        $activation_error = '<div class="alert alert-danger">Activation error!</div>';
        return;
    }

    $stmt = mysqli_prepare($connection, 'SELECT is_active FROM users WHERE token = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$user) {
        $activation_error = '<div class="alert alert-danger">Activation error!</div>';
    } elseif ((int) $user['is_active'] === 1) {
        $email_already_verified = '<div class="alert alert-info">User email already verified!</div>';
    } else {
        $update = mysqli_prepare($connection, "UPDATE users SET is_active = '1' WHERE token = ?");
        mysqli_stmt_bind_param($update, 's', $token);
        $updated = mysqli_stmt_execute($update);
        mysqli_stmt_close($update);

        if ($updated) {
            $email_verified = '<div class="alert alert-success">User email successfully verified!</div>';
        } else {
            $activation_error = '<div class="alert alert-danger">Activation error!</div>';
        }
    }
}

