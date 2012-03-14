#!/usr/bin/php -q
<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

require __DIR__ . '/parse-mail-config.php';

use Patchwork\Stream\Parser;
use Patchwork\Stream\Parser\Mail\Bounce;
use Patchwork\Stream\Parser\Mail\Auth;


foreach ($_SERVER['argv'] as $file)
{
    if (false !== $h = fopen($file, 'r'))
    {
        $sendmail = proc_open("exec /usr/sbin/sendmail -i -f '<>' postmaster 2> /dev/null", array(0 => array('pipe', 'r')), $sendmail_pipes);

        $parser = new Parser;
        new Parser\StreamForwarder($parser, $sendmail_pipes[0]);
        $mail = new Parser\Mail($parser);
        new Parser\Mail\EnvelopeHeaders($parser);
        $meId = new Parser\Mail\HeaderCatcher($parser, array('message-id'));
        $auth = new Auth($parser);
        new Auth\Client($parser, $local_whitelist);
        new Auth\Greylist($parser);
        $omId = new Auth\MessageId($parser, isset($db) ? array($db, 'messageIdExists') : false);
        $boun = new Bounce($parser);
        new Bounce\Rfc3464($parser);
        new Bounce\Autoreply($parser);
        new Bounce\Qmail($parser);
        new Bounce\Exim($parser);
        new Bounce\ReceivedFor($parser);

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

        $auth['sent-time'] = isset($db) ? $db->getAuthSentTime($mail->recipient, $boun) : null;

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

        isset($db) && $db->recordParseResults($results);
    }
}
