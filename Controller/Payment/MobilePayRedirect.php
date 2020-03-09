<?php

namespace QuickPay\Gateway\Controller\Payment;

class MobilePayRedirect extends \Magento\Framework\App\Action\Action
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
     * @var \QuickPay\Gateway\Helper\Order
     */
    protected $orderHelper;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var
     */
    protected $_resultJsonFactory;

    /**
     * @var \QuickPay\Gateway\Model\Carrier\Shipping
     */
    protected $shipping;

    /**
     * MobilePayRedirect constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Sales\Api\Data\OrderInterface $orderRepository
     * @param \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter
     * @param \QuickPay\Gateway\Helper\Order $orderHelper
     * @param \Magento\Checkout\Model\Sessio $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Api\Data\OrderInterface $orderRepository,
        \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter,
        \QuickPay\Gateway\Helper\Order $orderHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \QuickPay\Gateway\Model\Carrier\Shipping $shipping
    )
    {
        $this->_logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->_adapter = $adapter;
        $this->orderHelper = $orderHelper;
        $this->checkoutSession = $checkoutSession;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->shipping = $shipping;

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
     * Redirect to to QuickPay MobilePay
     *
     * @return string
     */
    public function execute()
    {
        try {
            $response = [];
            $params = $this->getRequest()->getParams();
            $resultJson = $this->_resultJsonFactory->create();
            if(empty($params['shipping'])){
                $response['error'] = __('Please select shipping method');
                return $resultJson->setData($response);
            }

            $shippingData = $this->shipping->getMethodByCode($params['shipping']);
            if(empty($shippingData)){
                $response['error'] = __('Please select shipping method');
                return $resultJson->setData($response);
            }

            $quote = $this->_getCheckout()->getQuote();

            //$quote->reserveOrderId()->save();

            $result = $this->orderHelper->createOrderByQuote($quote, $shippingData);

            if(isset($result['error'])){
                $this->messageManager->addError($result['error']);
                return $this->_redirect('checkout/cart');
            }

            $quote->setIsActive(0)->save();

            if ($result->getId()) {
                $this->_getCheckout()
                    ->setLastRealOrderId($result->getIncrementId())
                    ->setLastSuccessQuoteId($quote->getId())
                    ->setLastQuoteId($quote->getId())
                    ->setLastOrderId($result->getId());
            }

            //Save quote id in session for retrieval later
            $this->_getCheckout()->setQuickpayQuoteId($this->_getCheckout()->getQuoteId());

            $shippingServiceData = [
                'code' => $params['shipping'],
                'price' => $shippingData['price']
            ];
            $responseService = $this->_adapter->CreateMobilePayPaymentLink($quote, $shippingServiceData);

            if(isset($responseService['message'])){
                //$this->messageManager->addError($response['message']);
                $this->_getCheckout()->restoreQuote();
                $response['error'] = $responseService['message'];
                return $resultJson->setData($response);
                //$this->_redirect($this->_redirect->getRefererUrl());
            } else {
                $response['url'] = $responseService['url'];
                return $resultJson->setData($response);
                //$this->_redirect($response['url']);
            }
        } catch (\Exception $e) {
            //$this->messageManager->addException($e, __('Something went wrong, please try again later'));
            $this->_getCheckout()->restoreQuote();
            $response['error'] = $responseService['message'];
            return $resultJson->setData($response);
            //$this->_redirect('checkout/cart');
        }
    }
}
