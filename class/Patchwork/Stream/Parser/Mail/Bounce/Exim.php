<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Bounce;

class Exim extends Qmail
{
    protected

    $recipientRx = '/^  (\S*?@\S*)/',

    $callbacks = array(
        'extractHeaderRecipient' => T_MAIL_HEADER,
        'catchBoundary' => T_MAIL_BOUNDARY,
    ),
    $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('header', 'bodyLine'),
    );


    protected function extractHeaderRecipient($line)
    {
        if ('x-failed-recipients' === $this->header->name)
        {
            $v = explode(', ', $this->header->value);
            foreach ($v as $v) $this->reportBounce($v, '');
            return $this->getExclusivity();
        }
    }

    protected function catchBoundary()
    {
        $this->unregister(array(__FUNCTION__ => T_MAIL_BOUNDARY));
        $this->unregister(array('extractHeaderRecipient' => T_MAIL_HEADER));

        if ($this->hasExclusivity)
        {
            $this->register(array('extractBodyRecipient' => T_MAIL_BODY));
        }
        else
        {
            $this->unregisterAll();
        }
    }
}
