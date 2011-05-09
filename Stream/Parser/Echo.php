<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Echo extends Stream_Parser
{
    protected $callbacks = array('echoLine');

    protected function echoLine($line)
    {
        echo $line;
    }
}
