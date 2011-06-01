<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

// TODO:
// - detect and warn for malformed messages
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

    $mimePart = array(
        'parent'   => false,
        'depth'    => 0,
        'index'    => 0,
        'boundary' => false,
        'contentType' => 'message/rfc822',
        'boundarySelector' => array(),
        'defaultContentType' => false,
    ),

    $contentType = 'message/rfc822',
    $nextContentType = 'text/plain',

    $callbacks = array(
        'tagMailHeader'       => T_STREAM_LINE,
        'extractContentType'  => array(T_MAIL_HEADER => '/^Content-Type:\s+(.*)/si'),
        'registerContentType' => T_MAIL_BOUNDARY,
    );


    function __construct(parent $parent, $sender = null, $recipient = null, $ip = null, $helo = null, $hostname = null)
    {
        $this->envelopeSender         = $sender;
        $this->envelopeRecipient      = $recipient;
        $this->envelopeClientIp       = $ip;
        $this->envelopeClientHelo     = $helo;
        $this->envelopeClientHostname = $hostname;

        $this->mimePart = (object) $this->mimePart;

        parent::__construct($parent);
    }

    protected function tagMailHeader(&$line,$m, $tags)
    {
        if (isset($tags[T_MIME_BOUNDARY])) return;

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
        $this->nextContentType = $matches[1];
    }

    protected function tagMailBoundary($line)
    {
        $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));
        $this->register(array('tagMailBody' => T_STREAM_LINE));

        $this->contentType = $this->nextContentType;
        $this->nextContentType = false;

        if (false === $this->mimePart->contentType)
            $this->mimePart->contentType = $this->contentType;

        return T_MAIL_BOUNDARY;
    }

    protected function registerContentType()
    {
        switch (true)
        {
        case preg_match('/^(message\/|text\/rfc822-headers)/', $this->contentType):
            $this->registerRfc822Part();
            break;

        case preg_match('/^multipart\/(.*?);.*boundary=("?)(.*)\2/si', $this->contentType, $m):
            $this->registerMimePart($m[3], strcasecmp('digest', $m[1]) ? 'text/plain' : 'message/rfc822');
            break;
        }
    }

    protected function registerRfc822Part($nextContentType = 'text/plain')
    {
        if (false !== $this->nextContentType)
        {
            $this->setError("Failed to set Mail->nextContentType to `{$nextContentType}`: already set to `{$this->nextContentType}`", E_USER_WARNING);
            return;
        }

        $this->unregister(array('tagMailBody' => T_STREAM_LINE));
        $this->register(array('tagMailHeader' => T_STREAM_LINE));

        $this->nextContentType = $nextContentType;
    }

    protected function registerMimePart($boundary, $defaultContentType)
    {
        if (false !== $this->nextContentType)
        {
            $this->setError("Failed to set Mail->nextContentType to `{$nextContentType}`: already set to `{$this->nextContentType}`", E_USER_WARNING);
            return;
        }

        $this->unregister(array('tagMailBody' => T_STREAM_LINE));

        $s = array(T_STREAM_LINE => '/^--(' . preg_quote($boundary, '/') . ')(--)?/');
        $this->mimePart = (object) array(
            'parent'   => $this->mimePart,
            'depth'    => $this->mimePart->depth + 1,
            'index'    => 0,
            'boundary' => $boundary,
            'contentType' => false,
            'boundarySelector' => $s,
            'defaultContentType' => $defaultContentType,
        );

        $this->register(array('tagMimeBoundary' => $s));
        $this->register(array('tagMimeIgnore' => T_STREAM_LINE));

        $this->nextContentType = $defaultContentType;
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
            $p->contentType = false;
            $this->nextContentType = $p->defaultContentType;
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
