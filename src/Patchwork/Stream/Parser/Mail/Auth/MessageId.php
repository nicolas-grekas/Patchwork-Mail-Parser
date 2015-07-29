<?php
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Stream\Parser\Mail\Auth;

use Patchwork\Stream\Parser;
use Patchwork\Stream\Parser\Mail\Auth;

/**
 * The MessageId parser works on bounce messages by extracting the
 * message-id of the original message from the body of the bounce,
 * then running a callback for comparing this message-id with some
 * database.
 *
 * The authentication class that results from this process means that
 * the bounce can be taken for serious because it mentions a message-id
 * that has been used in a previously sent message.
 *
 * As neither the original recipient nor the original sender are considered,
 * no guarantee can be made concerning the validity the recipient or sender
 * mentionned in the bounce.
 */
class MessageId extends Auth
{
    protected $authClass = 'message-id';
    protected $messageId = false;
    protected $messageIdExistsCallback;
    protected $callbacks = array('catchMailType' => T_MAIL_BOUNDARY);
    protected $header;
    protected $mimePart;
    protected $bodyLine;
    protected $dependencies = array(
        'Mail\Auth',
        'Mail' => array('header', 'mimePart', 'bodyLine'),
    );

    public function __construct(Parser $parent, $message_id_exists_callback)
    {
        parent::__construct($parent);
        $this->messageIdExistsCallback = $message_id_exists_callback;
    }

    protected function catchMailType($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_BOUNDARY));

        $this->mimePart->depth
            ? $this->register(array('catchMimeId' => T_MAIL_HEADER))
            : $this->register(array('catchMessage' => T_MAIL_BODY));

        $this->reportAuth(false);
    }

    protected function catchMessage($line)
    {
        if (0 === strncmp(ltrim($this->bodyLine), '---', 3)) {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
            $this->register(array('catchMessageId' => T_MAIL_BODY));
        }
    }

    protected function catchMessageId($line)
    {
        if (0 === strncasecmp($this->bodyLine, 'Message-Id:', 11) && preg_match('/^Message-Id:\s+<(.*)>/i', $this->bodyLine, $m)) {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
            $this->reportMessageId($m[1]);
        }
    }

    protected function catchMimeId($line)
    {
        if ('message-id' === $this->header->name) {
            $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));
            $this->reportMessageId(trim($this->header->value, '><'));
        }
    }

    protected function reportMessageId($message_id)
    {
        $this->messageId = $message_id;

        if ($this->messageIdExistsCallback) {
            $this->reportAuth((int) (bool) call_user_func($this->messageIdExistsCallback, $message_id));
        }
    }

    public function getMessageId()
    {
        return $this->messageId;
    }
}
