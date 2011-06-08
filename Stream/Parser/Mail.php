<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

// TODO:
// - Handle Content-Transfert-Encoding on T_MAIL_BODY
// - Detect and warn for malformed messages

Stream_Parser::createTag('T_MAIL_HEADER');
Stream_Parser::createTag('T_MAIL_BOUNDARY');
Stream_Parser::createTag('T_MAIL_BODY');
Stream_Parser::createTag('T_MIME_BOUNDARY');
Stream_Parser::createTag('T_MIME_IGNORE');

class Stream_Parser_Mail extends Stream_Parser
{
    const

    TSPECIALS_822  = "()<>@,;:\\\".[]",
    TSPECIALS_2045 = "()<>@,;:\\\"/[]?=";


    protected

    $envelopeSender,
    $envelopeRecipient,
    $envelopeClientIp,
    $envelopeClientHelo,
    $envelopeClientHostname,

    $mimePart = array(
        'type' => false,
        'index' => 0,
        'depth' => 0,
        'parent' => false,
        'boundary' => false,
        'defaultType' => false,
        'boundarySelector' => array(),
    ),

    $type,
    $header,
    $nextType = array(
        'top' => 'text/plain',
        'primary' => 'text',
        'secondary' => 'plain',
        'params' => array(),
    ),

    $callbacks = array(
        'tagMailHeader' => T_STREAM_LINE,
        'catchHeader'   => T_MAIL_HEADER,
        'registerType'  => T_MAIL_BOUNDARY,
    );


    function __construct(parent $parent, $sender = null, $recipient = null, $ip = null, $helo = null, $hostname = null)
    {
        $this->envelopeSender         = $sender;
        $this->envelopeRecipient      = $recipient;
        $this->envelopeClientIp       = $ip;
        $this->envelopeClientHelo     = $helo;
        $this->envelopeClientHostname = $hostname;

        $this->mimePart = (object) $this->mimePart;
        $this->nextType = (object) $this->nextType;

        parent::__construct($parent);
    }

    protected function setNextType($topType, $params = array())
    {
        $t = explode('/', $topType, 2);
        $this->nextType = (object) array(
            'top' => $topType,
            'primary' => $t[0],
            'secondary' => $t[1],
            'params' => $params,
        );
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

    protected function catchHeader($line)
    {
        $v = explode(':', $line, 2);

        $this->header = (object) array(
            'name'  => strtolower($v[0]),
            'value' => preg_replace("/[ \t\r\n]*[\r\n]/", '', trim($v[1])),
        );

        if ('content-type' === $this->header->name)
        {
            $v = self::tokenizeHeader($this->header->value, self::TSPECIALS_2045);

            if (isset($v[2]) && '/' === $v[1])
            {
                $type = strtolower($v[0] . $v[1] . $v[2]);

                $params = array();
                $i = 2;
                while (1)
                {
                    while (isset($v[++$i]) && ';' !== $v[$i]) {}
                    if (!isset($v[$i+3])) break;
                    if ('=' === $v[$i+2]) $params[strtolower($v[$i+1])] = $v[$i+3];
                }

                $this->setNextType($type, $params);
            }
        }
    }

    protected function tagMailBoundary($line)
    {
        $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));
        $this->register(array('tagMailBody' => T_STREAM_LINE));

        $this->type = $this->nextType;
        $this->nextType = false;

        if (false === $this->mimePart->type)
            $this->mimePart->type = $this->type;

        return T_MAIL_BOUNDARY;
    }

    protected function registerType()
    {
        switch (true)
        {
        case 'message' === $this->type->primary:
        case 'text/rfc822-headers' === $this->type->top:
            $this->registerRfc822Part();
            break;

        case 'multipart' === $this->type->primary && !empty($this->type->params['boundary']):
            $this->registerMimePart('digest' === $this->type->secondary ? 'text/plain' : 'message/rfc822');
            break;
        }
    }

    protected function registerRfc822Part($nextTopType = 'text/plain', $nextTypeParams = array())
    {
        if (false !== $this->nextType)
        {
            $this->setError("Failed to set Mail->nextType to `{$nextTopType}`: already set to `{$this->nextType->top}`", E_USER_WARNING);
            return;
        }

        $this->setNextType($nextTopType, $nextTypeParams);

        $this->unregister(array('tagMailBody' => T_STREAM_LINE));
        $this->register(array('tagMailHeader' => T_STREAM_LINE));
    }

    protected function registerMimePart($defaultTopType, $defaultTypeParams = array())
    {
        if (false !== $this->nextType)
        {
            $this->setError("Failed to set Mail->nextType to `{$defaultTopType}`: already set to `{$this->nextType->top}`", E_USER_WARNING);
            return;
        }

        if (empty($this->type->params['boundary']))
        {
            $this->setError("No boundary defined for the current content-type");
            return;
        }

        $this->setNextType($defaultTopType, $defaultTypeParams);

        $s = array(T_STREAM_LINE => '/^--(' . preg_quote($this->type->params['boundary'], '/') . ')(--)?/');
        $this->mimePart = (object) array(
            'type' => false,
            'index' => 0,
            'depth' => $this->mimePart->depth + 1,
            'parent' => $this->mimePart,
            'boundary' => $this->type->params['boundary'],
            'defaultType' => $this->nextType,
            'boundarySelector' => $s,
        );

        $this->unregister(array('tagMailBody' => T_STREAM_LINE));
        $this->register(array('tagMimeBoundary' => $s));
        $this->register(array('tagMimeIgnore' => T_STREAM_LINE));
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
            $p->type = false;
            $this->nextType = $p->defaultType;
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

    Static function tokenizeHeader($header, $tspecial = self::TSPECIALS_822)
    {
        $i = -1;
        $state = '-';
        $tokens = array();

        do
        {
            $token = '';

            while (isset($header[++$i]))
            {
                $c = $header[$i];

                if ('(' === $state) switch ($c)
                {
                case '\\': if (isset($header[++$i])) continue 2; break 2;
                case '(' : ++$level; continue 2;
                case ')' : if (0 === --$level) $state = '-';
                default  : continue 2;
                }

                if ('"' === $state) switch ($c)
                {
                case '"' : $state = '-'; break 2;
                case "\n": $token = rtrim($token, " \t\r\n"); continue 2;
                case '\\': if (isset($header[++$i])) $c = $header[$i];
                           else break 2;
                default  : $token .= $c; continue 2;
                }

                switch ($c)
                {
                case '(' : $level = 1;
                case '"' : $state = $c;
                case ' ' : case "\t": case "\r": case "\n":
                           if ('' !== $token) break 2;
                           continue 2;
                }

                if (' ' > $c || false !== strpos($tspecial, $c))
                {
                    '' !== $token && $tokens[] = $token;
                    $tokens[] = $c;
                    continue 2;
                }
                else $token .= $c;
            }

            '' !== $token && $tokens[] = $token;
        }
        while (isset($header[$i]));

        return $tokens;
    }
}
