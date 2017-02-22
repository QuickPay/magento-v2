<?php

namespace QuickPay\Payment\Controller\Payment;

use Magento\Sales\Model\Order;
use Zend\Json\Json;

class Callback extends \Magento\Framework\App\Action\Action
{
    const PRIVATE_KEY_XML_PATH           = 'payment/quickpay/private_key';
    const TESTMODE_XML_PATH              = 'payment/quickpay/testmode';
    const TRANSACTION_FEE_LABEL_XML_PATH = 'payment/quickpay/transaction_fee_label';
    const TRANSACTION_FEE_SKU            = 'transaction_fee';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $order;

    /**
     * @var Order\Email\Sender\OrderSender
     */
    protected $orderSender;

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
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->order = $order;
        $this->orderSender = $orderSender;

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

        try {
            $response = Json::decode($body);

            //Fetch private key from config and validate checksum
            $key = $this->scopeConfig->getValue(self::PRIVATE_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $checksum = hash_hmac('sha256', $body, $key);
            $submittedChecksum = $this->getRequest()->getServer('HTTP_QUICKPAY_CHECKSUM_SHA256');

            if ($checksum === $submittedChecksum) {
                //Make sure that payment is accepted
                if ($response->accepted === true) {
                    /**
                     * Load order by incrementId
                     * @var Order $order
                     */
                    $order = $this->order->loadByIncrementId($response->order_id);

                    if (!$order->getId()) {
                        $this->logger->debug('Failed to load order with id: ' . $response->order_id);
                        return;
                    }

                    //Cancel order if testmode is disabled and this is a test payment
                    $testMode = $this->scopeConfig->isSetFlag(self::TESTMODE_XML_PATH,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

                    if (!$testMode && $response->test_mode === true) {
                        $this->logger->debug('Order attempted paid with a test card but testmode is disabled.');
                        if (!$order->isCanceled()) {
                            $order->registerCancellation("Order attempted paid with test card")->save();
                        }
                        return;
                    }

                    //Add transaction fee if set
                    if ($response->fee > 0) {
                        $this->addTransactionFee($order, $response->fee);
                    }

                    //Set order to processing
                    $stateProcessing = \Magento\Sales\Model\Order::STATE_PROCESSING;

                    if ($order->getState() !== $stateProcessing) {
                        $order->setState($stateProcessing)
                            ->setStatus($order->getConfig()->getStateDefaultStatus($stateProcessing))
                            ->save();
                    }

                    //Send order email
                    if (!$order->getEmailSent()) {
                        $this->sendOrderConfirmation($order);
                    }
                }
            } else {
                $this->logger->debug('Checksum mismatch');
                return;
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Add transaction fee as virtual product
     *
     * @param Order $order
     * @param $fee
     */
    private function addTransactionFee(Order $order, $fee)
    {
        try {
            foreach ($order->getAllItems() as $orderItem) {
                if ($orderItem->getSku() === self::TRANSACTION_FEE_SKU) {
                    return;
                }
            }

            /** @var \Magento\Sales\Model\Order\Item $item */
            $item = $this->_objectManager->create(\Magento\Sales\Model\Order\Item::class);
            $item->setSku(self::TRANSACTION_FEE_SKU);

            //Calculate fee price
            $feeBase = (float)$fee / 100;
            $feeTotal = $order->getStore()->getBaseCurrency()->convert($feeBase, $order->getOrderCurrencyCode());

            $name = $this->scopeConfig->getValue(self::TRANSACTION_FEE_LABEL_XML_PATH);
            $item->setName($name);
            $item->setBaseCost($feeBase);
            $item->setBasePrice($feeBase);
            $item->setBasePriceInclTax($feeBase);
            $item->setBaseOriginalPrice($feeBase);
            $item->setBaseRowTotal($feeBase);
            $item->setBaseRowTotalInclTax($feeBase);
            $item->setCost($feeTotal);
            $item->setPrice($feeTotal);
            $item->setPriceInclTax($feeTotal);
            $item->setOriginalPrice($feeTotal);
            $item->setRowTotal($feeTotal);
            $item->setRowTotalInclTax($feeTotal);
            $item->setProductType(\Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL);
            $item->setIsVirtual(1);
            $item->setQtyOrdered(1);
            $item->setStoreId($order->getStoreId());
            $item->setOrderId($order->getId());

            $order->addItem($item);

            $order = $this->updateTotals($order, $feeBase, $feeTotal);
            $order->save();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Update order totals after adding transaction fee
     *
     * @param Order $order
     * @param $feeBase
     * @param $feeTotal
     */
    private function updateTotals($order, $feeBase, $feeTotal)
    {
        $order->setBaseGrandTotal($order->getBaseGrandTotal() + $feeBase);
        $order->setBaseSubtotal($order->getBaseSubtotal() + $feeBase);
        $order->setGrandTotal($order->getGrandTotal() + $feeTotal);
        $order->setSubtotal($order->getSubtotal() + $feeTotal);

        return $order;
    }

    /**
     * Send order confirmation email
     *
     * @param \Magento\Sales\Model\Order $order
     */
    private function sendOrderConfirmation($order)
    {
        try {
            $this->orderSender->send($order);
            $order->addStatusHistoryComment(__('Order confirmation email sent to customer'))
                  ->setIsCustomerNotified(true)
                  ->save();
        } catch (\Exception $e) {
            $order->addStatusHistoryComment(__('Failed to send order confirmation email: %s', $e->getMessage()))
                  ->setIsCustomerNotified(false)
                  ->save();
        }
    }
}