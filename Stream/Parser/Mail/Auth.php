<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Auth extends Stream_Parser
{
    protected

    $authClass = false,
    $authenticationResults = array();


    function __construct(parent $parent)
    {
        parent::__construct($parent);

        if (__CLASS__ !== get_class($this))
        {
            isset($this->dependencies['Mail_Auth']->authenticationResults)
                ? $this->authenticationResults =& $this->dependencies['Mail_Auth']->authenticationResults[$this->authClass]
                : user_error('Mail_Auth dependency is not loaded');
        }
    }

    function getAuthenticationResults()
    {
        return $this->authenticationResults;
    }

    protected function reportAuth($result)
    {
        $this->authenticationResults = $result;
    }
}
