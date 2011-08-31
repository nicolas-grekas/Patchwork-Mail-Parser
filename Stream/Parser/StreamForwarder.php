<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

/**
 * Stream Parser Log, Catch and analyse received DSN
 *
 * This file forward the stream to sendmail
 * @author Sebastien Lavallee
 * @version 1.0
 * @package Stream/Parser
 */

/**
 * This page get the stream to forward it to an other process' stdin
 */

class Stream_Parser_StreamForwarder extends Stream_Parser
{
    protected

    $stream,
    $callbacks = array('writeLine' => T_STREAM_LINE);


    function __construct(parent $parent, $stream)
    {
        parent::__construct($parent);
        $this->stream = $stream;
    }

    /**
     *
     *
     * @param string $line
     *  Input string to be written
     */

    protected function writeLine($line)
    {
        fwrite($this->stream, $line);
    }
}
