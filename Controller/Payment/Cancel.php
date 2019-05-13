<?php

namespace QuickPay\Gateway\Controller\Payment;

class Cancel extends \Magento\Framework\App\Action\Action
{
    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    /**
     * Customer canceled payment on gateway side.
     *
     * @return void
     */
    public function execute()
    {
        $order = $this->_getCheckout()->getLastRealOrder();
        if ($order->getId() && ! $order->isCanceled()) {
            $order->registerCancellation('')->save();
        }

        $this->_getCheckout()->restoreQuote();

        $this->_redirect('checkout', ['_fragment' => 'payment']);
    }
}