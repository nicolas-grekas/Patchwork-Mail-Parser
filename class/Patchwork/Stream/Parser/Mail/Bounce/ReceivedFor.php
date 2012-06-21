<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Stream\Parser\Mail\Bounce;

use Patchwork\Stream\Parser\Mail\Bounce;

/**
 * The ReceivedFor parser extracts the original recipient from a bounce
 * by looking at Received headers in the excerpt of the original message
 * mentioned in the bounce. The reason of the bounce is extracted from
 * the body of the bounce itself.
 */
class ReceivedFor extends Bounce
{
    protected

    $reasonLines = 10,
    $reason = '',
    $recipient = '',

    $callbacks = array('extractReason' => T_MAIL_BODY),
    $mimePart, $bodyLine,
    $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('mimePart', 'bodyLine'),
    );


    protected function extractReason($line)
    {
        if ($this->mimePart->depth)
        {
            $this->unregister($this->callbacks);
        }
        else if (0 === strncmp(ltrim($this->bodyLine), '---', 3)) // 3 dashes end the reason
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
            $this->register(array('extractReceived' => T_MAIL_BODY));
        }
        else if ($this->reasonLines > 0 && '' !== $line = trim($this->bodyLine))
        {
            $this->reason .= $line . ' ';
            --$this->reasonLines; // Limit extracted reason length
        }
    }

    protected function extractReceived($line)
    {
        if (0 === strncasecmp($this->bodyLine, 'Received:', 9))
        {
            $this->unregister(array(__FUNCTION__  => T_MAIL_BODY));
            $this->register(array('extractReceivedFor' => T_MAIL_BODY));
            $this->extractReceivedFor($line);
            return $this->getExclusivity();
        }
    }

    protected function extractReceivedFor($line)
    {
        if (preg_match('/^(\s|Received: )/i', $this->bodyLine))
        {
            if (preg_match('/^\s+for (\S*?@\S*)[^@]*$/', $this->bodyLine, $m))
            {
                $this->recipient = trim($m[1], '<>;');
            }
        }
        else
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));

            if ($this->recipient)
            {
                $this->reportBounce($this->recipient, rtrim($this->reason));
            }
        }
    }
}
