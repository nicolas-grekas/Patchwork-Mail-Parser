<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_MailEnvelopeHeaders extends Stream_Parser
{
    protected

    $callbacks = array('getReturnPath' => T_LOGICAL_HEADER),
    $dependencies = array('Mail' => array('logicalHeader','envelopeSender','envelopeRecipient','envelopeClientIp','envelopeClientHelo','envelopeClientHostname'));

    protected function getReturnPath($line)
    {
        $this->unregister(array(__FUNCTION__ => T_LOGICAL_HEADER));

        if (!preg_match('/^Return-Path: <(.*)>/', $this->logicalHeader, $m))
        {
            $this->setError('No Return-Path found', E_USER_WARNING);
            return;
        }

        $this->envelopeSender = $m[1];
        $this->register(array('getDeliveredTo' => T_LOGICAL_HEADER));
    }

    protected function getDeliveredTo($line)
    {
        $this->unregister(array(__FUNCTION__ => T_LOGICAL_HEADER));

        if (!preg_match('/^Delivered-To: (.*)/', $this->logicalHeader, $m))
        {
            $this->setError('No Delivered-To found', E_USER_WARNING);
            return;
        }

        $this->envelopeRecipient = $m[1];
        $this->register(array('getReceivedLine' => T_LOGICAL_HEADER));
    }


    protected function getReceivedLine($line)
    {
        $this->unregister(array(__FUNCTION__ => T_LOGICAL_HEADER));

        if (preg_match('/^Received: from\s+(.*?)\s+\((.*?)\s+\[(.*?)\]\)/', $this->logicalHeader, $m))
        {
            $this->envelopeClientHelo     = $m[1];
            $this->envelopeClientHostname = $m[2];
            $this->envelopeClientIp       = $m[3];
        }
        else
        {
            $this->envelopeClientHelo     = 'localhost';
            $this->envelopeClientHostname = 'localhost';
            $this->envelopeClientIp       = '127.0.0.1';
        }
    }
}
