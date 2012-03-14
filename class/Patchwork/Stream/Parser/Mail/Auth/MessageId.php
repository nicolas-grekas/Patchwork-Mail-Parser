<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

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
 * that must be unforgeable.
 *
 * As neither the original recipient nor the original sender are considered,
 * no guarantee can be made concerning the validity the recipient or sender
 * mentionned in the bounce.
 */
class MessageId extends Auth
{
    protected

    $authClass = 'message-id',
    $messageId = false,
    $messageIdExistsCallback,
    $callbacks = array('catchMailType' => T_MAIL_BOUNDARY),
    $dependencies = array(
        'Mail\Auth',
        'Mail' => array('header', 'mimePart', 'bodyLine'),
    );


    function __construct(Parser $parent, $message_id_exists_callback)
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
        if (0 === strncmp($this->bodyLine, '---', 3))
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
            $this->register(array('catchMessageId' => T_MAIL_BODY));
        }
    }

    protected function catchMessageId($line)
    {
        if ( 0 === strncasecmp($this->bodyLine, 'Message-Id:', 11)
          && preg_match('/^Message-Id:\s+<(.*)>/i', $this->bodyLine, $m) )
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
            $this->reportMessageId($m[1]);
        }
    }

    protected function catchMimeId($line)
    {
        if ('message-id' === $this->header->name)
        {
            $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));
            $this->reportMessageId(trim($this->header->value, '><'));
        }
    }

    protected function reportMessageId($message_id)
    {
        $this->messageId = $message_id;

        if ($this->messageIdExistsCallback)
        {
            $this->reportAuth((int) (bool) call_user_func($this->messageIdExistsCallback, $message_id));
        }
    }

    function getMessageId()
    {
        return $this->messageId;
    }
}
