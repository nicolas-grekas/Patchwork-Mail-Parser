<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Bounce_Rfc3464 extends Stream_Parser_Mail_Bounce
{
    protected

    $status,
    $recipient,
    $diagnosticCode,

    $callbacks = array(
        'startBounceParse' => T_MAIL_BOUNDARY,
    ),
    $dependencies = array(
        'Mail_Bounce' => array('bounceDatas' => 'results'),
        'Mail' => array('type', 'header', 'mimePart'),
    );


    protected function startBounceParse($line)
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_BOUNDARY));

        if (1 === $this->mimePart->depth
            && 'multipart/report' === $this->type->top
            && isset($this->type->params['report-type'])
            && 0 === strcasecmp('delivery-status', $this->type->params['report-type']) )
        {
            $this->register(array('startDsnPart' => T_MAIL_BOUNDARY));
            return $this->getExclusivity();
        }
    }

    protected function startDsnPart($line)
    {
        if (1 === $this->mimePart->depth && 'message/delivery-status' === $this->type->top)
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
            $v = explode(";", $this->header->value);
            $this->recipient = trim($v[1]);
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
            $this->results[$this->recipient] = "{$this->diagnosticCode} (#{$this->status})";
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
