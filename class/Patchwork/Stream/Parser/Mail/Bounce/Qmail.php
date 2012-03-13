<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Bounce;

use Patchwork\Stream\Parser\Mail\Bounce;

class Qmail extends Bounce
{
    protected

    $recipientRx = '/^<(\S*?@\S*)>(.*)/',
    $reason = '',
    $recipient = '',

    $callbacks = array('extractBodyRecipient' => T_MAIL_BODY),
    $dependencies = array(
        'Mail\Bounce',
        'Mail' => 'bodyLine',
    );

    protected function extractBodyRecipient($line)
    {
        $next_recipient = '';
        $new_reason = '';

        if (0 === strncmp($this->bodyLine, '---', 3)) //line is a boundary between the message and the original email
        {
            $this->unregisterAll();
        }
        else if (preg_match($this->recipientRx, $this->bodyLine, $m)) //line is an email recipient
        {
            $next_recipient = $m[1];
            isset($m[2]) && $new_reason = ltrim($m[2], ': ');
        }
        else if ($this->recipient) //line is considered as a reason or an ending line
        {
            if ('' !== $line = trim($this->bodyLine))
            {
                $this->reason .= $line . ' ';
                return;
            }
        }
        else return;

        if ($this->recipient)
            $this->reportBounce($this->recipient, rtrim($this->reason));

        $this->recipient = $next_recipient;
        $this->reason = $new_reason;
    }
}
