<?php
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Stream\Parser\Mail;

use Patchwork\Stream\Parser;

/**
 * The Auth parser does nothing on its own but acts as a container for
 * child sub-parsers that extract authentication results, each child
 * specialized on one type of authentication.
 */
class Auth extends Parser
{
    protected $authClass = false;
    protected $authenticationResults = array();

    public function __construct(parent $parent)
    {
        parent::__construct($parent);

        if (__CLASS__ !== get_class($this)) {
            if (isset($this->dependencies['Mail\Auth']->authenticationResults) && $this->dependencies['Mail\Auth'] instanceof self) {
                $this->authenticationResults = &$this->dependencies['Mail\Auth']->authenticationResults[$this->authClass];
            } else {
                user_error(__CLASS__.' dependency is not loaded');
            }
        }
    }

    public function getAuthenticationResults()
    {
        return $this->authenticationResults;
    }

    protected function reportAuth($result)
    {
        $this->authenticationResults = $result;
    }
}
