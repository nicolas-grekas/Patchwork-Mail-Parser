<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Stream\Parser\Mail\Auth;

use Patchwork\Stream\Parser;
use Patchwork\Stream\Parser\Mail\Auth;

/**
 * The Client parser reports if the client IP or hostname from
 * which a message is received is within a given whitelist.
 */
class Client extends Auth
{
    protected

    $whitelist,
    $authClass = 'whitelist',
    $callbacks = array('testEnvelopeClient' => T_MAIL_BOUNDARY),
    $envelope,
    $dependencies = array(
        'Mail\Auth',
        'Mail' => 'envelope',
    );

    function __construct(Parser $parent, $whitelist = null)
    {
        $this->whitelist = $whitelist;
        parent::__construct($parent);
        $this->reportAuth(false);
    }

    protected function testEnvelopeClient($line)
    {
        $this->unregister($this->callbacks);

        if ('127.0.0.1' === $this->envelope->clientIp)
        {
            $this->reportAuth('local-host');
        }
        else foreach ($this->whitelist as $w)
        {
            if ($w == $this->envelope->clientIp || $w == $this->envelope->clientHostname)
            {
                $this->reportAuth('local-list');
                break;
            }
        }
    }
}
