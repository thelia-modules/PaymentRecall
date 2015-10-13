<?php

namespace PaymentRecall\Controller\Admin;

use PaymentRecall\Form\ConfigurationForm;
use PaymentRecall\Model\PaymentRecallModuleQuery;
use PaymentRecall\PaymentRecall;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class PaymentRecallConfigurationController extends BaseAdminController
{
    public function configuration()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('PaymentRecall'), AccessManager::UPDATE)) {
            return $response;
        }

        $form = new ConfigurationForm($this->getRequest());

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
            $this->getTranslator()->trans("PaymentRecall configuration", [], PaymentRecall::MODULE_DOMAIN),
            $message,
            $form,
            $e
        );


        return $this->render('module-configure', array('module_code' => 'PaymentRecall'));
    }

    public function toggleModule($id)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('PaymentRecall'), AccessManager::UPDATE)) {
            return $response;
        }

        PaymentRecallModuleQuery::create()
            ->filterByModuleId($id)
            ->findOneOrCreate()
            ->setEnable($this->getRequest()->request->get("enable"))
            ->save();
    }
}
