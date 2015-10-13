<?php

namespace PaymentRecall\EventListeners;

use PaymentRecall\Model\Base\PaymentRecallModuleQuery;
use PaymentRecall\Model\PaymentRecallOrderQuery;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;

class OrderListener implements EventSubscriberInterface
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function recordOrder(OrderEvent $event)
    {
        $order = $event->getOrder();

        $paymentModuleId = $order->getPaymentModuleId();

        $isEnablePaymentModule = PaymentRecallModuleQuery::create()
            ->filterByModuleId($paymentModuleId)
            ->findOneByEnable(true);

        //If the payment module is enable in PaymentRecall config record the order
        if ($isEnablePaymentModule !== null) {
            $record = PaymentRecallOrderQuery::create()
                ->filterByCustomerId($order->getCustomerId())
                ->findOneOrCreate();

            $record->setOrderId($order->getId())
                ->setRecallSend(false)
                ->save();
        }
    }

    public function updateOrderAddresses(OrderEvent $event)
    {
        //If the order come from a retry order
        if (!$orderId = $this->request->getSession()->get('payment_retry_order_id')) {
            return false;
        }

        $oldOrder = OrderQuery::create()
            ->findOneById($orderId);

        if (null === $oldOrder) {
            throw new \ErrorException("Sorry got an error with payment retry.");
        }

        //Get delivery address from old canceled order and set same inf in new delivery address
        $oldDeliveryAddress = OrderAddressQuery::create()
            ->findPk($oldOrder->getDeliveryOrderAddressId());
        $updateDelivery = OrderAddressQuery::create()
            ->findPk($event->getOrder()->getDeliveryOrderAddressId())
            ->setCompany($oldDeliveryAddress->getCompany())
            ->setAddress1($oldDeliveryAddress->getAddress1())
            ->setAddress2($oldDeliveryAddress->getAddress2())
            ->setAddress3($oldDeliveryAddress->getAddress3())
            ->setZipcode($oldDeliveryAddress->getZipcode())
            ->setCity($oldDeliveryAddress->getCity())
            ->save();

        //Same as delivery address for invoice address
        $oldInvoiceAddress = OrderAddressQuery::create()
            ->findPk($oldOrder->getInvoiceOrderAddressId());
        $updateInvoice = OrderAddressQuery::create()
            ->findPk($event->getOrder()->getInvoiceOrderAddressId())
            ->setCompany($oldInvoiceAddress->getCompany())
            ->setAddress1($oldInvoiceAddress->getAddress1())
            ->setAddress2($oldInvoiceAddress->getAddress2())
            ->setAddress3($oldInvoiceAddress->getAddress3())
            ->setZipcode($oldInvoiceAddress->getZipcode())
            ->setCity($oldInvoiceAddress->getCity())
            ->save();

        //Remove 'payment_retry_order_id' for nex order
        $this->request->getSession()->remove('payment_retry_order_id');
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::ORDER_AFTER_CREATE => ["recordOrder"],
            TheliaEvents::ORDER_BEFORE_PAYMENT => ["updateOrderAddresses"]
        ];
    }
}
