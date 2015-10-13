<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace PaymentRecall;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Install\Database;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\BaseModule;

class PaymentRecall extends BaseModule
{
    const MODULE_DOMAIN = "paymentrecall";
    const NOT_PAID_STATUS = "not_paid";
    const CANCEL_STATUS = "canceled";
    const RECALL_MESSAGE_NAME = "recall_payment_not_paid";

    /**
     * @param ConnectionInterface $con
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        if (!self::getConfigValue('is_initialized', false)) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__ . "/Config/thelia.sql"]);
            self::setConfigValue('time_before_mail', 20);
            self::setConfigValue('is_initialized', true);
        }

        if (null === MessageQuery::create()->findOneByName(self::RECALL_MESSAGE_NAME)) {
            $message = new Message();

            $email_templates_dir = __DIR__.DS.'I18n'.DS.'email-templates'.DS;

            $message
                ->setName(self::RECALL_MESSAGE_NAME)

                ->setLocale('en_US')
                ->setTitle('Recall payment')
                ->setSubject('Payment recall for order {$order_ref}')
                ->setHtmlMessage(file_get_contents($email_templates_dir.'en.html'))
                ->setTextMessage(file_get_contents($email_templates_dir.'en.txt'))

                ->setLocale('fr_FR')
                ->setTitle('Rappel de paiement')
                ->setSubject('Rappel de paiement pour votre commande {$order_ref}')
                ->setHtmlMessage(file_get_contents($email_templates_dir.'fr.html'))
                ->setTextMessage(file_get_contents($email_templates_dir.'fr.txt'))

                ->save()
            ;

        }
    }
}
