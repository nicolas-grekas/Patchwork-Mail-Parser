<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Bounce_ReceivedFor extends Stream_Parser_Mail_Bounce
{
    protected

    $reason = '',
    $recipient = '',

    $callbacks = array('extractReason' => T_MAIL_BODY),
    $dependencies = array('Mail_Bounce', 'Mail' => 'mimePart');


    protected function extractReason($line)
    {
        if ($this->mimePart->depth)
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
        }
        else if ('---' === substr($line, 0, 3))
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
            $this->register(array('extractReceived' => array('/^Received: /i' => T_MAIL_BODY)));
        }
        else if ('' !== $line = trim($line))
        {
            $this->reason .= $line . ' ';
        }
    }

    protected function extractReceived($line)
    {
        $this->unregister(array(__FUNCTION__ => array('/^Received: /i' => T_MAIL_BODY)));
        $this->register(array('extractReceivedFor' => T_MAIL_BODY));
        $this->extractReceivedFor($line);
    }

    protected function extractReceivedFor($line)
    {
        if (preg_match('/^(\s|Received: )/i', $line))
        {
            if (preg_match('/^\s+for (\S*?@\S*)[^@]*$/', $line, $m))
                $this->recipient = trim($m[1], '<>;');
        }
        else
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));

            if ($this->recipient)
                $this->reportBounce($this->recipient, rtrim($this->reason));
        }
    }
}
