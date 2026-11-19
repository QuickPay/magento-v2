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
     * @var \Magento\Sales\Model\ResourceModel\Order
     */
    protected $orderResource;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Sales\Model\ResourceModel\Order $orderResource
     */

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\ResourceModel\Order $orderResource
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->orderResource = $orderResource;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment\Interceptor $payment */
        $payment = $observer->getPayment();

        if (in_array($payment->getMethod(),[
	        ConfigProvider::CODE,
	        ConfigProvider::CODE_KLARNA,
	        ConfigProvider::CODE_MOBILEPAY,
	        ConfigProvider::CODE_VIPPS,
	        ConfigProvider::CODE_PAYPAL,
	        ConfigProvider::CODE_VIABILL,
	        ConfigProvider::CODE_SWISH,
	        ConfigProvider::CODE_TRUSTLY,
            ConfigProvider::CODE_ANYDAY,
            ConfigProvider::CODE_APPLEPAY,
            ConfigProvider::CODE_GOOGLEPAY
        ])) {
            $emailSend = $this->scopeConfig->getValue(self::SEND_ORDER_EMAIL_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $order = $payment->getOrder();
            if($emailSend) {
                /** @var \Magento\Sales\Model\Order $order */
                $order->setCanSendNewEmailFlag(true)
                    ->setIsCustomerNotified(true);
                $this->orderResource->save($order);
            } else {
                /** @var \Magento\Sales\Model\Order $order */
                $order->setCanSendNewEmailFlag(false)
                    ->setIsCustomerNotified(false);
                $this->orderResource->save($order);
            }
        }
    }
}
