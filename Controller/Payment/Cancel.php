<?php

namespace QuickPay\Gateway\Controller\Payment;

class Cancel extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $order;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \QuickPay\Gateway\Helper\Order
     */
    protected $orderHelper;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */
    protected $orderResource;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote
     */
    protected $quoteResource;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \QuickPay\Gateway\Helper\Order $orderHelper
     * @param \Magento\Sales\Model\ResourceModel\Order $orderResource
     * @param \Magento\Quote\Model\ResourceModel\Quote $quoteResource
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \QuickPay\Gateway\Helper\Order $orderHelper,
        \Magento\Sales\Model\ResourceModel\Order $orderResource,
        \Magento\Quote\Model\ResourceModel\Quote $quoteResource
    )
    {
        $this->order = $order;
        $this->quoteFactory = $quoteFactory;
        $this->orderHelper = $orderHelper;
        $this->orderResource = $orderResource;
        $this->quoteResource = $quoteResource;
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
     * Customer canceled payment on gateway side.
     *
     * @return void
     */
    public function execute()
    {
        $area = $this->getRequest()->getParam('area');
        $order = $this->getOrder();
        if ($order
            && $order->getId()
            && !$order->isCanceled()
            && $order->getState() == \Magento\Sales\Model\Order::STATE_NEW
            && $this->orderHelper->getOrderIsQuickpay($order)) {

            if($area == \Magento\Framework\App\Area::AREA_ADMINHTML){
                $this->messageManager->addSuccess(__('Your order has been canceled.'));
            } else {
                $quote = $this->quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
                $order->registerCancellation('');
                $this->orderResource->save($order);

                $quote->setIsActive(true)->setReservedOrderId(null);
                $this->quoteResource->save($quote);
                $this->_getCheckout()->replaceQuote($quote);
            }

        } else {
            $this->messageManager->addError(__('Order not found.'));
        }
        $this->_redirect('checkout/cart');
    }
}