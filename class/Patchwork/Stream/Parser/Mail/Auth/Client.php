<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Auth;

use Patchwork\Stream\Parser;
use Patchwork\Stream\Parser\Mail\Auth;

/**
 * The Client parser reports if the client IP or hostname from
 * which a message is received is within a given whitelist.
 */
class Client extends Auth
{
    protected

    $whitelist,
    $authClass = 'whitelist',
    $callbacks = array('testEnvelopeClient' => T_MAIL_BOUNDARY),
    $dependencies = array(
        'Mail\Auth',
        'Mail' => 'envelope',
    );

    function __construct(Parser $parent, $whitelist = null)
    {
        $this->whitelist = $whitelist;
        parent::__construct($parent);
        $this->reportAuth(false);
    }

    protected function testEnvelopeClient($line)
    {
        $this->unregister($this->callbacks);

        if ('127.0.0.1' === $this->envelope->clientIp)
        {
            $this->reportAuth('local-host');
        }
        else foreach ($this->whitelist as $w)
        {
            if ($w == $this->envelope->clientIp || $w == $this->envelope->clientHostname)
            {
                $this->reportAuth('local-list');
                break;
            }
        }
    }
}
