<?php

namespace PaymentRecall\Controller\Front;

use PaymentRecall\PaymentRecall;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use \Front\Controller\OrderController as BaseOrderController;

class OrderController extends BaseOrderController
{
    protected $useFallbackTemplate = true;

    public function retryPayment()
    {
        $customerId = $this->getRequest()->get("customer_id");
        $orderId = $this->getRequest()->get("order_id");

        $loggedCustomer = $this->getSecurityContext()->getCustomerUser();

        //If cutomer not logged or not same as in order send him to login page
        if ($loggedCustomer === null) {
            return $this->render("custom-recall-login", ["redirect" => $this->getRequest()->getRequestUri()]);
        } elseif ($loggedCustomer->getId() != $customerId) {
            $this->dispatch(TheliaEvents::CUSTOMER_LOGOUT);
            return $this->render("custom-recall-login", ["redirect" => $this->getRequest()->getRequestUri()]);
        }

        //Rebuild new order from order id in link
        try {
            $order = OrderQuery::create()->findOneById($orderId);

            //Fill cart with old order product
            $cart = $this->setCart($order);

            $orderStatusCode = $order->getOrderStatus()->getCode();

            if ($orderStatusCode !== PaymentRecall::CANCEL_STATUS && $orderStatusCode !== PaymentRecall::NOT_PAID_STATUS) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        "This order has already been paid, you can only retry payment for non paid order.",
                        [],
                        PaymentRecall::MODULE_DOMAIN
                    )
                );
            }

            //Apply discount to cart only if old order have not been paid
            $cart->setDiscount($order->getDiscount());
            $cart->save();

            /*
            * Set customer default address as temp address
            * (their will be replaced after order has been created by old order address)
            * And set delivery and payment module
            */
            $this->setDelivery($order);
            $this->setInvoice($order);

            $this->getSession()->set('payment_retry_order_id', $orderId);

            /* check stock not empty */
            if (true === ConfigQuery::checkAvailableStock()) {
                if (null !== $response = $this->checkStockNotEmpty()) {
                    return $response;
                }
            }

            /* check delivery address and module */
            $this->checkValidDelivery();

            /* check invoice address and payment module */
            $this->checkValidInvoice();

            $paymentOrderEvent = $this->getOrderEvent();

            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_PAY, $paymentOrderEvent);

            $placedOrder = $paymentOrderEvent->getPlacedOrder();


            if (null !== $placedOrder && null !== $placedOrder->getId()) {
                /* order has been placed */
                if ($paymentOrderEvent->hasResponse()) {
                    return $paymentOrderEvent->getResponse();
                } else {
                    return $this->generateRedirectFromRoute(
                        'order.placed',
                        [],
                        ['order_id' => $paymentOrderEvent->getPlacedOrder()->getId()]
                    );
                }
            } else {
                /* order has not been placed */
                return $this->generateRedirectFromRoute('cart.view');
            }

        } catch (\Exception $e) {
            return $this->generateRedirectFromRoute(
                "order.failed",
                [],
                [
                    'order_id' => $orderId,
                    'message' => $e->getMessage()
                ]
            );
        }
    }

    protected function setCart(Order $order)
    {
        $orderProducts = $order->getOrderProducts();

        $cart = $this->getSession()->getSessionCart($this->getDispatcher());
        $cartItems = $cart->getCartItemsJoinProductSaleElements();

        //Delete items in cart
        foreach ($cartItems as $cartItem) {
            $cartDelete = new Cart();
            $cartDelete->addCartItem($cartItem);
            $cartEvent = new CartEvent($cartDelete);
            $this->dispatch(TheliaEvents::CART_DELETEITEM, $cartEvent);
        }

        $cart->clearCartItems();

        $orderProductsArray = array();

        //Fill cart with order products
        /** @var \Thelia\Model\OrderProduct $orderProduct */
        foreach ($orderProducts as $orderProduct) {
            $newCartEvent = new CartEvent($cart);
            $newCartEvent->setQuantity($orderProduct->getQuantity());
            $product = ProductQuery::create()->findOneByRef($orderProduct->getProductRef());
            $newCartEvent->setProduct($product->getId());
            $newCartEvent->setNewness(1);
            $newCartEvent->setProductSaleElementsId($orderProduct->getProductSaleElementsId());

            $this->dispatch(TheliaEvents::CART_ADDITEM, $newCartEvent);

            $orderProductsArray[] = $orderProduct;
        }

        return $cart;
    }

    protected function setDelivery(Order $order)
    {
        $this->checkCartNotEmpty();
        //Set default customer address as temp delivery address (changed before payment by old order address)
        $deliveryTempAddress = $order->getCustomer()->getDefaultAddress();
        $deliveryModule = $order->getModuleRelatedByDeliveryModuleId();

        /* check that the delivery module fetches the delivery address area */
        if (AreaDeliveryModuleQuery::create()
                ->filterByAreaId($deliveryTempAddress->getCountry()->getAreaId())
                ->filterByDeliveryModuleId($deliveryModule->getId())
                ->count() == 0) {
            throw new \Exception(
                $this->getTranslator()->trans(
                    "Delivery module cannot be use with selected delivery address",
                    [],
                    PaymentRecall::MODULE_DOMAIN
                )
            );
        }

        /* get postage amount */
        $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

        $postage = OrderPostage::loadFromPostage(
            $moduleInstance->getPostage($deliveryTempAddress->getCountry())
        );

        $orderDeliveryEvent = $this->getOrderEvent();
        $orderDeliveryEvent->setDeliveryAddress($deliveryTempAddress->getId());
        $orderDeliveryEvent->setDeliveryModule($deliveryModule->getId());
        $orderDeliveryEvent->setPostage($postage->getAmount());
        $orderDeliveryEvent->setPostageTax($postage->getAmountTax());
        $orderDeliveryEvent->setPostageTaxRuleTitle($postage->getTaxRuleTitle());

        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_ADDRESS, $orderDeliveryEvent);
        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderDeliveryEvent);
        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_POSTAGE, $orderDeliveryEvent);

        return $orderDeliveryEvent->getDeliveryAddress();
    }

    protected function setInvoice(Order $order)
    {
        $this->checkValidDelivery();

        //Set default customer address as temp invoice address (changed before payment by old order invoice address)
        $invoiceTempAddress = $order->getCustomer()->getDefaultAddress();
        $paymentModule = $order->getModuleRelatedByPaymentModuleId();

        /* check that the invoice address belongs to the current customer */
        if ($invoiceTempAddress->getCustomerId() !== $this->getSecurityContext()->getCustomerUser()->getId()) {
            throw new \Exception(
                $this->getTranslator()->trans(
                    "Invoice address does not belong to the current customer",
                    [],
                    PaymentRecall::MODULE_DOMAIN
                )
            );
        }

        $invoiceOrderEvent = $this->getOrderEvent();
        $invoiceOrderEvent->setInvoiceAddress($invoiceTempAddress->getId());
        $invoiceOrderEvent->setPaymentModule($paymentModule->getId());

        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_INVOICE_ADDRESS, $invoiceOrderEvent);
        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_PAYMENT_MODULE, $invoiceOrderEvent);

        $this->getSession()->setOrder($invoiceOrderEvent->getOrder());

        return $invoiceOrderEvent->getInvoiceAddress();
    }

    protected function checkStockNotEmpty()
    {
        $cart = $this->getSession()->getSessionCart($this->getDispatcher());
        $cartItems = $cart->getCartItems();
        $flagQuantity = 0;
        foreach ($cartItems as $cartItem) {
            $pse = $cartItem->getProductSaleElements();
            if ($pse->getQuantity() <= 0) {
                $flagQuantity = 1;
            }
        }
        if ($flagQuantity == 1) {
            return $this->generateRedirectFromRoute('cart.view');
        } else {
            return null;
        }
    }
}
