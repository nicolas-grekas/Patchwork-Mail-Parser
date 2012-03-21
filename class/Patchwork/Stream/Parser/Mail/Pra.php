<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

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
    protected

    $callbacks = array(
        'tagHeader' => T_MAIL_HEADER,
        'tagBoundary' => T_MAIL_BOUNDARY,
    ),
    $dependencies = array('Mail' => array('envelope', 'header'));


    protected function tagHeader($line)
    {
        switch ($this->header->name)
        {
        case 'resent-sender':
        case 'resent-from':
        case 'sender':
        case 'from':
            $pra = Mail::parseAddresses($this->header->value);

            if (1 === count($pra))
            {
                $this->envelope->pra = $pra[0];
                $this->unregister($this->callbacks);
            }
        }
    }

    protected function tagBoundary($line)
    {
        $this->unregister($this->callbacks);
    }
}
