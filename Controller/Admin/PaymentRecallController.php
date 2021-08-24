<?php


namespace PaymentRecall\Controller\Admin;

use PaymentRecall\Event\PaymentRecallEvent;
use PaymentRecall\Event\PaymentRecallEvents;
use PaymentRecall\Form\ManualSendRecallForm;
use PaymentRecall\Model\PaymentRecallOrderQuery;
use PaymentRecall\PaymentRecall;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Model\OrderQuery;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/paymentrecall/manual/send", name="payment_recall_manual_send")
 */
class PaymentRecallController extends BaseAdminController
{
    /**
     * @Route("", name="", methods="POST")
     */
    public function manualRecallSend(EventDispatcherInterface $dispatcher)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('PaymentRecall'), AccessManager::UPDATE)) {
            return $response;
        }

        $message = "";
        $form = $this->createForm(ManualSendRecallForm::getName());
        try {
            $data = $this->validateForm($form)->getData();

            $order = OrderQuery::create()->findPk($data['order_id']);
            $orderId = $order->getId();
            $recallOrder = PaymentRecallOrderQuery::create()->findOneByOrderId($orderId);


            //Email has already been send
            if (false !== $recallOrder->getRecallSend()) {
                throw new \Exception(
                    Translator::getInstance()->trans(
                        'Mail already send for this order. You can only send one mail per order',
                        array(),
                        PaymentRecall::MODULE_DOMAIN
                    )
                );
            }

            $event = new PaymentRecallEvent();
            $event->setOrder($order);

            $dispatcher->dispatch($event, PaymentRecallEvents::SEND_PAYMENT_RECALL);

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
