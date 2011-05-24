<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Log extends Stream_Parser
{
    protected $callbacks = array('logLine' => T_STREAM_LINE);

    protected function logLine($line, $m, $t)
    {
        echo sprintf('% 3d', $this->lineNumber), ': ',
            str_pad(substr(rtrim(strtr($line, "\r\n\t", '   ')), 0, 50), 50);

        unset($t[T_STREAM_LINE]);
        foreach ($t as $t) echo ' ', self::getTagName($t);

        echo "\n";
    }
}
