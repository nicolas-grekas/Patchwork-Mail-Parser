<?php

// vi: set fenc=utf-8 ts=4 sw=4 et:
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
 * The Autoreply bounce parser detects some vacation messages.
 */
class Autoreply extends Bounce
{
    protected $bounceClass = 'vacation';
    protected $callbacks = array(
        'catchAutoReply' => T_MAIL_HEADER,
        'unregisterAll' => T_MAIL_BOUNDARY,
    );
    protected $header;
    protected $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('header'),
    );

    protected function catchAutoReply($line)
    {
        if ('auto-submitted' === $this->header->name && false !== stripos($line, 'vacation')) {
            return $this->getExclusivity();
        }
    }
}
