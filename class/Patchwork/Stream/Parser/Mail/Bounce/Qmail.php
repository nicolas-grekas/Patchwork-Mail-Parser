<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Bounce;

use Patchwork\Stream\Parser\Mail\Bounce;

/**
 * The Qmail parser extracts data from bounces emitted by qmail servers.
 * It loosely follows the QSBMF format described in http://cr.yp.to/proto/qsbmf.txt
 */
class Qmail extends Bounce
{
    protected

    $recipientRx = '/^<(\S*?@\S*)>(.*)/',
    $reason = '',
    $recipient = '',
    $recipients = array(),

    $callbacks = array('extractBodyRecipient' => T_MAIL_BODY),
    $header, $bodyLine, $mimePart,
    $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('header', 'bodyLine', 'mimePart')
    );

    protected function extractBodyRecipient($line)
    {
        $next_recipient = '';
        $new_reason = '';

        if ($this->mimePart->depth)
        {
            $this->unregister($this->callbacks);
        }
        else if ($this->recipients && 0 === strncmp(ltrim($this->bodyLine), '---', 3)) // line is a boundary between the message and the original email
        {
            $this->unregister($this->callbacks);
            foreach ($this->recipients as $e => $r) $this->reportBounce($e, $r);
            $this->recipients = array();
            return $this->getExclusivity();
        }
        else if (preg_match($this->recipientRx, $this->bodyLine, $m)) // line is an email recipient
        {
            $next_recipient = $m[1];
            isset($m[2]) && $new_reason = ltrim($m[2], ': ');
        }
        else if ($this->recipient) // line is considered as a reason or an ending line
        {
            if ('' !== $line = trim($this->bodyLine))
            {
                $this->reason .= $line . ' ';
                return;
            }
        }
        else return;

        if ($this->recipient)
        {
            $this->recipients[$this->recipient] = rtrim($this->reason);
        }

        $this->recipient = $next_recipient;
        $this->reason = $new_reason;
    }
}
