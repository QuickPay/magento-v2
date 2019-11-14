<?php

namespace QuickPay\Gateway\Controller\Payment;

use Magento\Sales\Model\Order;
use Zend\Json\Json;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Callback extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    const PRIVATE_KEY_XML_PATH            = 'payment/quickpay_gateway/private_key';
    const TESTMODE_XML_PATH               = 'payment/quickpay_gateway/testmode';
    const TRANSACTION_FEE_LABEL_XML_PATH  = 'payment/quickpay_gateway/transaction_fee_label';
    const AUTOCAPTURE_XML_PATH            = 'payment/quickpay_gateway/autocapture';
    const TRANSACTION_FEE_SKU             = 'transaction_fee';
    const SEND_INVOICE_EMAIL_XML_PATH     = 'payment/quickpay_gateway/send_invoice_email';

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
     * @var QuickPay\Gateway\Model\Adapter\QuickPayAdapter
     */
    protected $adapter;

    /**
     * @var Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $dir;

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
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\App\Filesystem\DirectoryList $dir
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->dir = $dir;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->adapter = $adapter;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;

        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler($this->dir->getRoot().'/var/log/quickpay.log'));

        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ? bool
    {
        return true;
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
        $this->logger->debug('CALLBACK');
        $body = $this->getRequest()->getContent();
        try {
            $response = Json::decode($body);
            $this->logger->debug(json_encode((array)$response));

            //Fetch private key from config and validate checksum
            $key = $this->scopeConfig->getValue(self::PRIVATE_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $autocapture = $this->scopeConfig->getValue(self::AUTOCAPTURE_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $invoiceEmailSend = $this->scopeConfig->getValue(self::SEND_INVOICE_EMAIL_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $checksum = hash_hmac('sha256', $body, $key);
            $submittedChecksum = $this->getRequest()->getServer('HTTP_QUICKPAY_CHECKSUM_SHA256');

            if ($checksum === $submittedChecksum) {
                //Make sure that payment is accepted
                $operation = end($response->operations);

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

                    $payment = $order->getPayment();
                    if($payment->getLastTransId()){
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

                    //Add card metadata
                    $payment->setIsTransactionClosed(0);
                    if (isset($response->metadata->type) && $response->metadata->type === 'card') {
                        $payment->setCcType($response->metadata->brand);
                        $payment->setCcLast4('xxxx-' . $response->metadata->last4);
                        $payment->setCcExpMonth($response->metadata->exp_month);
                        $payment->setCcExpYear($response->metadata->exp_year);

                        $payment->setAdditionalInformation('Transaction ID', $response->id);
                        $payment->setAdditionalInformation('Type', $response->metadata->type);
                        $payment->setAdditionalInformation('Card Type', $response->metadata->brand);
                        $payment->setAdditionalInformation('Card Number', 'XXXX-' . $response->metadata->last4);
                        $payment->setAdditionalInformation('Card Expiration Date', date('Y-m', strtotime($response->metadata->exp_year.'-'.$response->metadata->exp_month)));
                        $payment->setAdditionalInformation('Currency', $response->currency);

                    } else {
                        if (isset($response->metadata->payment_method)) {
                            $payment->setCcType($response->metadata->payment_method);
                            $payment->setAdditionalInformation('Transaction ID', $response->id);
                            $payment->setAdditionalInformation('Type', $response->metadata->payment_method);
                        }
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

                    $this->adapter->createTransaction($order, $response->id, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);

                    if($autocapture) {
                        //Generate invoice
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                        $invoice->register();
                        $invoice->getOrder()->setCustomerNoteNotify(false);
                        $invoice->getOrder()->setIsInProcess(true);

                        if($invoiceEmailSend){
                            $this->invoiceSender->send($invoice);
                        }

                        $transactionSave = $this->_objectManager->create(
                            \Magento\Framework\DB\Transaction::class
                        )->addObject(
                            $invoice
                        )->addObject(
                            $invoice->getOrder()
                        );
                        $transactionSave->save();
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
