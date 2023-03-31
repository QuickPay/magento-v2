<?php

namespace QuickPay\Gateway\Controller\Payment;

class Returns extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
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
        $order = null;
        if($protectCode = $this->getRequest()->getParam('order')){
            $searchCriteria = $this->_searchCriteriaBuilder->addFilter('protect_code', $protectCode)
                ->setPageSize(1)
                ->setCurrentPage(1)
                ->create();
            $resultOrders = $this->_orderRepository->getList($searchCriteria);

            if ($resultOrders->getTotalCount() > 0) {
                $orders = $resultOrders->getItems();
                $order = current($orders);
            }
        }

        return $order;
    }

    /**
     * Redirect to to checkout success
     *
     * @return void
     */
    public function execute()
    {
        $area = $this->getRequest()->getParam('area');
        $order = $this->getOrder();
        if($area == 'admin'){
            $this->messageManager->addSuccess(__('Thank you for your purchase. You will soon receive a confirmation by email.'));
        }

        if($order && $order->getId()){
            $this->_getCheckout()->setLastOrderId($order->getId());
            $this->_getCheckout()->setLastQuoteId($order->getQuoteId());
            $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
            $this->_getCheckout()->setLastRealOrderId($order->getIncrementId());

            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_redirect('checkout/cart');
        }
    }
}
