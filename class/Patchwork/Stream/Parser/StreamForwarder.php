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
 * The StreamForwarder parser writes every line of the parsed stream to an other stream.
 */
class StreamForwarder extends Parser
{
    protected

    $stream,
    $callbacks = array('writeLine' => T_STREAM_LINE);


    function __construct(parent $parent, $stream)
    {
        parent::__construct($parent);
        $this->stream = $stream;
    }

    protected function writeLine($line)
    {
        fwrite($this->stream, $line);
    }
}
