<?php


namespace PaymentRecall\Controller\Admin;

use PaymentRecall\Event\PaymentRecallEvent;
use PaymentRecall\Event\PaymentRecallEvents;
use PaymentRecall\Form\ManualSendRecallForm;
use PaymentRecall\Model\PaymentRecallOrderQuery;
use PaymentRecall\PaymentRecall;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\OrderQuery;

class PaymentRecallController extends BaseAdminController
{
    public function manualRecallSend()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('PaymentRecall'), AccessManager::UPDATE)) {
            return $response;
        }

        $message = "";
        $form = new ManualSendRecallForm($this->getRequest());
        try {
            $data = $this->validateForm($form)->getData();

            $order = OrderQuery::create()->findPk($data['order_id']);
            $orderId = $order->getId();
            $recallOrder = PaymentRecallOrderQuery::create()->findOneByOrderId($orderId);


            //Email has already been send
            if (false !== $recallOrder->getRecallSend()) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        'Mail already send for this order. You can only send one mail per order',
                        array(),
                        PaymentRecall::MODULE_DOMAIN
                    )
                );
            }

            $event = new PaymentRecallEvent();
            $event->setOrder($order);

            $this->dispatch(PaymentRecallEvents::SEND_PAYMENT_RECALL, $event);

            $recallAlert = "success";

        } catch (\Exception $e) {
            $message = $e->getMessage();
            $recallAlert = "error";
        }
        return $this->generateRedirectFromRoute(
            "admin.order.update.view",
            array(),
            array("order_id"=>$orderId,
                "recall_alert"=>$recallAlert,
                "error_message"=>$message
            )
        );
    }
}
