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

namespace Patchwork\Stream\Parser\Mail;

use Patchwork\Stream\Parser;

Parser::createTag('T_BOUNCE_EXCLUSIVITY');

/**
 * The Bounce mail parser does nothing on its own but acts as a container
 * for child sub-parsers that extract bounce data, each child specialized
 * on one type of bounce format.
 *
 * @todo Write a child parser that extracts the final recipient from the DSN
 *       when the original recipient is a forward. See X-Actual-Recipient.
 */
class Bounce extends Parser
{
    protected $bounceClass = 'dsn';
    protected $bounceReports = array();
    protected $hasExclusivity = false;

    public function __construct(parent $parent)
    {
        parent::__construct($parent);
        $this->register(array('catchBounceExclusivity' => T_BOUNCE_EXCLUSIVITY));

        if (__CLASS__ !== $c = get_class($this)) {
            if (isset($this->dependencies['Mail\Bounce']->bounceReports)) {
                $this->bounceReports = &$this->dependencies['Mail\Bounce']->bounceReports[$this->bounceClass][$c];
            } else {
                user_error(__CLASS__.' dependency is not loaded');
            }
        }
    }

    public function getBounceReports()
    {
        return $this->bounceReports;
    }

    protected function getExclusivity()
    {
        $this->hasExclusivity = true;
        if (!is_array($this->bounceReports)) {
            $this->bounceReports = array();
        }

        return T_BOUNCE_EXCLUSIVITY;
    }

    protected function catchBounceExclusivity()
    {
        if (!$this->hasExclusivity) {
            $this->unregisterAll();
        }
    }

    protected function reportBounce($recipient, $reason)
    {
        $this->bounceReports[$recipient] = $reason;
    }
}
