<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Stream\Parser;

use Patchwork\Stream\Parser;

/**
 * The HeaderCatcher mail parser extracts requested header from a message.
 */
class HeaderCatcher extends Parser
{
    protected

    $caughtHeaders = array(),
    $callbacks = array(
        'catchHeader' => T_MAIL_HEADER,
        'unregisterAll' => T_MAIL_BOUNDARY,
    ),
    $header,
    $dependencies = array('Mail'=> 'header');


    function __construct(parent $parent, array $headers)
    {
        if ($headers)
        {
            parent::__construct($parent);
            foreach ($headers as $h) $this->caughtHeaders[$h] = false;
        }
    }

    protected function catchHeader()
    {
        if (isset($this->caughtHeaders[$this->header->name]))
        {
            $this->caughtHeaders[$this->header->name] = $this->header->value;
        }
    }

    function getCaughtHeaders()
    {
       return $this->caughtHeaders;
    }
}
