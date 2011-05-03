<?php // vi: set encoding=utf-8 expandtab shiftwidth=4 tabstop=4:

class Stream_Parser_Echo extends Stream_Parser
{
    protected $callbacks = array('echoLine');

    protected function echoLine($line)
    {
        echo $line;
    }
}
