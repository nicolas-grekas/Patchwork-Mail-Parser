#!/usr/bin/php -q
<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

require __DIR__ . '/parse-mail-config.php';

use Patchwork\Stream\Parser;
use Patchwork\Stream\Parser\Mail\Bounce;
use Patchwork\Stream\Parser\Mail\Auth;


$parser = new Parser;
$mail = new Parser\Mail($parser);
new Parser\Mail\EnvelopeHeaders($parser);
$auth = new Auth($parser);
new Auth\Client($parser, $local_whitelist);
new Auth\Greylist($parser);
new Auth\MessageId($parser, isset($db) ? array($db, 'messageIdExists') : false);
$boun = new Bounce($parser);
new Bounce\Rfc3464($parser);
new Bounce\Autoreply($parser);
new Bounce\Qmail($parser);
new Bounce\Exim($parser);
new Bounce\ReceivedFor($parser);

//new Parser\Dumper($parser);

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

    $auth['sent-time'] = isset($db) ? $db->getAuthSentTime($mail->recipient, $boun) : null;

    print_r($mail);
    print_r($auth);
    print_r($boun);
}
