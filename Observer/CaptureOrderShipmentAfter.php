<?php

namespace QuickPay\Gateway\Observer;
use QuickPay\Gateway\Model\Ui\ConfigProvider;
use Magento\Framework\Event\ObserverInterface;

class CaptureOrderShipmentAfter implements ObserverInterface
{
    /**
     * @var QuickPay\Gateway\Model\Adapter\QuickPayAdapter
     */
    protected $adapter;

    public function __construct(
        \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter
    )
    {
        $this->adapter = $adapter;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $shipment->getOrder();

        $payment = $order->getPayment();
        if (in_array($payment->getMethod(),[ConfigProvider::CODE,ConfigProvider::CODE_KLARNA,ConfigProvider::CODE_MOBILEPAY])) {
            $parts = explode('-', $payment->getLastTransId());
            $order = $payment->getOrder();
            $transaction = $parts[0];

            $this->adapter->capture($order,$transaction, $order->getGrandTotal());
        }
    }
}