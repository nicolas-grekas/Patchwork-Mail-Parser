<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Bounce;

use Patchwork\Stream\Parser\Mail\Bounce;

/**
 * The Autoreply bounce parser detects some vacation messages.
 */
class Autoreply extends Bounce
{
    protected

    $bounceClass = 'vacation',
    $callbacks = array(
        'catchAutoReply' => T_MAIL_HEADER,
        'unregisterAll' => T_MAIL_BOUNDARY,
    ),
    $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('header'),
    );


    protected function catchAutoReply($line)
    {
        if ('auto-submitted' === $this->header->name && false !== stripos($line, 'vacation'))
        {
            return $this->getExclusivity();
        }
    }
}
