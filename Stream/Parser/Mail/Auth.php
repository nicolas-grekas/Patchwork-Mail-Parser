<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Auth extends Stream_Parser
{
    protected $authenticationResults = array();

    function getAuthenticationResults()
    {
        return $this->authenticationResults;
    }
}
