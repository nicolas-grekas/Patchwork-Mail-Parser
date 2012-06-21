<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

unset($_SERVER['argv'][0]);

ini_set('display_errors', false);
ini_set('log_errors', true);
ini_set('error_log', 'php://stderr');
error_reporting(E_ALL | E_STRICT);
function_exists('xdebug_disable') and xdebug_disable();

function __autoload($class)
{
    $class = str_replace(array('\\', '_'), array('/', '/'), $class);
    require dirname(__DIR__) . '/class/' . $class . '.php';
}

$local_whitelist = array(
    // List of IP or domain names that are under our direct control
);

try
{
    $db = new PDO('mysql:host=localhost;dbname=bounces', 'root', 'root');
    $db = new Patchwork\BouncePdoAdapter($db);
}
catch (PDOException $e)
{
    user_error($e->getMessage(), E_USER_WARNING);
    unset($db);
}
