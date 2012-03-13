<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

/**
 * Stream Parser Mail HeaderCatcher, Catch and analyse received DSN
 *
 * This file is collecting message headers
 * @author Sebastien Lavallee
 * @version 1.0
 * @package Patchwork/Stream/Parser/Mail
 */

namespace Patchwork\Stream\Parser;

use Patchwork\Stream\Parser;

/**
 * This page get the value of requested headers
 */

class HeaderCatcher extends Parser
{
    protected

    $caughtHeaders = array(),
    $callbacks = array(
        'catchHeader' => T_MAIL_HEADER,
        'unregisterAll' => T_MAIL_BOUNDARY,
    ),
    $dependencies = array('Mail'=> 'header');


    function __construct(parent $parent, array $headers)
    {
        if ($headers)
        {
            parent::__construct($parent);
            foreach ($headers as $h) $this->caughtHeaders[$h] = false;
        }
    }

    protected function catchHeader()
    {
        if (isset($this->caughtHeaders[$this->header->name]))
        {
            $this->caughtHeaders[$this->header->name] = $this->header->value;
        }
    }

    function getCaughtHeaders()
    {
       return $this->caughtHeaders;
    }
}
