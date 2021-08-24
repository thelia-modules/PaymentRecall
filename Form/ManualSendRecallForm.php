<?php

namespace PaymentRecall\Form;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Thelia\Form\BaseForm;
use Symfony\Component\Validator\Constraints\NotBlank;

class ManualSendRecallForm extends BaseForm
{

    protected function buildForm()
    {
        $this->formBuilder
            ->add('order_id', IntegerType::class, array(
                'constraints' => array(
                    new NotBlank()
                ),
            ))
        ;
    }
    
    public static function getName()
    {
        return "paymentrecall_manual_send_recall";
    }
}
