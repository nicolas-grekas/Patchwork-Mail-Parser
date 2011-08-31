<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Auth_MessageId extends Stream_Parser_Mail_Auth
{
    protected

    $authClass = 'message-id',
    $messageId = '',
    $callbacks = array('catchMailType' => T_MAIL_BOUNDARY),
    $dependencies = array(
        'Mail_Auth',
        'Mail' => array('header', 'mimePart', 'envelope', 'bodyLine'),
    );


    function __construct(Stream_Parser $parent, $message_id_counter)
    {
        parent::__construct($parent);
        $this->messageIdCounter = $message_id_counter;
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
        $this->reportAuth((int) call_user_func($this->messageIdCounter, $message_id, $this->envelope));
    }

    function getMessageId()
    {
        return $this->messageId;
    }
}
