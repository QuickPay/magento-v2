<?php

namespace QuickPay\Gateway\Controller\Payment;

class Returns extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $order;

    /**
     * @var \QuickPay\Gateway\Helper\Order
     */
    protected $orderHelper;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \QuickPay\Gateway\Helper\Order $orderHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \QuickPay\Gateway\Helper\Order $orderHelper
    )
    {
        $this->order = $order;
        $this->orderHelper = $orderHelper;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    /**
     * @return bool|\Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        if($incrementId = $this->getRequest()->getParam('order')){
            return $this->order->loadByIncrementId($incrementId);
        }

        return false;
    }

    /**
     * Redirect to to checkout success
     *
     * @return void
     */
    public function execute()
    {
        $area = $this->getRequest()->getParam('area');
        if($area == \Magento\Framework\App\Area::AREA_ADMINHTML){
            $this->messageManager->addSuccess(__('Thank you for your purchase. You will soon receive a confirmation by email.'));
        }

        $order = $this->getOrder();
        if ($order
            && $order->getId()
            && $this->orderHelper->getOrderIsQuickpay($order)) {
            $this->_getCheckout()->setLastOrderId($order->getId());
            $this->_getCheckout()->setLastQuoteId($order->getQuoteId());
            $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
            $this->_getCheckout()->setLastRealOrderId($order->getIncrementId());

            $this->_redirect('checkout/onepage/success');
        } else {
            $this->messageManager->addError(__('Order not found.'));

            $this->_redirect('checkout/cart');
        }
    }
}
