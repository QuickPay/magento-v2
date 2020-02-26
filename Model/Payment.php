<?php

namespace QuickPay\Gateway\Model;

/**
 * Pay In Store payment method model
 */
class Payment extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'quickpay_gateway';

    /**
     * @var string
     */
    protected $_title = 'QuickPay';

    /**
     * Availability option
     *
     * @var bool
     */

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_canCapture              = true;

    /**
     * @var bool
     */
    protected $_canRefund               = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    protected $_isGateway               = true;

    /**
     * @var bool
     */
    protected $_canUseForMultishipping  = false;

    /**
     * @param $lan
     * @return mixed
     */
    public function calcLanguage($lan)
    {
        $map_codes = array (
            'nb' => 'no',
            'nn' => 'no'
        );

        $splitted = explode('_', $lan);
        $lang = $splitted[0];
        if ( isset ( $map_codes[$lang] ) ) return $map_codes[$lang];
        return $lang;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }
        return $this;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $adapter = $objectManager->get(\QuickPay\Gateway\Model\Adapter\QuickPayAdapter::class);
        $parts = explode('-',$payment->getTransactionId());
        $transaction = $parts[0];

        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
        }

        $adapter->capture($transaction, $amount);

        return $this;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $adapter = $objectManager->get(\QuickPay\Gateway\Model\Adapter\QuickPayAdapter::class);
        $parts = explode('-',$payment->getTransactionId());
        $transaction = $parts[0];

        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }

        $adapter->refund($transaction, $amount);

        return $this;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return $this
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $adapter = $objectManager->get(\QuickPay\Gateway\Model\Adapter\QuickPayAdapter::class);
        $parts = explode('-',$payment->getTransactionId());
        $transaction = $parts[0];

        $adapter->cancel($transaction);

        return $this;
    }
}
