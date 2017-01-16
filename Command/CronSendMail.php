<?php

namespace PaymentRecall\Command;

use PaymentRecall\Event\PaymentRecallEvent;
use PaymentRecall\Event\PaymentRecallEvents;
use PaymentRecall\Model\PaymentRecallOrder;
use PaymentRecall\Model\PaymentRecallOrderQuery;
use PaymentRecall\PaymentRecall;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderQuery;

class CronSendMail extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("module:PaymentRecall:CronSendMail")
            ->setDescription("Send a mail if the order was not pay after defined time");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf('Checking recorded orders...'));
        Tlog::getInstance()->info('Checking recorded orders...');

        $this->checkOrders($output);
    }

    protected function checkOrders(OutputInterface $output)
    {
        $timeBeforeMail = PaymentRecall::getConfigValue('time_before_mail', 20);

        //Create fake session for sending mail
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $container = $this->getContainer();
        $container->set("request", $request);
        if (ConfigQuery::read('thelia_minus_version') < 3) {
            $container->enterScope("request");
        }

        //Get order for who no mail was sent
        $paymentRecallOrderNotSend = PaymentRecallOrderQuery::create()
            ->filterByRecallSend(0)
            ->find();

        $oneOrderFound = false;

        try {
            //If at least one order was found
            if ($paymentRecallOrderNotSend->count() > 0) {
                /** @var PaymentRecallOrder $paymentRecallOrder */
                foreach ($paymentRecallOrderNotSend as $paymentRecallOrder) {
                    $order = OrderQuery::create()
                        ->findOneById($paymentRecallOrder->getOrderId());
                    $status = $order->getOrderStatus();

                    //Keep only the orders with status 'not_paid'
                    if ($status->getCode() === PaymentRecall::NOT_PAID_STATUS) {
                        $oneOrderFound = true;

                        $output->writeln(sprintf('Order found ref : '.$order->getRef()));
                        Tlog::getInstance()->info('RecallPayment : Order found ref : '.$order->getRef());


                        $orderCreatedAt = strtotime($order->getCreatedAt('Y-m-d H:i:s'));
                        $now = time();

                        //Get time since order was placed (in min)
                        $intervale = ceil(($now - $orderCreatedAt) / 60);

                        //Compare with time before mail config
                        if ($intervale > $timeBeforeMail) {
                            $dispatcher = $this->getContainer()->get('event_dispatcher');

                            $paymentRecallEvent = new PaymentRecallEvent();
                            $paymentRecallEvent->setOrder($order);

                            //Dispatch the event who gonna send email
                            try {
                                $dispatcher->dispatch(PaymentRecallEvents::SEND_PAYMENT_RECALL, $paymentRecallEvent);
                            } catch (\Exception $e) {
                                Tlog::getInstance()->error('Error while sending the mail for : '.$order->getRef().' message : '.$e->getMessage());
                            }

                            if ($paymentRecallEvent->getIsEmailSend() !== true) {
                                throw new \Exception('Error while sending the mail for : '.$order->getRef());
                            }

                            $output->writeln(sprintf('Email send  for ref : '.$order->getRef()));
                            Tlog::getInstance()->info('RecallPayment : Email send  for ref : '.$order->getRef());
                        } else {
                            $output->writeln(sprintf($order->getRef().' has not yet passed the time for sending a recall mail'));
                            Tlog::getInstance()->info('RecallPayment :'.$order->getRef().' has not yet passed the time for sending a recall mail');
                        }
                    }
                }
            }
            if ($oneOrderFound !== true) {
                $output->writeln(sprintf('No order found'));
                Tlog::getInstance()->info('No order found');
            }
            $output->writeln(sprintf('Recall payment process ended with success'));
            Tlog::getInstance()->info('Recall payment process ended with success');
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
        }
    }
}
