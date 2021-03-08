<?php

namespace QuickPay\Gateway\Observer;
use QuickPay\Gateway\Model\Ui\ConfigProvider;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

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
        if (in_array($payment->getMethod(),[ConfigProvider::CODE,ConfigProvider::CODE_KLARNA,ConfigProvider::CODE_MOBILEPAY])) {
            $parts = explode('-', $payment->getLastTransId());
            $order = $payment->getOrder();
            $transaction = $parts[0];

            try {
                $this->adapter->cancel($order, $transaction);
            } catch (LocalizedException $e) {
                throw new LocalizedException(__($e->getMessage()));
            }
        }
    }
}