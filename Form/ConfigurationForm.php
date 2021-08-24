<?php


namespace PaymentRecall\Form;

use PaymentRecall\PaymentRecall;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Thelia\Form\BaseForm;
use Symfony\Component\Validator\Constraints;

class ConfigurationForm extends BaseForm
{

    protected function buildForm()
    {
        $this->formBuilder
            ->add('time_before_mail', IntegerType::class, array(
                'label' => $this->translator->trans(
                    'Time before send recall mail (2O min by default)',
                    array(),
                    PaymentRecall::MODULE_DOMAIN
                ),
                'data' => PaymentRecall::getConfigValue('time_before_mail')
            ))
        ;

    }
    
    public static function getName()
    {
        return "paymentrecall_configuration";
    }
}
