<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

Stream_Parser::createTag('T_LOGICAL_HEADER');

class Stream_Parser_Mail extends Stream_Parser
{
    protected

    $logicalHeader,

    $envelopeSender,
    $envelopeRecipient,
    $envelopeClientIp,
    $envelopeClientHelo,
    $envelopeClientHostname,

    $callbacks = array('tagLogicalHeader' => T_STREAM_LINE);


    function __construct(parent $parent, $sender = null, $recipient = null, $ip = null, $helo = null, $hostname = null)
    {
        $this->envelopeSender         = $sender;
        $this->envelopeRecipient      = $recipient;
        $this->envelopeClientIp       = $ip;
        $this->envelopeClientHelo     = $helo;
        $this->envelopeClientHostname = $hostname;

        parent::__construct($parent);
    }

    protected function tagLogicalHeader($line)
    {
        static $nextLogicalHeader = '';

        "\n" === substr($line, -1) && $line = substr($line, 0, -1);
        "\r" === substr($line, -1) && $line = substr($line, 0, -1);

        $nextLogicalHeader .= $line;

        if (!isset($this->nextLine[0]) || !(' ' === $this->nextLine[0] || "\t" === $this->nextLine[0]))
        {
            $this->logicalHeader = $nextLogicalHeader;
            $nextLogicalHeader = '';

            if ("\n" === $this->nextLine || "\r\n" === $this->nextLine)
                $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));

            return T_LOGICAL_HEADER;
        }
    }
}
