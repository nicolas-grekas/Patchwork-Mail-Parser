<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Auth;

use Patchwork\Stream\Parser;
use Patchwork\Stream\Parser\Mail\Auth;

/**
 * The Greylist parser assumes that the last X-Greylist header in a mail
 * is inserted by the local server. As such, it can safelly extract
 * authentication data appended there by the milter-greylist component.
 */
class Greylist extends Auth
{
    protected

    $result = false,
    $authClass = 'whitelist',
    $pattern = array(
        'Local Mail' => 'local-list',
        'Sender IP whitelisted' => 'local-list',
        'Sender IP whitelisted by DNSRBL' => 'external-list',
    ),
    $callbacks = array(
        'catchGreylist' => T_MAIL_HEADER,
        'registerResults' => T_MAIL_BOUNDARY,
    ),
    $dependencies = array('Mail\Auth');


    function __construct(Parser $parent)
    {
        uksort($this->pattern, array(__CLASS__, 'strlencmp'));
        parent::__construct($parent);
        $this->reportAuth(false);
    }

    protected function catchGreylist($line)
    {
        if (0 === strncasecmp($line, 'X-Greylist: ', 12))
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
    }

    protected function registerResults($line)
    {
        $this->unregister($this->callbacks);

        if (false !== $this->result && 'local-list' !== $this->authenticationResults)
        {
            $this->reportAuth($this->result);
        }
    }

    static function strlencmp($a, $b)
    {
        return strlen($b) - strlen($a);
    }
}
