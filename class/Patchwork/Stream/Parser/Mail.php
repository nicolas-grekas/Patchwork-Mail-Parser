<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser;

use Patchwork\Stream\Parser;

Parser::createTag('T_MAIL_HEADER');
Parser::createTag('T_MAIL_BOUNDARY');
Parser::createTag('T_MAIL_BODY');
Parser::createTag('T_MAIL_MALFORMED');
Parser::createTag('T_MIME_BOUNDARY');
Parser::createTag('T_MIME_IGNORE');

/**
 * The Mail parser tags lines of an RFC822 message and
 * exposes its MIME structure to other dependent parsers.
 *
 * @todo Handle RFC2047 encoded headers
 * @todo Detect and warn for malformed messages
 */
class Mail extends Parser
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
     * Initializes the parser, and the mail envelope if already known.
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
     * Returns the envelope of the message.
     *
     * @return stdClass containing: sender, recipient, client IP, helo and hostname.
     */
    function getEnvelope()
    {
        return clone $this->envelope;
    }

    /**
     * Sets the content-type of the next mime body part.
     *
     * @param string $topType content-type of the next body part ('text/plain' e.g.)
     * @param array $params parameters of the content-type (['charset' => 'UTF-8'] e.g.)
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
     * For each MIME part, identifies logical mail headers and tags them as T_MAIL_HEADER
     *
     * @param string &$line input line of the stream
     * @param array $matches not used
     * @param array $tags tags that are already assigned to this line
     * @return int|false new tag for the line.
     */

    protected function tagMailHeader(&$line, $matches, $tags)
    {
        if (isset($tags[T_MIME_BOUNDARY])) return;

        if ("\n" === $line || "\r\n" === $line)
        {
            $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));
            return $this->tagMailBoundary($line, $matches, $tags);
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
     * For each MIME part, extracts headers name and value and exposes them one at a time in $this->header.
     * Content-Type headers are parsed according to RFC2045,
     * transfert encoding of the body part is extracted from Content-Transfer-Encoding.
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
     * For each MIME part, tags lines between headers and body as T_MAIL_BOUNDARY
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
     * Sets parsing of the next body part according to its content-type.
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
     * Sets parsing of the next body part to an encapsulated RFC822
     * message whose default Content-Type is given as arguments.
     *
     * @param string $defaultTopType default Content-Type of the encapsulated body part (defaults to 'text/plain')
     * @param array $defaultTypeParams parameters for the default Content-Type
     */
    public function registerRfc822Part($defaultTopType = 'text/plain', $defaultTypeParams = array())
    {
        if (false !== $this->nextType)
        {
            $this->setError("Failed to set Mail->nextType to `{$defaultTopType}`: already set to `{$this->nextType->top}`", E_USER_WARNING);
            return;
        }

        $this->setNextType($defaultTopType, $defaultTypeParams);

        $this->unregister(array('tagMailBody' => T_STREAM_LINE));
        $this->register(array('tagMailHeader' => T_STREAM_LINE));
    }

    /**
     * Sets parsing of the next part to a MIME one.
     *
     * @param string $defaultTopType default Content-Type of the MIME part
     * @param array $defaultTypeParams parameters for the default Content-Type
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
     * Tags lines matching MIME boundaries as T_MIME_BOUNDARY.
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
     * Tags lines between headers of a MIME part and its first opening boundary as T_MIME_IGNORE.
     */
    protected function tagMimeIgnore($line, $matches, $tags)
    {
        if (!isset($tags[T_MIME_BOUNDARY])) return T_MIME_IGNORE;
    }

    /**
     * Tags MIME parts body lines as T_MAIL_BODY.
     * The corresponding transfer-encoding decoded, UTF-8 converted string is exposed in $this->bodyLine.
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
     * Tokenizes a header string according to RFC822 section 3.
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
