<?php
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
 * The dumper parser echoes the parsed stream line by line for easier debugging.
 */
class Dumper extends Parser
{
    protected $width = 50;
    protected $callbacks = array('logLine' => T_STREAM_LINE);

    public function __construct(parent $parent, $width = null)
    {
        parent::__construct($parent);
        if (isset($width)) {
            $this->width = $width;
        }
    }

    protected function logLine($line, $t)
    {
        $line = strtr($line, "\r\n\t", '   ');
        $line = substr($line, 0, $this->width);

        echo sprintf("% 5d: % -{$this->width}s", $this->lineNumber, $line);

        unset($t[T_STREAM_LINE]);
        foreach ($t as $t) {
            echo ' ', self::getTagName($t);
        }

        echo "\n";
    }
}
