<?php
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
 * The Qmail parser extracts data from bounces emitted by qmail servers.
 * It loosely follows the QSBMF format described in http://cr.yp.to/proto/qsbmf.txt.
 */
class Qmail extends Bounce
{
    protected $recipientRx = '/^<(\S*?@\S*)>(.*)/';
    protected $reason = '';
    protected $recipient = '';
    protected $recipients = array();

    protected $callbacks = array('extractBodyRecipient' => T_MAIL_BODY);
    protected $header;
    protected $bodyLine;
    protected $mimePart;
    protected $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('header', 'bodyLine', 'mimePart'),
    );

    protected function extractBodyRecipient($line)
    {
        $next_recipient = '';
        $new_reason = '';

        if ($this->mimePart->depth) {
            $this->unregister($this->callbacks);
        } elseif ($this->recipients && 0 === strncmp(ltrim($this->bodyLine), '---', 3)) {
            // line is a boundary between the message and the original email

            $this->unregister($this->callbacks);
            foreach ($this->recipients as $e => $r) {
                $this->reportBounce($e, $r);
            }
            $this->recipients = array();

            return $this->getExclusivity();
        } elseif (preg_match($this->recipientRx, $this->bodyLine, $m)) {
            // line is an email recipient

            $next_recipient = $m[1];
            if (isset($m[2])) {
                $new_reason = ltrim($m[2], ': ');
            }
        } elseif ($this->recipient) {
            // line is considered as a reason or an ending line

            if ('' !== $line = trim($this->bodyLine)) {
                $this->reason .= $line.' ';

                return;
            }
        } else {
            return;
        }

        if ($this->recipient) {
            $this->recipients[$this->recipient] = rtrim($this->reason);
        }

        $this->recipient = $next_recipient;
        $this->reason = $new_reason;
    }
}
