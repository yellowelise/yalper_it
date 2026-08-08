<?php

require_once __DIR__ . '/secrets_loader.php';

ob_start();
if (!isset($_SESSION)) {
    session_start();
}

try {
    $databaseConfig = yalper_secret_section(
        'database',
        array('host', 'name', 'user', 'password')
    );

    // Queste variabili sono ancora usate da alcuni demoni legacy.
    $hostname = $databaseConfig['host'];
    $username = $databaseConfig['user'];
    $password = $databaseConfig['password'];
    $dbname = $databaseConfig['name'];

    $connection = mysqli_connect($hostname, $username, $password, $dbname);
    if (!$connection) {
        throw new RuntimeException('Connessione al database fallita.');
    }
    mysqli_set_charset($connection, 'utf8mb4');
} catch (Throwable $exception) {
    error_log('Configurazione Yalper: ' . $exception->getMessage());
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(500);
    }
    exit('Configurazione del servizio non disponibile.');
}
