<?php
namespace QuickPay\Payment\Helper;

/**
 * Checkout workflow helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const PAYMENT_METHODS_XML_PATH = 'payment/quickpay/payment_methods';
    const SPECIFIED_PAYMENT_METHOD_XML_PATH = 'payment/quickpay/payment_method_specified';

    /**
     * Get payment methods
     *
     * @return string
     */
    public function getPaymentMethods()
    {
        $payment_methods = $this->scopeConfig->getValue(self::PAYMENT_METHODS_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        /**
         * Get specified payment methods
         */
        if ($payment_methods === 'specified') {
            $payment_methods = $this->scopeConfig->getValue(self::SPECIFIED_PAYMENT_METHOD_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }

        return $payment_methods;
    }
}
