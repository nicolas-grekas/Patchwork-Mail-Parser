<?php

unset($_SERVER['argv'][0]);

ini_set('display_errors', false);
ini_set('log_errors', true);
ini_set('error_log', 'php://stderr');
error_reporting(E_ALL | E_STRICT);
function_exists('xdebug_disable') and xdebug_disable();

require __DIR__.'/../vendor/autoload.php';

$local_whitelist = array(
    // List of IP or domain names that are under our direct control
);

try {
    $db = new PDO('mysql:host=localhost;dbname=bounces', 'root', 'root');
    $db = new Patchwork\BouncePdoAdapter($db);
} catch (PDOException $e) {
    user_error($e->getMessage(), E_USER_WARNING);
    unset($db);
}
