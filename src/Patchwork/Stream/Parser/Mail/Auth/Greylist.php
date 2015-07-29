<?php
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

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
    protected $result = false;
    protected $authClass = 'whitelist';
    protected $pattern = array(
        'Local Mail' => 'local-list',
        'Sender IP whitelisted' => 'local-list',
        'Sender IP whitelisted by DNSRBL' => 'external-list',
    );
    protected $callbacks = array(
        'catchGreylist' => T_MAIL_HEADER,
        'registerResults' => T_MAIL_BOUNDARY,
    );
    protected $dependencies = array('Mail\Auth');

    public function __construct(Parser $parent)
    {
        uksort($this->pattern, array(__CLASS__, 'strlencmp'));
        parent::__construct($parent);
        $this->reportAuth(false);
    }

    protected function catchGreylist($line)
    {
        if (0 === strncasecmp($line, 'X-Greylist: ', 12)) {
            $this->result = false;

            foreach ($this->pattern as $p => $r) {
                if (12 === strpos($line, $p)) {
                    $this->result = $r;
                    break;
                }
            }
        }
    }

    protected function registerResults($line)
    {
        $this->unregister($this->callbacks);

        if (false !== $this->result && 'local-list' !== $this->authenticationResults) {
            $this->reportAuth($this->result);
        }
    }

    public static function strlencmp($a, $b)
    {
        return strlen($b) - strlen($a);
    }
}
