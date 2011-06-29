<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Bounce_Qmail extends Stream_Parser_Mail_Bounce
{
    protected

    $recipient,
    $callbacks = array(
        'extractRecipient' => array('/^<(.*?@.*?)>/' => T_MAIL_BODY),
    ),
    $dependencies = array(
        'Mail_Bounce',
    );


    protected function extractRecipient($line, $matches)
    {
        $this->unregister(array(__FUNCTION__ => array('/^<(.*?@.*?)>/' => T_MAIL_BODY)));
        $this->register(array('extractStatus' => T_MAIL_BODY));
        $this->recipient = $matches[1];
    }

    protected function extractStatus($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_BODY));
        $this->register(array('extractRecipient' => array('/^<(.*?@.*?)>/' => T_MAIL_BODY)));
        $this->reportBounce($this->recipient, trim($line));
    }
}

