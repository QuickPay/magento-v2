<?php

namespace QuickPay\Payment\Plugin\Model\Order\Payment\State;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\State\CommandInterface as BaseCommandInterface;

class CommandInterface
{
    /**
     * Set pending order status on order place
     * see https://github.com/magento/magento2/issues/5860
     *
     * @todo Refactor this when another option becomes available
     *
     * @param BaseCommandInterface $subject
     * @param \Closure $proceed
     * @param OrderPaymentInterface $payment
     * @param $amount
     * @param OrderInterface $order
     * @return mixed
     */
    public function aroundExecute(BaseCommandInterface $subject, \Closure $proceed, OrderPaymentInterface $payment, $amount, OrderInterface $order)
    {
        $result = $proceed($payment, $amount, $order);

        if ($payment->getMethod() === \QuickPay\Payment\Model\Ui\ConfigProvider::CODE) {
            $orderStatus = Order::STATE_NEW;
            if ($orderStatus && $order->getState() == Order::STATE_PROCESSING) {
                $order->setState($orderStatus)
                      ->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_NEW));
            }
        }

        return $result;
    }
}