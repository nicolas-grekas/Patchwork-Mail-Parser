<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser;

use Patchwork\Stream\Parser;

/**
 * The dumper parser echoes the parsed stream line by line for easier debugging.
 */
class Dumper extends Parser
{
    protected

    $width = 50,
    $callbacks = array('logLine' => T_STREAM_LINE);

    function __construct(parent $parent, $width = null)
    {
        parent::__construct($parent);
        isset($width) && $this->width = $width;
    }

    protected function logLine($line, $m, $t)
    {
        $line = strtr($line, "\r\n\t", '   ');
        $line = substr($line, 0, $this->width);

        echo sprintf("% 5d: % -{$this->width}s", $this->lineNumber, $line);

        unset($t[T_STREAM_LINE]);
        foreach ($t as $t) echo ' ', self::getTagName($t);

        echo "\n";
    }
}
