<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

namespace Patchwork\Stream\Parser\Mail\Bounce;

use Patchwork\Stream\Parser\Mail\Bounce;

class Autoreply extends Bounce
{
    protected

    $bounceClass = 'vacation',
    $callbacks = array(
        'catchAutoReply' => array(T_MAIL_HEADER => '/vacation/i'),
    ),
    $dependencies = array(
        'Mail\Bounce',
        'Mail' => array('header'),
    );


    protected function catchAutoReply($line)
    {
        if ('auto-submitted' === $this->header->name)
        {
            return $this->getExclusivity();
        }
    }
}
