<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Bounce_Autoreply extends Stream_Parser_Mail_Bounce
{
    protected

    $bounceClass = 'vacation',
    $callbacks = array(
        'catchAutoReply' => array(T_MAIL_HEADER => '/vacation/i'),
    ),
    $dependencies = array(
        'Mail_Bounce',
        'Mail' => array('header'),
    );


    protected function catchAutoReply($line)
    {
        if ('auto-submitted' === $this->header->name)
        {
            return $this->getExclusivity();
        }
    }
}
