<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail;

use Patchwork\Stream\Parser;

class Auth extends Parser
{
    protected

    $authClass = false,
    $authenticationResults = array();


    function __construct(parent $parent)
    {
        parent::__construct($parent);

        if (__CLASS__ !== get_class($this))
        {
            isset($this->dependencies['Mail\Auth']->authenticationResults)
                ? $this->authenticationResults =& $this->dependencies['Mail\Auth']->authenticationResults[$this->authClass]
                : user_error(__CLASS__ . ' dependency is not loaded');
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
