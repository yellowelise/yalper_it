<?php

// Sessioni applicative multiple. Il token restituito al client non viene
// salvato in chiaro: nel DB resta solo il suo hash SHA-256.
if (!defined('AUTH_SESSION_TTL_DAYS')) {
    define('AUTH_SESSION_TTL_DAYS', 30);
}

function auth_session_table_available($connection)
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    $result = mysqli_query($connection, "SHOW TABLES LIKE 'user_sessions'");
    $available = $result && mysqli_num_rows($result) > 0;

    if ($result) {
        mysqli_free_result($result);
    }

    return $available;
}

function auth_generate_session_token()
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function auth_create_session($connection, $userId)
{
    if (!auth_session_table_available($connection)) {
        return null;
    }

    $plainToken = auth_generate_session_token();
    $tokenHash = hash('sha256', $plainToken);
    $expiresAt = date('Y-m-d H:i:s', time() + (AUTH_SESSION_TTL_DAYS * 86400));

    $stmt = mysqli_prepare(
        $connection,
        'INSERT INTO user_sessions (user_id, token_hash, expires_at, last_used_at) VALUES (?, ?, ?, NOW())'
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iss', $userId, $tokenHash, $expiresAt);
    $created = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $created ? $plainToken : false;
}

function auth_validate_credentials($connection, $sessionToken, $userToken, $touchSession = true)
{
    if ($sessionToken === '' || $userToken === '') {
        return null;
    }

    // Dopo la migration si usa esclusivamente user_sessions. La migration
    // importa anche gli OTT correnti, così le code IndexedDB già esistenti
    // continuano a funzionare durante il periodo di grazia.
    if (auth_session_table_available($connection)) {
        $tokenHash = hash('sha256', $sessionToken);
        $stmt = mysqli_prepare(
            $connection,
            'SELECT u.*, s.id AS auth_session_id
             FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = ?
               AND u.token = ?
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()
             LIMIT 1'
        );

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $tokenHash, $userToken);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if ($user && $touchSession) {
            $sessionId = (int) $user['auth_session_id'];
            $touch = mysqli_prepare($connection, 'UPDATE user_sessions SET last_used_at = NOW() WHERE id = ?');
            if ($touch) {
                mysqli_stmt_bind_param($touch, 'i', $sessionId);
                mysqli_stmt_execute($touch);
                mysqli_stmt_close($touch);
            }
        }

        return $user ?: null;
    }

    // Compatibilità temporanea se il codice viene pubblicato prima della
    // migration. In questa modalità resta attivo il vecchio singolo OTT.
    $stmt = mysqli_prepare($connection, 'SELECT * FROM users WHERE OTT = ? AND token = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $sessionToken, $userToken);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $user ?: null;
}

function auth_revoke_session($connection, $sessionToken)
{
    if ($sessionToken === '') {
        return false;
    }

    if (auth_session_table_available($connection)) {
        $tokenHash = hash('sha256', $sessionToken);
        $stmt = mysqli_prepare(
            $connection,
            'UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL'
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $tokenHash);
        mysqli_stmt_execute($stmt);
        $revoked = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $revoked;
    }

    // Fallback legacy: rende inutilizzabile il vecchio OTT della sessione.
    $replacement = auth_generate_session_token();
    $stmt = mysqli_prepare($connection, 'UPDATE users SET OTT = ? WHERE OTT = ?');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $replacement, $sessionToken);
    mysqli_stmt_execute($stmt);
    $revoked = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $revoked;
}

