<?php

namespace QuickPay\Gateway\Observer;
use QuickPay\Gateway\Model\Ui\ConfigProvider;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class CaptureOrderShipmentAfter implements ObserverInterface
{
    const SHIPMENT_AUTO_CAPTURE_XML_PATH     = 'payment/quickpay_gateway/autocapture_shipment';

    /**
     * @var QuickPay\Gateway\Model\Adapter\QuickPayAdapter
     */
    protected $adapter;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->adapter = $adapter;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $autocapture = $this->scopeConfig->getValue(self::SHIPMENT_AUTO_CAPTURE_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if($autocapture) {
            $shipment = $observer->getEvent()->getShipment();
            /** @var \Magento\Sales\Model\Order $order */
            $order = $shipment->getOrder();

            $payment = $order->getPayment();
            if (in_array($payment->getMethod(), [
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
                ]) && $payment->getLastTransId() && $payment->canCapture()) {

                $parts = explode('-', $payment->getLastTransId() ?? '');
                $order = $payment->getOrder();
                $transactionId = $parts[0];
                try {
                    $this->adapter->capture($order, $transactionId, $order->getGrandTotal());
                    $this->adapter->createTransaction($order, $transactionId, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                } catch (LocalizedException $e) {
                    throw new LocalizedException(__($e->getMessage()));
                }
            }
        }
    }
}
