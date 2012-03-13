<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail;

use Patchwork\Stream\Parser;

Parser::createTag('T_BOUNCE_EXCLUSIVITY');

class Bounce extends Parser
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
            isset($this->dependencies['Mail\Bounce']->bounceReports)
                ? $this->bounceReports =& $this->dependencies['Mail\Bounce']->bounceReports[$this->bounceClass][$c]
                : user_error(__CLASS__ . ' dependency is not loaded');
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
