<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

/**
 * Stream Parser Mail, Catch and analyse received DSN
 *
 * This file recognize and tag the stream
 * It's build to tag lines of a basic or a mime mail
 * @todo Handle RFC2047 encoded headers
 * @todo Detect and warn for malformed messages
 * @todo Extract information about the final recipient from the DSN when the original recipient is forwarded. See for eg. X-Actual-Recipient.
 * @author Sebastien Lavallee
 * @version 1.0
 * @package Patchwork/Stream/Parser
 */

/**
 * Tag matching header line in a email
 */

Patchwork_Stream_Parser::createTag('T_MAIL_HEADER');
Patchwork_Stream_Parser::createTag('T_MAIL_BOUNDARY');
Patchwork_Stream_Parser::createTag('T_MAIL_BODY');
Patchwork_Stream_Parser::createTag('T_MAIL_MALFORMED');
Patchwork_Stream_Parser::createTag('T_MIME_BOUNDARY');
Patchwork_Stream_Parser::createTag('T_MIME_IGNORE');

/**
 * This page recognise a file as an email and tag the lines as header, body etc...
 * Following the rfc822 and mime messages
 */

class Patchwork_Stream_Parser_Mail extends Patchwork_Stream_Parser
{
    const

    TSPECIALS_822  = '()<>@,;:\\".[]',
    TSPECIALS_2045 = '()<>@,;:\\"/[]?=';


    protected

    $envelope,

    $mimePart = array(
        'type' => false,
        'index' => 0,
        'depth' => 0,
        'parent' => false,
        'encoding' => '7bit',
        'boundary' => false,
        'defaultType' => false,
        'boundarySelector' => array(),
    ),

    $type,
    $header,
    $bodyLine = '',
    $nextType = array(
        'top' => 'text/plain',
        'primary' => 'text',
        'secondary' => 'plain',
        'params' => array(),
    ),

    $callbacks = array(
        'catchHeader' => T_MAIL_HEADER,
        'registerType' => T_MAIL_BOUNDARY,
        'tagMailHeader' => T_STREAM_LINE,
    );


    /**
     * Constructor
     *
     * @param parent $parent
     *  Take the value of the parent class
     * @param string $sender
     *  Input string set a value for the sender(default is null)
     * @param string $recient
     *  Input string set a value for the recipient(default is null)
     * @param string $ip
     *  Input string set a value for the clientIp(default is null)
     * @param string $helo
     *  Input string set a value for the clientHelo(default is null)
     * @param string $hostname
     *  Input string set a value for the clientHostname(default is null)
     */

    function __construct(parent $parent, $sender = null, $recipient = null, $ip = null, $helo = null, $hostname = null)
    {
        $this->envelope = (object) array(
            'sender' => $sender,
            'recipient' => $recipient,
            'clientIp' => $ip,
            'clientHelo' => $helo,
            'clientHostname' => $hostname,
        );

        $this->mimePart = (object) $this->mimePart;
        $this->nextType = (object) $this->nextType;

        parent::__construct($parent);
    }

    /**
     * Get the envelope object. Contains informations about the email : sender, recipient, client Ip, client helo, and the client Hostname.
     *
     * @return object
     *  Object containing 'sender', 'recipient', 'clientIp', 'clientHelo', 'clientHostname'
     */

    function getEnvelope()
    {
        return clone $this->envelope;
    }

    /**
     * Keep information given by a Content-Type header line in $this->nextType variable (Indicate at any time the format of body used in this email).
     *
     * @param $topType
     *  First parameter given in the Content-Type header indicating what kind of body the parser is facing
     * @param array $params
     *  If params is given it will contain the other parameters of the Content-Type line
     */

    public function setNextType($topType, $params = array())
    {
        $t = explode('/', $topType, 2);
        $this->nextType = (object) array(
            'top' => $topType,
            'primary' => $t[0],
            'secondary' => $t[1],
            'params' => $params,
        );
    }

    /**
     * Tag header lines until a boundary line shows up, detect malformed header and regroup multiple line header.
     *
     * @param string &$line
     *  Input string to be analysed
     * @param array $m
     *  $m filled with the results of search in a preg_match() method. $m[0] has to contain the text that matched the full pattern, $m[1] will have the text that matched the first captured parenthesized subpattern, and so on
     * @param array $tags
     *  array of tags that are already assigned to this line
     * @return int
     *  Value of the tag for this line
     */

