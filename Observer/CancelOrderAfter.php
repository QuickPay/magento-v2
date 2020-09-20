<?php

namespace QuickPay\Gateway\Observer;

use Magento\Framework\Event\ObserverInterface;

class CancelOrderAfter implements ObserverInterface
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

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $payment = $order->getPayment();
        if ($payment->getMethod() === \QuickPay\Gateway\Model\Ui\ConfigProvider::CODE) {
            $parts = explode('-', $payment->getLastTransId());
            $order = $payment->getOrder();
            $transaction = $parts[0];

            $this->adapter->cancel($order, $transaction);
        }
    }
}