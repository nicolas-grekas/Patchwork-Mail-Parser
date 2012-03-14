<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

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
