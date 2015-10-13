<?php


namespace PaymentRecall\Loop;

use PaymentRecall\Model\PaymentRecallOrder;
use PaymentRecall\Model\PaymentRecallOrderQuery;
use PaymentRecall\PaymentRecall;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

class PaymentRecallOrderLoop extends BaseLoop implements PropelSearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument('order_id')
        );
    }

        
    public function buildModelCriteria()
    {
        return PaymentRecallOrderQuery::create()
            ->filterByOrderId($this->getOrderId());
    }
    
    
    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var PaymentRecallOrder $recall */
        foreach ($loopResult->getResultDataCollection() as $recall) {
            $loopResultRow = new LoopResultRow($recall);

            $loopResultRow->set("RECALL_ID", $recall->getId())
                ->set("RECALL_SEND", $recall->getRecallSend());
            $loopResult->addRow($loopResultRow);
        }
        return $loopResult;
    }
}
