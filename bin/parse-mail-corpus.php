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
        $parser = new Parser;
        $mail = new Parser\Mail($parser);
        new Parser\Mail\EnvelopeHeaders($parser);
        $auth = new Auth($parser);
        new Auth\Received($parser, $local_whitelist);
        new Auth\Greylist($parser);
        isset($db) && new Auth\MessageId($parser, array($db, 'countMessageId'));
        $boun = new Bounce($parser);
        new Bounce\Rfc3464($parser);
        new Bounce\Autoreply($parser);
        new Bounce\Qmail($parser);
        new Bounce\Exim($parser);
        new Bounce\ReceivedFor($parser);

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

            $auth['sent-time'] = isset($db) ? $db->getAuthSentTime($mail->recipient, $boun) : null;

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