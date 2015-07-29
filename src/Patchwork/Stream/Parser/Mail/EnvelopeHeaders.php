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
 * The EnvelopeHeaders parser extracts the envelope of an email from
 * its three very first headers, in this specific order, as inserted
 * by postfix e.g.: Return-Path, Delivered-To then Received.
 */
class EnvelopeHeaders extends Parser
{
    protected $callbacks = array('getReturnPath' => T_MAIL_HEADER);
    protected $envelope;
    protected $dependencies = array('Mail' => 'envelope');

    protected function getReturnPath($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));

        if (!preg_match('/^Return-Path: <(.*)>/', $line, $m)) {
            $this->setError('No Return-Path found', E_USER_WARNING);

            return;
        }

        $this->envelope->sender = $m[1];
        $this->register(array('getDeliveredTo' => T_MAIL_HEADER));
    }

    protected function getDeliveredTo($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));

        if (!preg_match('/^Delivered-To: (.*)/', $line, $m)) {
            $this->setError('No Delivered-To found', E_USER_WARNING);

            return;
        }

        $this->envelope->recipient = $m[1];
        $this->register(array('getReceivedLine' => T_MAIL_HEADER));
    }

    protected function getReceivedLine($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_HEADER));

        if (preg_match('/^Received: from\s+(.*?)\s+\((.*?)\s+\[(.*?)\]\)(?:[\s\S]*\sfor (\S*?@\S*)[^@]*$)?/', $line, $m)) {
            $this->envelope->clientIp = $m[3];
            $this->envelope->clientHelo = $m[1];
            $this->envelope->clientHostname = $m[2];
            if (!empty($m[4])) {
                $this->envelope->recipient = trim($m[4], '<>;');
            }
        } else {
            $this->envelope->clientIp = '127.0.0.1';
            $this->envelope->clientHelo = 'localhost';
            $this->envelope->clientHostnamer = 'localhost';
        }
    }
}
