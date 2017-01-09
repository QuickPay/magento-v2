<?php

namespace QuickPay\Payment\Controller\Payment;

class Callback extends \Magento\Framework\App\Action\Action
{
    const PRIVATE_KEY_XML_PATH = 'payment/quickpay/private_key';
    const TESTMODE_XML_PATH    = 'payment/quickpay/testmode';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $_order;

    /**
     * Class constructor
     * @param \Magento\Framework\App\Action\Context              $context
     * @param \Psr\Log\LoggerInterface                           $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\Data\OrderInterface $order
    )
    {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_order = $order;

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
     * Handle callback from QuickPay
     *
     * @return string
     */
    public function execute()
    {
        $body = $this->getRequest()->getContent();
        $response = json_decode($body);

        //Determined if valid json
        if ($response !== null && json_last_error() === JSON_ERROR_NONE) {
            //Fetch private key from config and validate checksum
            $key = $this->_scopeConfig->getValue(self::PRIVATE_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $checksum = hash_hmac('sha256', $body, $key);
            $submittedChecksum = $this->getRequest()->getServer('HTTP_QUICKPAY_CHECKSUM_SHA256');

            if ($checksum === $submittedChecksum) {
                //Attempt to load order by incrementId
                $order = $this->_order->loadByIncrementId($response->order_id);

                if (! $order->getId()) {
                    $this->_logger->debug('Failed to load order with id: '. $response->order_id);
                    return;
                }

                //Cancel order if testmode is disabled and this is a test payment
                $testMode = $this->_scopeConfig->isSetFlag(self::TESTMODE_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

                if (! $testMode && $response->test_mode === true) {
                    $this->_logger->debug('Order attempted paid with a test card but testmode is disabled.');
                    if (! $order->isCanceled()) {
                        $order->registerCancellation("Order attempted paid with test card")->save();
                    }
                    return;
                }

                //Set order to processing
                $stateProcessing = \Magento\Sales\Model\Order::STATE_PROCESSING;

                 if ($order->getState() !== $stateProcessing) {
                     $order->setState($stateProcessing)
                         ->setStatus($order->getConfig()->getStateDefaultStatus($stateProcessing))
                         ->save();
                 }

                $this->_logger->debug(var_export($response, true));
            } else {
                $this->_logger->debug('Checksum mismatch');
                return;
            }
        } else {
            $this->_logger->debug('Failed to decode json, body received: ' . var_export($this->getRequest()->getContent(), true));
        }
    }
}