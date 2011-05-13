<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

Stream_Parser::createTag('T_MAIL_HEADER');
Stream_Parser::createTag('T_MAIL_BOUNDARY');
Stream_Parser::createTag('T_MAIL_BODY');

class Stream_Parser_Mail extends Stream_Parser
{
    protected

    $envelopeSender,
    $envelopeRecipient,
    $envelopeClientIp,
    $envelopeClientHelo,
    $envelopeClientHostname,

    $callbacks = array('tagHeader' => T_STREAM_LINE);


    function __construct(parent $parent, $sender = null, $recipient = null, $ip = null, $helo = null, $hostname = null)
    {
        $this->envelopeSender         = $sender;
        $this->envelopeRecipient      = $recipient;
        $this->envelopeClientIp       = $ip;
        $this->envelopeClientHelo     = $helo;
        $this->envelopeClientHostname = $hostname;

        parent::__construct($parent);
    }

    protected function tagHeader(&$line)
    {
        static $nextHeader = array();

        $nextHeader[] = $line;

        if (!isset($this->nextLine[0]) || !(' ' === $this->nextLine[0] || "\t" === $this->nextLine[0]))
        {
            $line = implode('', $nextHeader);
            $nextHeader = array();

            if ("\n" === $this->nextLine || "\r\n" === $this->nextLine)
            {
                $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));
                $this->register(array('tagBoundary' => T_STREAM_LINE));
            }

            return T_MAIL_HEADER;
        }

        return false;
    }

    protected function tagBoundary($line)
    {
        $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));
        $this->register(array('tagBody' => T_STREAM_LINE));
        return T_MAIL_BOUNDARY;
    }

    protected function tagBody($line)
    {
        return T_MAIL_BODY;
    }
}
