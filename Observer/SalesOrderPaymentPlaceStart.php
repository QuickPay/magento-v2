<?php

namespace QuickPay\Payment\Observer;

use Magento\Framework\Event\Observer;

class SalesOrderPaymentPlaceStart implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Prevent order emails from being sent prematurely
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment\Interceptor $payment */
        $payment = $observer->getPayment();

        if ($payment->getMethod() === \QuickPay\Payment\Model\Ui\ConfigProvider::CODE) {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $payment->getOrder();
            $order->setCanSendNewEmailFlag(false)
                  ->setIsCustomerNotified(false)
                  ->save();
        }
    }
}