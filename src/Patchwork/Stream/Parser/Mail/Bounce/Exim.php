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

/**
 * The Exim parser extracts bounce data from bounces emitted by exim servers.
 */
class Exim extends Qmail
{
    protected $recipientRx = '/^  (\S*?@\S*)/';

    protected $callbacks = array(
        'extractHeaderRecipient' => T_MAIL_HEADER,
        'catchBoundary' => T_MAIL_BOUNDARY,
    );

    protected function extractHeaderRecipient($line)
    {
        if ('x-failed-recipients' === $this->header->name) {
            $v = explode(', ', $this->header->value);
            foreach ($v as $v) {
                $this->reportBounce($v, '');
            }

            return $this->getExclusivity();
        }
    }

    protected function catchBoundary()
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_BOUNDARY));
        $this->unregister(array('extractHeaderRecipient' => T_MAIL_HEADER));

        if ($this->hasExclusivity) {
            $this->register(array('extractBodyRecipient' => T_MAIL_BODY));
        } else {
            $this->unregisterAll();
        }
    }
}
