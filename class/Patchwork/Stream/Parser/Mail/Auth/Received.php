<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Patchwork_Stream_Parser_Mail_Auth_Received extends Patchwork_Stream_Parser_Mail_Auth
{
    protected

    $whitelist,
    $authClass = 'whitelist',
    $callbacks = array('testEnvelopeClient' => T_MAIL_BOUNDARY),
    $dependencies = array(
        'Mail' => 'envelope',
        'Mail_Auth',
    );

    function __construct(Patchwork_Stream_Parser $parent, $whitelist = null)
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
