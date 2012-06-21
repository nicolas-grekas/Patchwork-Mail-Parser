<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Stream\Parser\Mail\Bounce;

use Patchwork\Stream\Parser\Mail\Bounce;

/**
 * The Rfc3464 mail parser extracts bounced recipients and reasons from RFC3464 compliant bounces.
 */
class Rfc3464 extends Bounce
{
    protected

    $status,
    $recipient,
    $diagnosticCode,

    $callbacks = array(
        'catchBoundary' => T_MAIL_BOUNDARY,
    ),
    $type, $header, $mimePart,
    $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('type', 'header', 'mimePart'),
    );


    protected function catchBoundary($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_BOUNDARY));
        $this->register(array('startDsnPart' => T_MAIL_BOUNDARY));

        if ( 'multipart/report' === $this->type->top
          && isset($this->type->params['report-type'])
          && 0 === strcasecmp('delivery-status', $this->type->params['report-type']) )
        {
            return $this->getExclusivity();
        }
    }

    protected function startDsnPart($line)
    {
        if ('message/delivery-status' === $this->type->top)
        {
            $this->dependencies['Mail']->setNextType($this->type->top);

            $this->unregister(array(__FUNCTION__ => T_MAIL_BOUNDARY));
            $this->register(array(
                'extractReportInfo' => T_MAIL_HEADER,
                'endReport'  => array(T_MAIL_BOUNDARY, T_MIME_BOUNDARY),
                'endDsnPart' => T_MIME_BOUNDARY
            ));

            return $this->getExclusivity();
        }
    }

    protected function extractReportInfo($line)
    {
        switch ($this->header->name)
        {
        case 'status':
            $this->status = $this->header->value;
            break;
        case 'final-recipient':
            if ($this->recipient) break;
        case 'original-recipient':
            if ('<' === substr($this->header->value, 0, 1))
            {
                $this->recipient = trim($this->header->value, '<>');
            }
            else
            {
                $v = explode(";", $this->header->value);
                $this->recipient = trim($v[1]);
            }
            break;
        case 'diagnostic-code':
            $this->diagnosticCode = $this->header->value;
            break;
        }
    }

    protected function endReport($line)
    {
        if (isset($this->recipient))
        {
            $this->reportBounce($this->recipient, "{$this->diagnosticCode} (#{$this->status})");
        }

        $this->status = null;
        $this->recipient = null;
        $this->diagnosticCode = null;

        $this->dependencies['Mail']->setNextType($this->type->top);
    }

    protected function endDsnPart()
    {
        $this->unregisterAll();
    }
}
