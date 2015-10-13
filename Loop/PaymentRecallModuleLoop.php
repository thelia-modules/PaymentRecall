<?php


namespace PaymentRecall\Loop;

use PaymentRecall\Model\PaymentRecallModuleQuery;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Payment;

class PaymentRecallModuleLoop extends Payment
{
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Module $paymentModule */
        foreach ($loopResult->getResultDataCollection() as $paymentModule) {
            $loopResultRow = new LoopResultRow($paymentModule);

            $recallEnabled = PaymentRecallModuleQuery::create()
                ->findOneByModuleId($paymentModule->getId());

            $loopResultRow
                ->set('ID', $paymentModule->getId())
                ->set('CODE', $paymentModule->getCode())
                ->set('TITLE', $paymentModule->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $paymentModule->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $paymentModule->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $paymentModule->getVirtualColumn('i18n_POSTSCRIPTUM'));

            if ($recallEnabled) {
                $loopResultRow->set('PAYMENT_RECALL', $recallEnabled->getEnable());
            } else {
                $loopResultRow->set('PAYMENT_RECALL', false);
            }

            $this->addOutputFields($loopResultRow, $paymentModule);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
