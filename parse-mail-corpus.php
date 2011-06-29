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

unset($_SERVER['argv'][0]);

foreach ($_SERVER['argv'] as $file)
{
    if (false !== $h = fopen($file, 'r'))
    {
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

        file_put_contents('php://stderr', $file . "\n");

        $parser->parseStream($h);

        fclose($h);

        if ($e = $parser->getErrors())
        {
            file_put_contents('php://stderr', print_r($e, true));
        }
        else
        {
            $auth = $auth->getAuthenticationResults();
            $boun = $boun->getBounceReports();

            $tail = '';

            foreach (array('whitelist') as $test)
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
                echo "{$file}\t\t\t{$tail}";
            }
        }
    }
}
