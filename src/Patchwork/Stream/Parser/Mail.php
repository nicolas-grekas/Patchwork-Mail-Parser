<?php
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

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
 * @todo Detect and warn for malformed messages
 */
class Mail extends Parser
{
    const TSPECIALS_822 = '()<>@,;:\\".[]';
    const TSPECIALS_2045 = '()<>@,;:\\"/[]?=';
    const EMAIL_RX = '/[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*/'; // From the HTML5 spec


    protected $envelope;

    protected $mimePart = array(
        'type' => false,
        'index' => 0,
        'depth' => 0,
        'parent' => false,
        'encoding' => '7bit',
        'boundary' => false,
        'defaultType' => false,
        'boundarySelector' => array(),
    );

    protected $type;
    protected $header;
    protected $bodyLine = '';
    protected $nextType = array(
        'top' => 'text/plain',
        'primary' => 'text',
        'secondary' => 'plain',
        'params' => array(),
    );

    protected $callbacks = array(
        'catchHeader' => T_MAIL_HEADER,
        'registerType' => T_MAIL_BOUNDARY,
        'tagMailHeader' => T_STREAM_LINE,
    );

    private $nextHeader = array();

    /**
     * Initializes the parser, and the mail envelope if already known.
     */
    public function __construct(parent $parent, $sender = null, $recipient = null, $ip = null, $helo = null, $hostname = null)
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
    public function getEnvelope()
    {
        return clone $this->envelope;
    }

    /**
     * Sets the content-type of the next mime body part.
     *
     * @param string $topType content-type of the next body part ('text/plain' e.g.)
     * @param array  $params  parameters of the content-type (['charset' => 'UTF-8'] e.g.)
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
     * For each MIME part, identifies logical mail headers and tags them as T_MAIL_HEADER.
     *
     * @param string &$line   input line of the stream
     * @param array  $matches not used
     * @param array  $tags    tags that are already assigned to this line
     *
     * @return int|false new tag for the line.
     */
    protected function tagMailHeader(&$line, $matches, $tags)
    {
        if (isset($tags[T_MIME_BOUNDARY])) {
            return;
        }

        if ("\n" === $line || "\r\n" === $line) {
            $this->unregister(array(__FUNCTION__ => T_STREAM_LINE));

            return $this->tagMailBoundary($line, $matches, $tags);
        }

        $this->nextHeader[] = $line;

        if (!isset($this->nextLine[0]) || !(' ' === $this->nextLine[0] || "\t" === $this->nextLine[0])) {
            $line = $l = implode('', $nextHeader);
            $this->nextHeader = array();

            return preg_match('/^[\x21-\x39\x3B-\x7E]+:/', $l) ? T_MAIL_HEADER : T_MAIL_MALFORMED;
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
            'name' => strtolower($v[0]),
            'value' => preg_replace('/[ \t\r\n]*[\r\n][ \t\r\n]*/', ' ', trim($v[1])),
        );

        switch ($this->header->name) {
            case 'content-type':
                $v = self::tokenizeHeader($this->header->value, self::TSPECIALS_2045);

                if (isset($v[2]) && '/' === $v[1]) {
                    $type = strtolower($v[0].$v[1].$v[2]);

                    $params = array();
                    $i = 2;
                    while (1) {
                        while (isset($v[++$i]) && ';' !== $v[$i]) {
                        }
                        if (!isset($v[$i + 3])) {
                            break;
                        }
                        if ('=' === $v[$i + 2]) {
                            $params[strtolower($v[$i + 1])] = $v[$i + 3];
                        }
                    }

                    $this->setNextType($type, $params);
                }
                break;

            case 'content-transfer-encoding':
                $this->mimePart->encoding = strtolower($this->header->value);
                break;

            case 'subject':
                $this->mimePart->subject = self::decodeHeader($this->header->value);
                if (0 === $this->mimePart->depth) {
                    $this->envelope->subject = $this->mimePart->subject;
                }
                break;
        }
    }

    /**
     * For each MIME part, tags lines between headers and body as T_MAIL_BOUNDARY.
     */
    protected function tagMailBoundary($line)
    {
        $this->register(array('tagMailBody' => T_STREAM_LINE));

        $this->type = $this->nextType;
        $this->nextType = false;

        if (false === $this->mimePart->type) {
            $this->mimePart->type = $this->type;
        }

        return T_MAIL_BOUNDARY;
    }

