<?php
namespace QuickPay\Gateway\Model\Adapter;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use QuickPay\QuickPay;
use Zend_Locale;

/**
 * Class QuickPayAdapter
 */
class QuickPayAdapter
{
    const PUBLIC_KEY_XML_PATH      = 'payment/quickpay_gateway/apikey';
    const TRANSACTION_FEE_XML_PATH = 'payment/quickpay_gateway/transaction_fee';
    const AUTOCAPTURE_XML_PATH = 'payment/quickpay_gateway/autocapture';
    const TEXT_ON_STATEMENT_XML_PATH = 'payment/quickpay_gateway/text_on_statement';
    const PAYMENT_METHODS_XML_PATH = 'payment/quickpay_gateway/payment_methods';
    const SPECIFIED_PAYMENT_METHOD_XML_PATH = 'payment/quickpay_gateway/payment_method_specified';
    const BRANDING_ID_XML_PATH = 'payment/quickpay_gateway/branding_id';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $url;

    /**
     * @var ResolverInterface
     */
    protected $resolver;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $dir;

    /**
     * QuickPayAdapter constructor.
     *
     * @param LoggerInterface $logger
     * @param UrlInterface $url
     * @param ScopeConfigInterface $scopeConfig
     * @param ResolverInterface $resolver
     */
    public function __construct(
        LoggerInterface $logger,
        UrlInterface $url,
        ScopeConfigInterface $scopeConfig,
        ResolverInterface $resolver,
        OrderRepositoryInterface $orderRepository,
        BuilderInterface $transactionBuilder,
        TransactionRepositoryInterface $transactionRepository,
        DirectoryList $dir
    )
    {
        $this->logger = $logger;
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
        $this->resolver = $resolver;
        $this->orderRepository = $orderRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->transactionRepository = $transactionRepository;
        $this->dir = $dir;

        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler($this->dir->getRoot().'/var/log/quickpay.log'));
    }

    /**
     * create payment link
     *
     * @param array $attributes
     * @return array|bool
     */
    public function CreatePaymentLink($order)
    {
        try {
            $response = [];
            $this->logger->debug('CREATE PAYMENT');

            $api_key = $this->scopeConfig->getValue(self::PUBLIC_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $client = new QuickPay(":{$api_key}");

            $form = [
                'order_id' => $order->getIncrementId(),
                'currency' => $order->getOrderCurrency()->ToString(),
            ];

            if ($textOnStatement = $this->scopeConfig->getValue(self::TEXT_ON_STATEMENT_XML_PATH)) {
                $form['text_on_statement'] = $textOnStatement;
            }

            $shippingAddress = $order->getShippingAddress();
            $form['shipping_address'] = [];
            $form['shipping_address']['name'] = $shippingAddress->getFirstName() . " " . $shippingAddress->getLastName();
            $form['shipping_address']['street'] = $shippingAddress->getStreetLine1();
            $form['shipping_address']['city'] = $shippingAddress->getCity();
            $form['shipping_address']['zip_code'] = $shippingAddress->getPostcode();
            $form['shipping_address']['region'] = $shippingAddress->getRegionCode();
            $form['shipping_address']['country_code'] = Zend_Locale::getTranslation($shippingAddress->getCountryId(), 'Alpha3ToTerritory');
            $form['shipping_address']['phone_number'] = $shippingAddress->getTelephone();
            $form['shipping_address']['email'] = $shippingAddress->getEmail();

            $form['shipping'] = [
                'amount' => $order->getShippingInclTax() * 100
            ];

            $billingAddress = $order->getShippingAddress();
            $form['invoice_address'] = [];
            $form['invoice_address']['name'] = $billingAddress->getFirstName() . " " . $billingAddress->getLastName();
            $form['invoice_address']['street'] = implode(' ', $billingAddress->getStreet());
            $form['invoice_address']['city'] = $billingAddress->getCity();
            $form['invoice_address']['zip_code'] = $billingAddress->getPostcode();
            $form['invoice_address']['region'] = $billingAddress->getRegionCode();
            $form['invoice_address']['country_code'] = Zend_Locale::getTranslation($billingAddress->getCountryId(), 'Alpha3ToTerritory');
            $form['invoice_address']['phone_number'] = $billingAddress->getTelephone();
            $form['invoice_address']['email'] = $billingAddress->getEmail();

            //Build basket array
            $form['basket'] = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $form['basket'][] = [
                    'qty'        => (int) $item->getQtyOrdered(),
                    'item_no'    => $item->getSku(),
                    'item_name'  => $item->getName(),
                    'item_price' => (int) ($item->getBasePriceInclTax() * 100),
                    'vat_rate'   => $item->getTaxPercent() / 100,
                ];
            }

            $payments = $client->request->post('/payments', $form);

            $paymentArray = $payments->asArray();

            $this->logger->debug(json_encode($paymentArray));

            if(!empty($paymentArray['error_code'])){
                $response['message'] = $paymentArray['message'];
                return $response;
            }

            $paymentId = $paymentArray['id'];

            $parameters = [
                "amount"             => $order->getTotalDue() * 100,
                "continueurl"        => $this->url->getUrl('quickpaygateway/payment/returns'),
                "cancelurl"          => $this->url->getUrl('quickpaygateway/payment/cancel'),
                "callbackurl"        => $this->url->getUrl('quickpaygateway/payment/callback'),
                "customer_email"     => $order->getCustomerEmail(),
                "autocapture"        => $this->scopeConfig->isSetFlag(self::AUTOCAPTURE_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                "payment_methods"    => $this->getPaymentMethods(),
                "branding_id"        => $this->scopeConfig->getValue(self::BRANDING_ID_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                "language"           => $this->getLanguage(),
                "auto_fee"           => $this->scopeConfig->isSetFlag(self::TRANSACTION_FEE_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            ];

            //Create payment link and return payment id
            $paymentLink = $client->request->put(sprintf('/payments/%s/link', $paymentId), $parameters)->asArray();

            $this->logger->debug(json_encode($paymentLink));

            if(!empty($paymentLink['error_code'])){
                $response['message'] = $paymentLink['message'];

                return $response;
            }

            $response['url'] = $paymentLink['url'];

            return $response;
        } catch (\Exception $e) {

            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    /**
     * Capture payment
     *
     * @param array $attributes
     * @return array|bool
     */
    public function capture($transaction, $ammount)
    {
        try {
            $this->logger->debug("Capture payment");

            $api_key = $this->scopeConfig->getValue(self::PUBLIC_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $client = new QuickPay(":{$api_key}");

            $form = [
                'id' => $transaction,
                'amount' => $ammount * 100,
            ];

            $payments = $client->request->post("/payments/{$transaction}/capture", $form);
            $paymentArray = $payments->asArray();

            $this->logger->debug(json_encode($paymentArray));

            return $paymentArray;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    /**
     * Cancel payment
     *
     * @param array $attributes
     * @return array|bool
     */
    public function cancel($transaction)
    {
        $this->logger->debug("Cancel payment");
        try {
            $api_key = $this->scopeConfig->getValue(self::PUBLIC_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $client = new QuickPay(":{$api_key}");

            $form = [
                'id' => $transaction,
            ];

            $payments = $client->request->post("/payments/{$transaction}/cancel", $form);
            $paymentArray = $payments->asArray();

            $this->logger->debug(json_encode($paymentArray));

            return $paymentArray;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    /**
     * Refund payment
     *
     * @param array $attributes
     * @return array|bool
     */
    public function refund($transaction, $ammount)
    {
        $this->logger->debug("Refund payment");

        try {
            $api_key = $this->scopeConfig->getValue(self::PUBLIC_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $client = new QuickPay(":{$api_key}");

            $form = [
                'id' => $transaction,
                'amount' => $ammount * 100,
            ];

            $payments = $client->request->post("/payments/{$transaction}/refund", $form);
            $paymentArray = $payments->asArray();

            $this->logger->debug(json_encode($paymentArray));

            return $paymentArray;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    /**
     * Get language code from locale
     *
     * @return mixed
     */
    private function getLanguage()
    {
        $locale = $this->resolver->getLocale();

        //Map both norwegian locales to no
        $map = [
            'nb' => 'no',
            'nn' => 'no',
        ];

        $language = explode('_', $locale)[0];

        if (isset($map[$language])) {
            return $map[$language];
        }

        return $language;
    }

    /**
     * Get payment methods
     *
     * @return string
     */
    public function getPaymentMethods()
    {
        $payment_methods = $this->scopeConfig->getValue(self::PAYMENT_METHODS_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        /**
         * Get specified payment methods
         */
        if ($payment_methods === 'specified') {
            $payment_methods = $this->scopeConfig->getValue(self::SPECIFIED_PAYMENT_METHOD_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }

        return $payment_methods;
    }

    /**
     * @param null $order
     * @param $transactionId
     * @param $type
     */
    public function createTransaction($order = null, $transactionId, $type)
    {
        try {
            //get payment object from order object
            $payment = $order->getPayment();

            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = '';
            if($type == \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH){
                $message = __('The authorized amount is %1.', $formatedPrice);
            } elseif($type == \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE) {
                $message = __('The captured amount is %1.', $formatedPrice);
            }

            if($payment->getLastTransId()){
                $parent_id = $payment->getLastTransId();
            } else {
                $parent_id = null;
            }

            $payment->setLastTransId($transactionId);
            $payment->setTransactionId($transactionId);
            /*$payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
            );*/

            //get the object of builder class
            $trans = $this->transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($transactionId)
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$payment->getAdditionalInformation()]
                )
                ->setFailSafe(true)
                //build method creates the transaction and returns the object
                ->build($type);
            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId($parent_id);
            $payment->save();
            $order->save();

        } catch (Exception $e) {
            //log errors here
        }
    }
}
