<?php

declare (strict_types=1);
$public_path = getcwd();
$uri = urldecode(parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists($public_path . $uri)) {
    return false;
}
$formatted_date_time = date('D M j H:i:s Y');
$request_method = $_SERVER['REQUEST_METHOD'];
$remote_address = $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT'];
file_put_contents('php://stdout', "[{$formatted_date_time}] {$remote_address} [{$request_method}] URI: {$uri}\n");
require_once $public_path . '/index.php';