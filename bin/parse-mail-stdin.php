#!/usr/bin/php -q
<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

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
);

$db = new PDO('mysql:host=localhost;dbname=bounces', 'root', 'hp');
$db = new Patchwork_BouncePdoAdapter($db);

$parser = new Patchwork_Stream_Parser;
$mail = new Patchwork_Stream_Parser_Mail($parser);
new Patchwork_Stream_Parser_Mail_EnvelopeHeaders($parser);
$auth = new Patchwork_Stream_Parser_Mail_Auth($parser);
new Patchwork_Stream_Parser_Mail_Auth_Received($parser, $local_whitelist);
new Patchwork_Stream_Parser_Mail_Auth_Greylist($parser);
new Patchwork_Stream_Parser_Mail_Auth_MessageId($parser, array($db, 'countMessageId'));
$boun = new Patchwork_Stream_Parser_Mail_Bounce($parser);
new Patchwork_Stream_Parser_Mail_Bounce_Rfc3464($parser);
new Patchwork_Stream_Parser_Mail_Bounce_Autoreply($parser);
new Patchwork_Stream_Parser_Mail_Bounce_Qmail($parser);
new Patchwork_Stream_Parser_Mail_Bounce_Exim($parser);
new Patchwork_Stream_Parser_Mail_Bounce_ReceivedFor($parser);

//new Stream_Parser_Log($parser);

$parser->parseStream(STDIN);

if ($e = $parser->getErrors())
{
    print_r($e);
}
else
{
    $mail = $mail->getEnvelope();
    $auth = $auth->getAuthenticationResults();
    $boun = $boun->getBounceReports();

    $auth['sent-time'] = $db->getAuthSentTime($mail->recipient, $boun);

    print_r($mail);
    print_r($auth);
    print_r($boun);
}
