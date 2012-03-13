<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Bounce;

use Patchwork\Stream\Parser\Mail\Bounce;

class ReceivedFor extends Bounce
{
    protected

    $reasonLines = 10,
    $reason = '',
    $recipient = '',

    $callbacks = array('extractReason' => T_MAIL_BODY),
    $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('mimePart', 'bodyLine'),
    );


    protected function extractReason($line)
    {
        if ($this->mimePart->depth)
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
        }
        else if (0 === strncmp($this->bodyLine, '---', 3))
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
            $this->register(array('extractReceived' => T_MAIL_BODY));
        }
        else if ($this->reasonLines > 0 && '' !== $line = trim($this->bodyLine))
        {
            $this->reason .= $line . ' ';
            --$this->reasonLines;
        }
    }

    protected function extractReceived($line)
    {
        if ( 0 === strncasecmp($this->bodyLine, 'Received:', 9)
          && preg_match('/^Received:\s/i', $this->bodyLine) )
        {
            $this->unregister(array(__FUNCTION__  => T_MAIL_BODY));
            $this->register(array('extractReceivedFor' => T_MAIL_BODY));
            $this->extractReceivedFor($line);
        }
    }

    protected function extractReceivedFor($line)
    {
        if (preg_match('/^(\s|Received: )/i', $this->bodyLine))
        {
            if (preg_match('/^\s+for (\S*?@\S*)[^@]*$/', $this->bodyLine, $m))
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
