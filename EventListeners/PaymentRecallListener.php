<?php


namespace PaymentRecall\EventListeners;

use PaymentRecall\Event\PaymentRecallEvent;
use PaymentRecall\Event\PaymentRecallEvents;
use PaymentRecall\Model\PaymentRecallOrderQuery;
use PaymentRecall\PaymentRecall;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\URL;

class PaymentRecallListener implements EventSubscriberInterface
{
    protected $parser;
    protected $mailer;
    protected $dispatcher;

    public function __construct(ParserInterface $parser, MailerFactory $mailer, EventDispatcherInterface $dispatcher)
    {
        $this->parser = $parser;
        $this->mailer = $mailer;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return \Thelia\Mailer\MailerFactory
     */
    public function getMailer()
    {
        return $this->mailer;
    }

    public function sendRecallEmail(PaymentRecallEvent $event)
    {
        $order = $event->getOrder();
        $message = MessageQuery::create()
            ->findOneByName(PaymentRecall::RECALL_MESSAGE_NAME);

        if ($order->getOrderStatus()->getCode() === PaymentRecall::NOT_PAID_STATUS) {
            if (false === $message) {
                throw new \Exception("Failed to load message '".PaymentRecall::RECALL_MESSAGE_NAME."'");
            }

            $contact_email = ConfigQuery::read('store_email', false);

            if (!$contact_email) {
                throw new \Exception("Failed to find contact store email");
            }

            Tlog::getInstance()->debug("Sending recall payment email from store contact e-mail $contact_email");

            $customer = $order->getCustomer();

            $messageParameters = [
                "customer_id"=>$customer->getId(),
                "order_id"=>$order->getId(),
                "order_ref"=>$order->getRef(),
                "recall_url"=>rtrim(ConfigQuery::read('url_base_site'), "/")."/order/retry/payment",
                "logo_url"=>rtrim(ConfigQuery::read('url_base_site'), "/")."/logo_email.jpg"
            ];

            $this->getMailer()->sendEmailMessage(
                PaymentRecall::RECALL_MESSAGE_NAME,
                [$contact_email=>ConfigQuery::read('store_name')],
                [$customer->getEmail()=>$customer->getFirstname()." ".$customer->getLastname()],
                $messageParameters,
                $order->getLang()->getLocale()
            );

            Tlog::getInstance()->debug("Recall payment mail sent to customer ".$customer->getEmail());

            //Cancel old order
            $cancelStatus = OrderStatusQuery::create()->findOneByCode(PaymentRecall::CANCEL_STATUS);
            $orderEvent = new OrderEvent($order);
            $orderEvent->setStatus($cancelStatus->getId());
            $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_UPDATE_STATUS);


            PaymentRecallOrderQuery::create()
                ->findOneByOrderId($order->getId())
                ->setRecallSend(true)
                ->save();

            $event->setIsEmailSend(true);
        }


    }

    public static function getSubscribedEvents()
    {
        return [
            PaymentRecallEvents::SEND_PAYMENT_RECALL => ["sendRecallEmail", 128]
        ];
    }
}
