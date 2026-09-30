<?php
// Dev-only static server router: disable caching for JS/CSS/HTML so browsers
// always pick up the latest edits during active development of the mobile
// app preview. PHP's built-in server ignores headers set before "return
// false", so we must fully serve these files ourselves.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

$mimeTypes = [
    'js'   => 'application/javascript; charset=UTF-8',
    'css'  => 'text/css; charset=UTF-8',
    'html' => 'text/html; charset=UTF-8',
];

if (isset($mimeTypes[$ext]) && is_file($file)) {
    header('Content-Type: ' . $mimeTypes[$ext]);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    readfile($file);
    return true;
}

return false; // let the built-in server serve everything else normally
