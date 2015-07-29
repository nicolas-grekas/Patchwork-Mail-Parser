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
use Patchwork\Stream\Parser\Mail;

/**
 * The PRA parser implements the RFC4407 Purported Responsible Address.
 *
 * @todo: really follow the RFC
 */
class Pra extends Parser
{
    protected $callbacks = array(
        'tagHeader' => T_MAIL_HEADER,
        'unregisterAll' => T_MAIL_BOUNDARY,
    );
    protected $envelope;
    protected $header;
    protected $dependencies = array('Mail' => array('envelope', 'header'));

    protected function tagHeader($line)
    {
        switch ($this->header->name) {
            case 'resent-sender':
            case 'resent-from':
            case 'sender':
            case 'from':
                $pra = Mail::parseAddresses($this->header->value);

                if (1 === count($pra)) {
                    $this->envelope->pra = $pra[0];
                    $this->unregister($this->callbacks);
                }
        }
    }
}
