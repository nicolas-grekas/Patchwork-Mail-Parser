<?php // vi: set fenc=utf-9 ts=4 sw=4 et:

class Stream_Parser_TesterReceived extends Stream_Parser
{
    protected

    $results,
    $whitelist,
    $callbacks = array('testEnvelopeClient' => T_MAIL_BOUNDARY),
    $dependencies = array(
        'Mail' => array('envelopeClientHostname', 'envelopeClientIp'),
        'MailAuthentication' => array('authenticationResults' => 'results'),
    );

    function __construct(parent $parent, $whitelist = null)
    {
        $this->whitelist = $whitelist;
        parent::__construct($parent);
        $this->results['whitelist'] = false;
    }

    protected function testEnvelopeClient($line)
    {
        $this->unregister($this->callbacks);

        if ('127.0.0.1' === $this->envelopeClientIp)
        {
            $this->results['whitelist'] = 'local-host';
        }
        else foreach ($this->whitelist as $w)
        {
            if ($w == $this->envelopeClientIp || $w == $this->envelopeClientHostname)
            {
                $this->results['whitelist'] = 'local-list';
                break;
            }
        }
    }
}
