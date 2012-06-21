<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser;

use Patchwork\Stream\Parser;

/**
 * The HeaderCatcher mail parser extracts requested header from a message.
 */
class HeaderCatcher extends Parser
{
    protected

    $caughtHeaders = array(),
    $callbacks = array(
        'catchHeader' => T_MAIL_HEADER,
        'unregisterAll' => T_MAIL_BOUNDARY,
    ),
    $header,
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