    /**
     * Sets parsing of the next body part according to its content-type.
     */
    protected function registerType()
    {
        switch (true) {
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
     * @param string $defaultTopType    default Content-Type of the encapsulated body part (defaults to 'text/plain')
     * @param array  $defaultTypeParams parameters for the default Content-Type
     */
    public function registerRfc822Part($defaultTopType = 'text/plain', $defaultTypeParams = array())
    {
        if (false !== $this->nextType) {
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
     * @param string $defaultTopType    default Content-Type of the MIME part
     * @param array  $defaultTypeParams parameters for the default Content-Type
     */
    public function registerMimePart($defaultTopType, $defaultTypeParams = array())
    {
        if (false !== $this->nextType) {
            $this->setError("Failed to set Mail->nextType to `{$defaultTopType}`: already set to `{$this->nextType->top}`", E_USER_WARNING);

            return;
        }

        if (empty($this->type->params['boundary'])) {
            $this->setError('No boundary defined for the current content-type');

            return;
        }

        $this->setNextType($defaultTopType, $defaultTypeParams);

        $s = array(T_STREAM_LINE => '/^--('.preg_quote($this->type->params['boundary'], '/').')(--)?/');
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
    protected function tagMimeBoundary($line, $p, $matches)
    {
        $this->unregister(array(
            'tagMailHeader' => T_STREAM_LINE,
            'tagMimeIgnore' => T_STREAM_LINE,
            'tagMailBody' => T_STREAM_LINE,
        ));

        $p = $this->mimePart;

        while ($p->boundary !== $matches[1]) {
            $this->unregister(array(__FUNCTION__ => $p->boundarySelector));
            $this->mimePart = $p = $p->parent;
        }

        if (empty($matches[2])) {
            ++$p->index;
            $p->type = false;
            $this->nextType = $p->defaultType;
            $this->register(array('tagMailHeader' => T_STREAM_LINE));
        } else {
            $this->unregister(array(__FUNCTION__ => $p->boundarySelector));
            $this->register(array('tagMimeIgnore' => T_STREAM_LINE));
            $this->mimePart = $p->parent;
        }

        return T_MIME_BOUNDARY;
    }

    /**
     * Tags lines between headers of a MIME part and its first opening boundary as T_MIME_IGNORE.
     */
    protected function tagMimeIgnore($line, $tags)
    {
        if (!isset($tags[T_MIME_BOUNDARY])) {
            return T_MIME_IGNORE;
        }
    }

    /**
     * Tags MIME parts body lines as T_MAIL_BODY.
     * The corresponding transfer-encoding decoded, UTF-8 converted string is exposed in $this->bodyLine.
     */
    protected function tagMailBody($line, $tags)
    {
        if (!isset($tags[T_MIME_BOUNDARY])) {
            if ('quoted-printable' === $this->mimePart->encoding) {
                $line = quoted_printable_decode($line);
            } elseif ('base64' === $this->mimePart->encoding) {
                $line = base64_decode($line);
            }

            if (isset($this->type->params['charset'])) {
                $line = @iconv(str_ireplace('unicode-1-1-utf-7', 'utf-7', $this->type->params['charset']), 'UTF-8//IGNORE', $line);
            }

            $this->bodyLine = $line;

            return T_MAIL_BODY;
        }
    }

    /**
     * Tokenizes a header string according to RFC822 section 3.
     */
    public static function tokenizeHeader($header, $tspecial = self::TSPECIALS_822)
    {
        $i = -1;
        $state = '-';
        $tokens = array();

        do {
            $token = '';

            while (isset($header[++$i])) {
                $c = $header[$i];

                if ('(' === $state) {
                    switch ($c) {
                        case '\\':
                            if (isset($header[++$i])) {
                                continue 2;
                            }
                            break 2;

                        case '(':
                            ++$level;
                            continue 2;

                        case ')':
                            if (0 === --$level) {
                                $state = '-';
                            }
                        default:
                            continue 2;
                    }
                }

                if ('"' === $state) {
                    switch ($c) {
                        case '"':
                            $state = '-';
                            break 2;

                        case "\n":
                            $token = rtrim($token, " \t\r\n");
                            continue 2;

                        case '\\':
                            if (isset($header[++$i])) {
                                $c = $header[$i];
                            } else {
                                break 2;
                            }
                        default:
                            $token .= $c;
                            continue 2;
                    }
                }

                switch ($c) {
                    case '(': $level = 1;
                    case '"': $state = $c;
                    case ' ':
                    case "\t":
                    case "\r":
                    case "\n":
                        if ('' !== $token) {
                            break 2;
                        }
                        continue 2;
                }

                if (' ' > $c || false !== strpos($tspecial, $c)) {
                    '' !== $token && $tokens[] = $token;
                    $tokens[] = $c;
                    continue 2;
                } else {
                    $token .= $c;
                }
            }

            '' !== $token && $tokens[] = $token;
        } while (isset($header[$i]));

        return $tokens;
    }

    /**
     * Decodes a string according to RFC2047.
     *
     * @return string|false returns a decoded MIME field on success, or false if an error occurs.
     */
    public static function decodeHeader($header)
    {
        $header = str_ireplace('=?unicode-1-1-utf-7?', '=?utf-7?', $header);

        return iconv_mime_decode($header, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8//IGNORE');
    }

    /**
     * Parses email addresses according to RFC822.
     *
     * @return array
     */
    public static function parseAddresses($header, $email_rx = self::EMAIL_RX)
    {
        $t = self::tokenizeHeader($header);
        $group = false;
        $emails = array();

        for ($i = 0; isset($t[$i]); ++$i) {
            $state = 1;

            $e = array(
                'display' => '',
                'address' => '',
                'group' => $group,
            );

            for (; isset($t[$i]); ++$i) {
                switch ($t[$i]) {
                    case '<':
                        $e['address'] = '';
                        $state = 2;
                        continue 2;

                    case '>':
                        $state = 3;
                        continue 2;

                    case ',':
                        break 2;

                    case ':':
                        if (false === $group && 1 === $state) {
                            if ($e['display']) {
                                $e['display'] = self::decodeHeader(substr($e['display'], 1));
                            }
                            $group = $e['group'] = $e['display'] ? $e['display'] : true;
                            $e['display'] = $e['address'] = '';
                            continue 2;
                        }
                        break;

                    case ';':
                        if (false !== $group) {
                            $group = false;
                            continue 2;
                        }
                        break;
                }

                switch ($state) {
                    case 1: $e['display'] .= ' '.$t[$i];
                    case 2: $e['address'] .= $t[$i];
                }
            }

            if ('' === $e['address'] || preg_match($email_rx, $e['address'])) {
                if (1 === $state) {
                    $e['display'] = '';
                } elseif ($e['display']) {
                    $e['display'] = self::decodeHeader(substr($e['display'], 1));
                }
                $emails[] = $e;
            }
        }

        return $emails;
    }
}
