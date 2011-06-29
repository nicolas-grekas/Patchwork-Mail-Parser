<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

Stream_Parser::createTag('T_BOUNCE_EXCLUSIVITY');

class Stream_Parser_Mail_Bounce extends Stream_Parser
{
    protected

    $bounceClass = 'dsn',
    $bounceReports = array(),
    $hasExclusivity = false;


    function __construct(parent $parent)
    {
        parent::__construct($parent);
        $this->register(array('catchBounceExclusivity' => T_BOUNCE_EXCLUSIVITY));

        if (__CLASS__ !== $c = get_class($this))
        {
            isset($this->dependencies['Mail_Bounce']->bounceReports)
                ? $this->bounceReports =& $this->dependencies['Mail_Bounce']->bounceReports[$this->bounceClass][$c]
                : user_error('Mail_Bounce dependency is not loaded');
        }
    }

    function getBounceReports()
    {
        return $this->bounceReports;
    }

    protected function getExclusivity()
    {
        $this->hasExclusivity = true;
        is_array($this->bounceReports) || $this->bounceReports = array();
        return T_BOUNCE_EXCLUSIVITY;
    }

    protected function catchBounceExclusivity()
    {
        $this->hasExclusivity || $this->unregisterAll();
    }

    protected function reportBounce($recipient, $reason)
    {
        $this->bounceReports[$recipient] = $reason;
    }
}
