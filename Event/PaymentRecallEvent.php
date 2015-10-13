<?php

namespace PaymentRecall\Event;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;

class PaymentRecallEvent extends ActionEvent
{

    protected $order;
    protected $isEmailSend;

    /**
     * @param Order $order
     * @return $this
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param bool $isEmailSend
     * @return $this
     */
    public function setIsEmailSend($isEmailSend)
    {
        $this->isEmailSend = $isEmailSend;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIsEmailSend()
    {
        return $this->isEmailSend;
    }
}
