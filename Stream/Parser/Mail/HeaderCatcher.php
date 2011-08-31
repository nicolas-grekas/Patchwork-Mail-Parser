<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

/**
 * Stream Parser Mail HeaderCatcher, Catch and analyse received DSN
 *
 * This file is collecting message headers
 * @author Sebastien Lavallee
 * @version 1.0
 * @package Stream/Parser/Mail
 */

/**
 * This page get the value of requested headers
 */

class Stream_Parser_Mail_HeaderCatcher extends Stream_Parser
{
    protected

    $catchedHeaders = array(),
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
            foreach ($headers as $h) $this->catchedHeaders[$h] = false;
        }
    }

    protected function catchHeader()
    {
        if (isset($this->catchedHeaders[$this->header->name]))
        {
            $this->catchedHeaders[$this->header->name] = $this->header->value;
        }
    }

    function getCatchedHeaders()
    {
       return $this->catchedHeaders;
    }
}
