<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

class Stream_Parser_Mail_Bounce_Exim extends Stream_Parser_Mail_Bounce
{
    protected

    $reasons = array(),
    $recipient,

    $callbacks = array(
        'extractHeaderRecipient' => T_MAIL_HEADER,
        'catchBoundary' => T_MAIL_BOUNDARY,
        'endReport' => array('/^---/' => T_MAIL_BODY),
    ),
    $dependencies = array(
        'Mail_Bounce',
        'Mail' => array('header'),
    );


    protected function extractHeaderRecipient($line)
    {
        if ('x-failed-recipients' === $this->header->name)
        {
            $v = explode(', ', $this->header->value);
            foreach ($v as $v) $this->reasons[$v] = '';
            return $this->getExclusivity();
        }
    }

    protected function catchBoundary()
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_BOUNDARY));
        $this->unregister(array('extractHeaderRecipient' => T_MAIL_HEADER));

        if (empty($this->reasons))
        {
            $this->unregisterAll();
        }
        else
        {
            $this->register(array('extractBodyRecipient' => array('/^  (\S*?@\S*)/' => T_MAIL_BODY)));
        }
    }

    protected function extractBodyRecipient($line, $matches)
    {
        if (isset($this->reasons[$matches[1]]))
        {
            $this->recipient = $matches[1];
            $this->unregister(array('extractReason' => array('/^    /' => T_MAIL_BODY)));
            $this->  register(array('extractReason' => array('/^    /' => T_MAIL_BODY)));
        }
    }

    protected function extractReason($line)
    {
        if ('' !== $line = trim($line))
        {
            $this->reasons[$this->recipient] .= $line . ' ';
        }
    }

    protected function endReport()
    {
        foreach ($this->reasons as $r => $s)
            $this->reportBounce($r, rtrim($s));

        $this->unregisterAll();
    }
}
