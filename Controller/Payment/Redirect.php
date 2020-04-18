<?php

namespace QuickPay\Gateway\Controller\Payment;

class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \QuickPay\Gateway\Model\Adapter\QuickPayAdapter
     */
    protected $_adapter;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * Class constructor
     * @param \Magento\Framework\App\Action\Context              $context
     * @param \Psr\Log\LoggerInterface                           $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Api\Data\OrderInterface $orderRepository,
        \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory
    )
    {
        $this->_logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->_adapter = $adapter;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_checkoutSession;
    }

    /**
     * @return bool|\Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        if ($this->_getCheckout()->getLastRealOrderId()) {
            $order = $this->_orderFactory->create()->loadByIncrementId($this->_getCheckout()->getLastRealOrderId());
            return $order;
        }
        return false;
    }

    /**
     * Redirect to to QuickPay
     *
     * @return string
     */
    public function execute()
    {
        try {
            $order = $this->getOrder();

            if($order) {
                //Save quote id in session for retrieval later
                $this->_getCheckout()->setQuickpayQuoteId($this->_getCheckout()->getQuoteId());

                $response = $this->_adapter->CreatePaymentLink($order);

                if (isset($response['message'])) {
                    $this->messageManager->addError($response['message']);
                    $this->_getCheckout()->restoreQuote();
                    $this->_redirect($this->_redirect->getRefererUrl());
                } else {
                    $this->_redirect($response['url']);
                }
            } else {
                $this->messageManager->addError('Error');
                $this->_getCheckout()->restoreQuote();
                $this->_redirect($this->_redirect->getRefererUrl());
            }
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong, please try again later'));
            $this->_getCheckout()->restoreQuote();
            $this->_redirect('checkout/cart');
        }
    }
}
