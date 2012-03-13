<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Auth;

use Patchwork\Stream\Parser;
use Patchwork\Stream\Parser\Mail\Auth;

class Greylist extends Auth
{
    protected

    $result = false,
    $authClass = 'whitelist',
    $pattern = array(
        'Sender IP whitelisted by DNSRBL' => 'external-list',
        'Sender IP whitelisted'           => 'local-list',
        'Local Mail'                      => 'local-list',
    ),
    $callbacks = array(
        'testGreylist' => array('/^X-Greylist: /' => T_MAIL_HEADER),
        'registerResults' => T_MAIL_BOUNDARY,
    ),
    $dependencies = array('Mail\Auth');


    function __construct(Parser $parent)
    {
        uksort($this->pattern, array(__CLASS__, 'strlencmp'));
        parent::__construct($parent);
        $this->reportAuth(false);
    }

    protected function testGreylist($line)
    {
        $this->result = false;

        foreach ($this->pattern as $p => $r)
        {
            if (12 === strpos($line, $p))
            {
                $this->result = $r;
                break;
            }
        }
    }

    protected function registerResults($line)
    {
        $this->unregister($this->callbacks);

        if (false !== $this->result && 'local-list' !== $this->authenticationResults)
            $this->reportAuth($this->result);
    }

    static function strlencmp($a, $b)
    {
        return strlen($b) - strlen($a);
    }
}
