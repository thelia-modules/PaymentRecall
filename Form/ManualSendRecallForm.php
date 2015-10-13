<?php

namespace PaymentRecall\Form;

use Thelia\Form\BaseForm;
use Symfony\Component\Validator\Constraints\NotBlank;

class ManualSendRecallForm extends BaseForm
{

    protected function buildForm()
    {
        $this->formBuilder
            ->add('order_id', 'integer', array(
                'constraints' => array(
                    new NotBlank()
                ),
            ))
        ;
    }
    
    public function getName()
    {
        return "paymentrecall_manual_send_recall";
    }
}
