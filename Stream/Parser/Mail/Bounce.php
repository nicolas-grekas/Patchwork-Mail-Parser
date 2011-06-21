<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

Stream_Parser::createTag('T_BOUNCE_EXCLUSIVITY');

class Stream_Parser_Mail_Bounce extends Stream_Parser
{
    protected

    $bounceDatas = array(),
    $hasExclusivity = false;


    function __construct(parent $parent)
    {
        parent::__construct($parent);
        $this->register(array('catchBounceExclusivity' => T_BOUNCE_EXCLUSIVITY));
    }

    function getBouncesDatas()
    {
        return $this->bounceDatas;
    }

    protected function getExclusivity()
    {
        $this->hasExclusivity = true;
        return T_BOUNCE_EXCLUSIVITY;
    }

    protected function catchBounceExclusivity()
    {
        $this->hasExclusivity || $this->unregisterAll();
    }
}
