<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

// TODO:
// - detect and warn for malformed messages
// - expose nested content-type structure
// - parse headers
// - Content-Transfert-Encoding

Stream_Parser::createTag('T_MAIL_HEADER');
Stream_Parser::createTag('T_MAIL_BOUNDARY');
Stream_Parser::createTag('T_MAIL_BODY');
Stream_Parser::createTag('T_MIME_BOUNDARY');
Stream_Parser::createTag('T_MIME_IGNORE');

class Stream_Parser_Mail extends Stream_Parser
{
    protected

    $envelopeSender,
    $envelopeRecipient,
    $envelopeClientIp,
    $envelopeClientHelo,
    $envelopeClientHostname,

    $mimePart = false,
    $contentType = '',

    $callbacks = array(
        'tagMailHeader'      => T_STREAM_LINE,
        'extractContentType' => array(T_MAIL_HEADER => '/^Content-Type:\s+(.*)/si'),
    );


    function __construct(parent $parent, $sender = null, $recipient = null, $ip = null, $helo = null, $hostname = null)
    {
        $this->envelopeSender         = $sender;
        $this->envelopeRecipient      = $recipient;
        $this->envelopeClientIp       = $ip;
        $this->envelopeClientHelo     = $helo;
        $this->envelopeClientHostname = $hostname;

        parent::__construct($parent);
    }

    protected function tagMailHeader(&$line)
    {
        if (isset($tags[T_MIME_BOUNDARY])) return T_MAIL_BOUNDARY;

        static $nextHeader = array();

        $nextHeader[] = $line;

        if (!isset($this->nextLine[0]) || !(' ' === $this->nextLine[0] || "\t" === $this->nextLine[0]))
        {
            $line = implode('', $nextHeader);
            $nextHeader = array();

            if ("\n" === $this->nextLine || "\r\n" === $this->nextLine)
            {
                $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));
                $this->register(array('tagMailBoundary' => T_STREAM_LINE));
            }

            return T_MAIL_HEADER;
        }

        return false;
    }

    protected function extractContentType($line, $matches)
    {
        $this->contentType = $matches[1];
    }

    protected function tagMailBoundary($line)
    {
        $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));

        switch (true)
        {
        case preg_match('/^multipart\/.*;\s*boundary=("?)(.*)\1/si', $this->contentType, $m):
            $s = array(T_STREAM_LINE => '/^--(' . preg_quote($m[2], '/') . ')(--)?/');
            $this->mimePart = (object) array(
                'parent'   => $this->mimePart,
                'depth'    => $this->mimePart ? $this->mimePart->depth + 1 : 1,
                'index'    => 0,
                'boundary' => $m[2],
                'boundarySelector' => $s,
            );

            $this->register(array('tagMimeBoundary' => $s));
            $this->register(array('tagMimeIgnore' => T_STREAM_LINE));
            break;

        case preg_match('/^(message\/rfc822|text\/rfc822-headers)/', $this->contentType):
            $this->register(array('tagMailHeader' => T_STREAM_LINE));
            break;

        default:
            $this->register(array('tagMailBody' => T_STREAM_LINE));
            break;
        }

        $this->contentType = '';

        return T_MAIL_BOUNDARY;
    }

    protected function tagMimeBoundary($line, $matches)
    {
        $this->unregister(array(
            'tagMailHeader' => T_STREAM_LINE,
            'tagMimeIgnore' => T_STREAM_LINE,
            'tagMailBody'   => T_STREAM_LINE,
        ));

        $p = $this->mimePart;

        while ($p->boundary !== $matches[1])
        {
            $this->unregister(array(__FUNCTION__ => $p->boundarySelector));
            $this->mimePart = $p = $p->parent;
        }

        if (empty($matches[2]))
        {
            ++$p->index;
            $this->register(array('tagMailHeader' => T_STREAM_LINE));
        }
        else
        {
            $this->unregister(array(__FUNCTION__ => $p->boundarySelector));
            $this->register(array('tagMimeIgnore' => T_STREAM_LINE));
            $this->mimePart = $p->parent;
        }

        return T_MIME_BOUNDARY;
    }

    protected function tagMimeIgnore($line, $matches, $tags)
    {
        if (!isset($tags[T_MIME_BOUNDARY])) return T_MIME_IGNORE;
    }

    protected function tagMailBody($line, $matches, $tags)
    {
        if (!isset($tags[T_MIME_BOUNDARY])) return T_MAIL_BODY;
    }
}
