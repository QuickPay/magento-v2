<?php

namespace QuickPay\Gateway\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

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
        if ($payment->getMethod() === \QuickPay\Gateway\Model\Ui\ConfigProvider::CODE) {
            $parts = explode('-', $payment->getLastTransId());
            $order = $payment->getOrder();
            $transaction = $parts[0];

            try {
                $this->adapter->capture($order,$transaction, $order->getGrandTotal());
            } catch (LocalizedException $e) {
                throw new LocalizedException(__($e->getMessage()));
            }
        }
    }
}