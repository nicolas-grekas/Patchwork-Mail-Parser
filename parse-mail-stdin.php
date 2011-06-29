#!/usr/bin/php -q
<?php // vi: set encoding=utf-8 expandtab shiftwidth=4 tabstop=4:

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 'stderr');

require __DIR__ . '/Stream/Parser.php';
require __DIR__ . '/Stream/Parser/Log.php';
require __DIR__ . '/Stream/Parser/Mail.php';
require __DIR__ . '/Stream/Parser/Mail/Auth.php';
require __DIR__ . '/Stream/Parser/Mail/Bounce.php';
require __DIR__ . '/Stream/Parser/Mail/EnvelopeHeaders.php';
require __DIR__ . '/Stream/Parser/Mail/Auth/Received.php';
require __DIR__ . '/Stream/Parser/Mail/Auth/Greylist.php';
require __DIR__ . '/Stream/Parser/Mail/Bounce/Rfc3464.php';
require __DIR__ . '/Stream/Parser/Mail/Bounce/Autoreply.php';
require __DIR__ . '/Stream/Parser/Mail/Bounce/Qmail.php';

$local_whitelist = array(
);

$parser = new Stream_Parser;
new Stream_Parser_Mail($parser);
new Stream_Parser_Mail_EnvelopeHeaders($parser);
$auth = new Stream_Parser_Mail_Auth($parser);
new Stream_Parser_Mail_Auth_Received($parser, $local_whitelist);
new Stream_Parser_Mail_Auth_Greylist($parser);
$boun = new Stream_Parser_Mail_Bounce($parser);
new Stream_Parser_Mail_Bounce_Rfc3464($parser);
new Stream_Parser_Mail_Bounce_Autoreply($parser);
new Stream_Parser_Mail_Bounce_Qmail($parser);

//new Stream_Parser_Log($parser);

$parser->parseStream(STDIN);

if ($e = $parser->getErrors())
{
    print_r($e);
}
else
{
    $auth = $auth->getAuthenticationResults();
    $boun = $boun->getBounceReports();

    print_r($auth);
    print_r($boun);
}
