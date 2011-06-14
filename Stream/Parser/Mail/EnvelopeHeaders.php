<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_EnvelopeHeaders extends Stream_Parser
{
    protected

    $callbacks = array('getReturnPath' => T_MAIL_HEADER),
    $dependencies = array('Mail' => 'envelope');

    protected function getReturnPath($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));

        if (!preg_match('/^Return-Path: <(.*)>/', $line, $m))
        {
            $this->setError('No Return-Path found', E_USER_WARNING);
            return;
        }

        $this->envelope->sender = $m[1];
        $this->register(array('getDeliveredTo' => T_MAIL_HEADER));
    }

    protected function getDeliveredTo($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));

        if (!preg_match('/^Delivered-To: (.*)/', $line, $m))
        {
            $this->setError('No Delivered-To found', E_USER_WARNING);
            return;
        }

        $this->envelope->recipient = $m[1];
        $this->register(array('getReceivedLine' => T_MAIL_HEADER));
    }


    protected function getReceivedLine($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));

        if (preg_match('/^Received: from\s+(.*?)\s+\((.*?)\s+\[(.*?)\]\)/', $line, $m))
        {
            $this->envelope->clientHelo     = $m[1];
            $this->envelope->clientHostname = $m[2];
            $this->envelope->clientIp       = $m[3];
        }
        else
        {
            $this->envelope->clientHelo     = 'localhost';
            $this->envelope->clientHostname = 'localhost';
            $this->envelope->clientIp       = '127.0.0.1';
        }
    }
}
