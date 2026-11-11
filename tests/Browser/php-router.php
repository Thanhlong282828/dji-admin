<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$public = realpath(__DIR__.'/../../public');
$candidate = is_string($path) && $public !== false ? realpath($public.$path) : false;
$storage = realpath(__DIR__.'/../../storage/app/public');
$insidePublic = is_string($candidate) && is_string($public) && str_starts_with($candidate, $public.DIRECTORY_SEPARATOR);
$insideStorage = is_string($candidate) && is_string($storage) && str_starts_with($candidate, $storage.DIRECTORY_SEPARATOR);
if (is_string($candidate) && ($insidePublic || $insideStorage) && is_file($candidate)) {
    $mime = match (strtolower(pathinfo($candidate, PATHINFO_EXTENSION))) {
        'css' => 'text/css; charset=utf-8',
        'js', 'mjs' => 'text/javascript; charset=utf-8',
        'json', 'map' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        default => mime_content_type($candidate),
    };
    if (is_string($mime)) {
        header('Content-Type: '.$mime);
    }
    readfile($candidate);

    return true;
}

require __DIR__.'/../../public/index.php';
