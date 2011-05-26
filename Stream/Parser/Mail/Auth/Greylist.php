<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Auth_Greylist extends Stream_Parser
{
    protected

    $result = false,
    $results,
    $pattern = array(
        'Sender IP whitelisted by DNSRBL' => 'external-list',
        'Sender IP whitelisted'           => 'local-list',
        'Local Mail'                      => 'local-list',
    ),
    $callbacks = array(
        'testGreylist' => array('/^X-Greylist: /' => T_MAIL_HEADER),
        'registerResults' => T_MAIL_BOUNDARY,
    ),
    $dependencies = array('Mail_Auth' => array('authenticationResults' => 'results'));


    function __construct(parent $parent)
    {
        uksort($this->pattern, array(__CLASS__, 'strlencmp'));
        parent::__construct($parent);
        $this->results['whitelist'] = false;
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

        if (false !== $this->result && 'local-list' !== $this->results['whitelist'])
            $this->results['whitelist'] = $this->result;
    }

    static function strlencmp($a, $b)
    {
        return strlen($b) - strlen($a);
    }
}