    protected function tagMailHeader(&$line, $m, $tags)
    {
        if (isset($tags[T_MIME_BOUNDARY])) return;

        if ("\n" === $line || "\r\n" === $line)
        {
            $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));
            return $this->tagMailBoundary($line, $m, $tags);
        }

        static $nextHeader = array();

        $nextHeader[] = $line;

        if (!isset($this->nextLine[0]) || !(' ' === $this->nextLine[0] || "\t" === $this->nextLine[0]))
        {
            $line = implode('', $nextHeader);
            $nextHeader = array();

            return preg_match('/^[\x21-\x39\x3B-\x7E]+:/', $line) ? T_MAIL_HEADER : T_MAIL_MALFORMED;
        }

        return false;
    }

    /**
     * Explode header lines separating the name and the value (results registered in $this->header), tokenize Content-Type Header line (register the result using setNextType) and detect the encoding of the mail using Content-transfer-encoding line
     *
     * @param string $line
     *  Input string to be analysed
     */

    protected function catchHeader($line)
    {
        $v = explode(':', $line, 2);

        $this->header = (object) array(
            'name'  => strtolower($v[0]),
            'value' => preg_replace('/[ \t\r\n]*[\r\n][ \t\r\n]*/', ' ', trim($v[1])),
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
        else if ('content-transfer-encoding' === $this->header->name)
        {
            $this->mimePart->encoding = strtolower($this->header->value);
        }
    }

    /**
     * Tag line as T_Mail_Boundary line (blank line after a header line)
     *
     * @param string $line
     *  Input string to be analysed
     * @return int
     *  Value of the tag for this line (T_MAIL_BOUNDARY)
     */

    protected function tagMailBoundary($line)
    {
        $this->register(array('tagMailBody' => T_STREAM_LINE));

        $this->type = $this->nextType;
        $this->nextType = false;

        if (false === $this->mimePart->type)
            $this->mimePart->type = $this->type;

        return T_MAIL_BOUNDARY;
    }

    /**
     * Activate methods depending on the current state of $this->type
     */

    protected function registerType()
    {
        switch (true)
        {
        case 'message' === $this->type->primary:
        case 'text/rfc822-headers' === $this->type->top:
            $this->registerRfc822Part();
            break;

        case 'multipart' === $this->type->primary && !empty($this->type->params['boundary']):
            $this->registerMimePart('digest' === $this->type->secondary ? 'message/rfc822' : 'text/plain');
            break;
        }
    }

    /**
     * Set new value for $this->nextType using setNextType() for a rfc822 message part
     *
     * @param string $nextTopType
     *  First parameter given in the Content-Type header indicating what kind of body the parser is facing (default value is 'text/plain')
     * @param array $nextTypeParams
     *  If nextTypeParams is given it will contain the other parameters of the Content-Type line (default value array())
     */

    public function registerRfc822Part($nextTopType = 'text/plain', $nextTypeParams = array())
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

    /**
     * Register parameters about a section of a mime mail
     *
     * @param string $defaultTopType
     *  First parameter given in the Content-Type header indicating what kind of body the parser is facing
     * @param array $defaultTopParams
     *  If $defaultTopParams is given it will contain the other parameters of the Content-Type line
     */

    public function registerMimePart($defaultTopType, $defaultTypeParams = array())
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
            'encoding' => '7bit',
            'boundary' => $this->type->params['boundary'],
            'defaultType' => $this->nextType,
            'boundarySelector' => $s,
        );

        $this->unregister(array('tagMailBody' => T_STREAM_LINE));
        $this->register(array('tagMimeBoundary' => $s));
        $this->register(array('tagMimeIgnore' => T_STREAM_LINE));
    }

    /**
     * Tag line as a T_MIME_BOUNDARY
     *
     * @param string $line
     *  Input string to be analysed
     * @param array $matches
     *  $matches filled with the results of search in a preg_match() method. $matches[0] has to contain the text that matched the full pattern, $matches[1] will have the text that matched the first captured parenthesized subpattern, and so on
     * @return int
     *  Value of the tag for this line (T_MIME_BOUNDARY)
     */

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

    /**
     * Tag line as a T_MIME_IGNORE
     *
     * @param string $line
     *  Input string to be analysed
     * @param array $matches
     *  $matches filled with the results of search in a preg_match() method. $m[0] has to contain the text that matched the full pattern, $m[1] will have the text that matched the first captured parenthesized subpattern, and so on
     * @param array $tags
     *  array of tags that are already assigned to this line
     * @return int
     *  Value of the tag for this line (T_MIME_IGNORE)
     */

    protected function tagMimeIgnore($line, $matches, $tags)
    {
        if (!isset($tags[T_MIME_BOUNDARY])) return T_MIME_IGNORE;
    }

    /**
     * Tag line as a T_MAIL_BODY and decode the line result is kept in $this->bodyLine
     *
     * @param string &$line
     *  Input string to be analysed
     * @param array $matches
     *  $matches filled with the results of search in a preg_match() method. $m[0] has to contain the text that matched the full pattern, $m[1] will have the text that matched the first captured parenthesized subpattern, and so on
     * @param array $tags
     *  array of tags that are already assigned to this line
     * @return int
     *  Value of the tag for this line (T_MAIL_BODY)
     */

    protected function tagMailBody($line, $matches, $tags)
    {
        if (!isset($tags[T_MIME_BOUNDARY]))
        {
            if ('quoted-printable' === $this->mimePart->encoding) $line = quoted_printable_decode($line);
            else if (     'base64' === $this->mimePart->encoding) $line = base64_decode($line);

            if (isset($this->type->params['charset']))
                $line = @iconv($this->type->params['charset'], 'UTF-8//IGNORE', $line);

            $this->bodyLine = $line;

            return T_MAIL_BODY;
        }
    }

    /**
     * Cut a string into tokens using specific delimiters given to the method
     *
     * @param string $header
     *  Input string to be tokenized
     * @param string $tspecial
     *  Designate the list of token considered as delimiter(default value TSPECIALS_822)
     * @return array
     *  Output array containing the tokens
     */

    static function tokenizeHeader($header, $tspecial = self::TSPECIALS_822)
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
