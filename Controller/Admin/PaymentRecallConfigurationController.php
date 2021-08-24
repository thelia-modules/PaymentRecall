<?php

namespace PaymentRecall\Controller\Admin;

use PaymentRecall\Form\ConfigurationForm;
use PaymentRecall\Model\PaymentRecallModuleQuery;
use PaymentRecall\PaymentRecall;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/paymentrecall", name="payment_recall")
 */
class PaymentRecallConfigurationController extends BaseAdminController
{
    /**
     * @Route("/configuration", name="_configuration", methods="POST")
     */
    public function configuration()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('PaymentRecall'), AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ConfigurationForm::getName());

        try {
            $formData = $this->validateForm($form)->getData();

            foreach ($formData as $name => $value) {
                if ($name === "success_url" || $name === "error_message") {
                    continue;
                }
                PaymentRecall::setConfigValue($name, $value);
            }

            return $this->generateRedirect('/admin/module/PaymentRecall');

        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $this->setupFormErrorContext(
            Translator::getInstance()->trans("PaymentRecall configuration", [], PaymentRecall::MODULE_DOMAIN),
            $message,
            $form,
            $e
        );


        return $this->render('module-configure', array('module_code' => 'PaymentRecall'));
    }

    /**
     * @Route("/toggle/module/{id}", name="_toggle_module", methods="POST")
     */
    public function toggleModule($id, RequestStack $requestStack)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('PaymentRecall'), AccessManager::UPDATE)) {
            return $response;
        }

        PaymentRecallModuleQuery::create()
            ->filterByModuleId($id)
            ->findOneOrCreate()
            ->setEnable($requestStack->getCurrentRequest()->request->get("enable"))
            ->save();
    }
}
