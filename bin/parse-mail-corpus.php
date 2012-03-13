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

unset($_SERVER['argv'][0]);

foreach ($_SERVER['argv'] as $file)
{
    if (false !== $h = fopen($file, 'r'))
    {
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

        file_put_contents('php://stderr', $file . "\n");

        $parser->parseStream($h);

        fclose($h);

        if ($e = $parser->getErrors())
        {
            file_put_contents('php://stderr', print_r($e, true));
        }
        else
        {
            $mail = $mail->getEnvelope();
            $auth = $auth->getAuthenticationResults();
            $boun = $boun->getBounceReports();

            $auth['sent-time'] = $db->getAuthSentTime($mail->recipient, $boun);

            $tail = '';

            foreach (array('whitelist', 'message-id', 'sent-time') as $test)
            {
                $tail .= "\t";
                empty($auth[$test]) || $tail .= $auth[$test];
            }

            $tail .= "\n";
            $echoed = false;

            foreach ($boun as $class => $parser)
            {
                foreach ($parser as $parser => $recipient)
                {
                    if (null !== $recipient)
                    {
                        $echoed = true;

                        if (empty($recipient))
                        {
                            echo "{$file}\t{$class}\t{$parser}\t\t{$tail}";
                        }
                        else foreach ($recipient as $recipient => $reason)
                        {
                            echo "{$file}\t{$class}\t{$parser}\t{$recipient}\t{$reason}{$tail}";
                        }
                    }
                }
            }

            if (!$echoed)
            {
                echo "{$file}\t\t\t\t{$tail}";
            }
        }
    }
}
