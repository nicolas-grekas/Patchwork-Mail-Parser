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
        $sendmail = proc_open("exec /usr/sbin/sendmail -i -f '<>' postmaster 2> /dev/null", array(0 => array('pipe', 'r')), $sendmail_pipes);

        $parser = new Patchwork_Stream_Parser;
        new Patchwork_Stream_Parser_StreamForwarder($parser, $sendmail_pipes[0]);
        $mail = new Patchwork_Stream_Parser_Mail($parser);
        new Patchwork_Stream_Parser_Mail_EnvelopeHeaders($parser);
        $meId = new Patchwork_Stream_Parser_Mail_HeaderCatcher($parser, array('message-id'));
        $auth = new Patchwork_Stream_Parser_Mail_Auth($parser);
        new Patchwork_Stream_Parser_Mail_Auth_Received($parser, $local_whitelist);
        new Patchwork_Stream_Parser_Mail_Auth_Greylist($parser);
        $omId = new Patchwork_Stream_Parser_Mail_Auth_MessageId($parser, array($db, 'countMessageId'));
        $boun = new Patchwork_Stream_Parser_Mail_Bounce($parser);
        new Patchwork_Stream_Parser_Mail_Bounce_Rfc3464($parser);
        new Patchwork_Stream_Parser_Mail_Bounce_Autoreply($parser);
        new Patchwork_Stream_Parser_Mail_Bounce_Qmail($parser);
        new Patchwork_Stream_Parser_Mail_Bounce_Exim($parser);
        new Patchwork_Stream_Parser_Mail_Bounce_ReceivedFor($parser);

        $parser->parseStream($h);

        fclose($h);

        if ($e = $parser->getErrors())
        {
            file_put_contents('php://stderr', print_r($e, true));
        }

        $mail = $mail->getEnvelope();
        $auth = $auth->getAuthenticationResults();
        $boun = $boun->getBounceReports();
        $meId = $meId->getCaughtHeaders();
        $omId = $omId->getMessageId();
        $meId = trim($meId['message-id'], '<>;');

        $auth['sent-time'] = $db->getAuthSentTime($mail->recipient, $boun);

        $results = array();
        $result_const = array(
            'bounce_message_id' => $meId,
            'bounced_message_id' => $omId,
            'bounced_sender' => $mail->recipient,
        );

        foreach ($auth as $k => $v) $result_const['auth_' . strtr($k, '-', '_')] = $v;

        $filled = 0;

        foreach ($boun as $class => $parser)
        {
            foreach ($parser as $parser => $recipient)
            {
                if (null !== $recipient)
                {
                    if (empty($recipient))
                    {
                        $filled || $filled = 1;

                        $results[] = $result_const + array(
                            'bounce_type' => $class,
                            'bounce_parser' => $parser,
                        );
                    }
                    else foreach ($recipient as $recipient => $reason)
                    {
                        $filled = 2;

                        $results[] = $result_const + array(
                            'bounce_type' => $class,
                            'bounce_parser' => $parser,
                            'bounce_reason' => $reason,
                            'bounced_recipient' => $recipient,
                        );
                    }
                }
            }
        }

        if ($filled) proc_terminate($sendmail);
        else $results[] = $result_const;

        fclose($sendmail_pipes[0]);
        proc_close($sendmail);

        $db->recordParseResults($results);
    }
}
