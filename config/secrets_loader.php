<?php

/**
 * Carica i segreti da un file PHP che restituisce un array.
 *
 * Produzione: /home/yalperit/.config/yalper/secrets.php
 * Sviluppo:   config/secrets.local.php (ignorato dal versionamento)
 */
function yalper_load_secrets()
{
    static $secrets = null;

    if ($secrets !== null) {
        return $secrets;
    }

    $candidates = array(
        '/home/yalperit/.config/yalper/secrets.php',
        __DIR__ . '/secrets.local.php',
    );

    foreach ($candidates as $candidate) {
        if (!is_file($candidate) || !is_readable($candidate)) {
            continue;
        }

        $loaded = require $candidate;
        if (!is_array($loaded)) {
            throw new RuntimeException('Il file dei segreti non restituisce un array.');
        }

        $secrets = $loaded;
        return $secrets;
    }

    throw new RuntimeException('File dei segreti non trovato.');
}

function yalper_secret_section($section, array $requiredKeys)
{
    $secrets = yalper_load_secrets();
    if (!isset($secrets[$section]) || !is_array($secrets[$section])) {
        throw new RuntimeException('Sezione di configurazione mancante: ' . $section);
    }

    $config = $secrets[$section];
    foreach ($requiredKeys as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') {
            throw new RuntimeException('Chiave di configurazione mancante: ' . $section . '.' . $key);
        }
    }

    return $config;
}

