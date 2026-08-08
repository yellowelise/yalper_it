<?php

function media_normalize_folder_name($rawFolder)
{
    $rawFolder = trim((string) $rawFolder);
    if ($rawFolder === '' || strlen($rawFolder) > 220 || strpos($rawFolder, "\0") !== false) {
        return null;
    }

    // Una cartella è sempre un singolo componente: mai slash o backslash.
    if (strpos($rawFolder, '/') !== false || strpos($rawFolder, '\\') !== false) {
        return null;
    }

    if (!preg_match('/^(\d{1,2})_(\d{1,2})_(\d{4})_(.+)$/u', $rawFolder, $matches)) {
        return null;
    }

    $day = (int) $matches[1];
    $month = (int) $matches[2];
    $year = (int) $matches[3];
    if (!checkdate($month, $day, $year)) {
        return null;
    }

    $normalized = sprintf('%02d_%02d_%04d_%s', $day, $month, $year, $matches[4]);

    // Mantiene lettere Unicode e la punteggiatura usata dai nomi partita,
    // escludendo metacaratteri shell, separatori e caratteri di controllo.
    if (!preg_match('/^[\p{L}\p{N} _().-]+$/u', $normalized)) {
        return null;
    }

    return $normalized;
}

function media_validate_upload_filename($fileName)
{
    $fileName = (string) $fileName;
    if (
        $fileName === '' ||
        strlen($fileName) > 120 ||
        basename($fileName) !== $fileName ||
        strpos($fileName, "\0") !== false ||
        !preg_match('/^[A-Za-z0-9._-]+$/', $fileName)
    ) {
        return false;
    }

    $allowedExtensions = ['webm', 'wav', 'mp4', 'mkv', 'mp3', 'ogg', 'bmp', 'jpg', 'png'];
    return in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), $allowedExtensions, true);
}

function media_clean_metadata($value, $maxLength = 255)
{
    $value = trim((string) $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function media_validate_video_relative_path($path, $expectedUserToken = null)
{
    $path = str_replace('\\', '/', trim((string) $path));
    $parts = explode('/', $path);

    if (
        count($parts) !== 4 ||
        $parts[0] !== 'upload' ||
        $parts[1] !== 'uploads' ||
        $parts[2] === '' ||
        !preg_match('/^[A-Za-z0-9_-]{16,128}$/', $parts[2]) ||
        basename($parts[2]) !== $parts[2] ||
        basename($parts[3]) !== $parts[3] ||
        strlen($parts[3]) > 255 ||
        !preg_match('/^[\p{L}\p{N} _().-]+\.m3u8$/u', $parts[3]) ||
        strtolower(pathinfo($parts[3], PATHINFO_EXTENSION)) !== 'm3u8'
    ) {
        return null;
    }

    if ($expectedUserToken !== null && !hash_equals((string) $expectedUserToken, $parts[2])) {
        return null;
    }

    return implode('/', $parts);
}
