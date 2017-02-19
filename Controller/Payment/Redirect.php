<?php

namespace QuickPay\Payment\Controller\Payment;

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
     * Class constructor
     * @param \Magento\Framework\App\Action\Context              $context
     * @param \Psr\Log\LoggerInterface                           $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Api\Data\OrderInterface $orderRepository
    )
    {
        $this->_logger = $logger;
        $this->orderRepository = $orderRepository;

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
            $paymentLink = $order->getPayment()->getAdditionalInformation(\QuickPay\Payment\Gateway\Response\PaymentLinkHandler::PAYMENT_LINK);

            //TODO: Refactor this to use repository?
            $order->setState(\Magento\Sales\Model\Order::STATE_NEW)
                ->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_NEW))
                ->addStatusHistoryComment(__("Order placed and pending payment"))
                ->save();

            return $this->_redirect($paymentLink);
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong, please try again later'));
            $this->_logger->critical($e);
            $this->_getCheckout()->restoreQuote();
            $this->_redirect('checkout/cart');
        }
    }
}