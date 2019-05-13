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
     * Class constructor
     * @param \Magento\Framework\App\Action\Context              $context
     * @param \Psr\Log\LoggerInterface                           $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Api\Data\OrderInterface $orderRepository,
        \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter
    )
    {
        $this->_logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->_adapter = $adapter;

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
     * Redirect to to QuickPay
     *
     * @return string
     */
    public function execute()
    {
        try {

            $order = $this->_getCheckout()->getLastRealOrder();

            //Save quote id in session for retrieval later
            $this->_getCheckout()->setQuickpayQuoteId($this->_getCheckout()->getQuoteId());

            $response = $this->_adapter->CreatePaymentLink($order);

            if(isset($response['message'])){
                $this->messageManager->addError($response['message']);
                $this->_getCheckout()->restoreQuote();
                $this->_redirect($this->_redirect->getRefererUrl());
            } else {
                $this->_redirect($response['url']);
            }
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong, please try again later'));
            $this->_getCheckout()->restoreQuote();
            $this->_redirect('checkout/cart');
        }
    }
}
