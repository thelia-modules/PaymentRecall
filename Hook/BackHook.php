<?php


namespace PaymentRecall\Hook;


use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class BackHook extends BaseHook
{
    public function onModuleConfigJs(HookRenderEvent $event)
    {
        $event->add($this->render('module-config-js.html'));
    }

    public function onOrderEdit(HookRenderEvent $event)
    {
        $event->add($this->render('order-edit.html'));
    }
}