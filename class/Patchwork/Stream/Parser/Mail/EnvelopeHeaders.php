<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

/**
 * Stream Parser Mail Envelope Headers, Catch and analyse received DSN
 *
 * This file is collecting values of the envelope and record them in mail.php
 * @author Sebastien Lavallee
 * @version 1.0
 * @package Patchwork/Stream/Parser/Mail
 */

/**
 * This page gets the content of the envelope header
 */

class Patchwork_Stream_Parser_Mail_EnvelopeHeaders extends Patchwork_Stream_Parser
{
    protected

    $callbacks = array('getReturnPath' => T_MAIL_HEADER),
    $dependencies = array('Mail' => 'envelope');


    /**
     * Get the sender from the stream if the bounce using the Return-Path header line.
     *
     * @param $line
     *  Input string to be analysed
     */

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

    /**
     * Get the recipient from the stream if the bounce using the Delivered-to header line.
     *
     * @param $line
     *  Input string to be analysed
     */

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

    /**
     * Get the client Helo, hostname and Ip from the stream if the bounce using the Received header line.
     *
     * @param $line
     *  Input string to be analysed
     */

    protected function getReceivedLine($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));

        if (preg_match('/^Received: from\s+(.*?)\s+\((.*?)\s+\[(.*?)\]\)(?:[\s\S]*\sfor (\S*?@\S*)[^@]*$)?/', $line, $m))
        {
            $this->envelope->clientIp = $m[3];
            $this->envelope->clientHelo = $m[1];
            $this->envelope->clientHostname = $m[2];
            empty($m[4]) || $this->envelope->recipient = trim($m[4], '<>;');
        }
        else
        {
            $this->envelope->clientIp = '127.0.0.1';
            $this->envelope->clientHelo = 'localhost';
            $this->envelope->clientHostnamer = 'localhost';
        }
    }
}
