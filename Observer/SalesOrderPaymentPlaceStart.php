<?php

namespace QuickPay\Gateway\Observer;
use QuickPay\Gateway\Model\Ui\ConfigProvider;
use Magento\Framework\Event\Observer;

class SalesOrderPaymentPlaceStart implements \Magento\Framework\Event\ObserverInterface
{
    const SEND_ORDER_EMAIL_XML_PATH     = 'payment/quickpay_gateway/send_order_email';
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Prevent order emails from being sent prematurely
     *
     * @param Observer $observer
     * @return void
     */

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment\Interceptor $payment */
        $payment = $observer->getPayment();

        if (in_array($payment->getMethod(),[ConfigProvider::CODE,ConfigProvider::CODE_KLARNA,ConfigProvider::CODE_MOBILEPAY])) {
            $emailSend = $this->scopeConfig->getValue(self::SEND_ORDER_EMAIL_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $order = $payment->getOrder();
            if($emailSend) {
                /** @var \Magento\Sales\Model\Order $order */
                $order->setCanSendNewEmailFlag(true)
                    ->setIsCustomerNotified(true)
                    ->save();
            } else {
                /** @var \Magento\Sales\Model\Order $order */
                $order->setCanSendNewEmailFlag(false)
                    ->setIsCustomerNotified(false)
                    ->save();
            }
        }
    }
}