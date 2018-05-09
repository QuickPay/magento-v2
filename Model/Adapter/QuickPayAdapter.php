<?php
namespace QuickPay\Payment\Model\Adapter;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use \Magento\Framework\UrlInterface;
use QuickPay\QuickPay;
use Zend_Locale;

/**
 * Class QuickPayAdapter
 */
class QuickPayAdapter
{
    const PUBLIC_KEY_XML_PATH      = 'payment/quickpay/public_key';
    const TRANSACTION_FEE_XML_PATH = 'payment/quickpay/transaction_fee';
    const AUTOCAPTURE_XML_PATH = 'payment/quickpay/autocapture';
    const TEXT_ON_STATEMENT_XML_PATH = 'payment/quickpay/text_on_statement';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $url;

    /**
     * @var \QuickPay\Payment\Helper\Data
     */
    protected $helper;

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
     * QuickPayAdapter constructor.
     *
     * @param LoggerInterface $logger
     * @param UrlInterface $url
     * @param \QuickPay\Payment\Helper\Data $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param ResolverInterface $resolver
     */
    public function __construct(
        LoggerInterface $logger,
        UrlInterface $url,
        \QuickPay\Payment\Helper\Data $helper,
        ScopeConfigInterface $scopeConfig,
        ResolverInterface $resolver,
        OrderRepositoryInterface $orderRepository
    )
    {
        $this->logger = $logger;
        $this->url = $url;
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->resolver = $resolver;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Authorize payment and create payment link
     *
     * @param array $attributes
     * @return array|bool
     */
    public function authorizeAndCreatePaymentLink(array $attributes)
    {
        try {
            $api_key = $this->scopeConfig->getValue(self::PUBLIC_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $client = new QuickPay(":{$api_key}");

            $form = [
                'order_id' => $attributes['INCREMENT_ID'],
                'currency' => $attributes['CURRENCY'],
            ];

            $shippingAddress = $attributes['SHIPPING_ADDRESS'];
            $form['shipping_address'] = [];
            $form['shipping_address']['name'] = $shippingAddress->getFirstName() . " " . $shippingAddress->getLastName();
            $form['shipping_address']['street'] = $shippingAddress->getStreetLine1();
            $form['shipping_address']['city'] = $shippingAddress->getCity();
            $form['shipping_address']['zip_code'] = $shippingAddress->getPostcode();
            $form['shipping_address']['region'] = $shippingAddress->getRegionCode();
            $form['shipping_address']['country_code'] = Zend_Locale::getTranslation($shippingAddress->getCountryId(), 'Alpha3ToTerritory');
            $form['shipping_address']['phone_number'] = $shippingAddress->getTelephone();
            $form['shipping_address']['email'] = $shippingAddress->getEmail();

            $order = $this->orderRepository->get($attributes['ORDER_ID']);

            $form['shipping'] = [
                'amount' => $order->getShippingInclTax() * 100
            ];

            $billingAddress = $attributes['BILLING_ADDRESS'];
            $form['invoice_address'] = [];
            $form['invoice_address']['name'] = $billingAddress->getFirstName() . " " . $billingAddress->getLastName();
            $form['invoice_address']['street'] = $billingAddress->getStreetLine1();
            $form['invoice_address']['city'] = $billingAddress->getCity();
            $form['invoice_address']['zip_code'] = $billingAddress->getPostcode();
            $form['invoice_address']['region'] = $billingAddress->getRegionCode();
            $form['invoice_address']['country_code'] = Zend_Locale::getTranslation($billingAddress->getCountryId(), 'Alpha3ToTerritory');
            $form['invoice_address']['phone_number'] = $billingAddress->getTelephone();
            $form['invoice_address']['email'] = $billingAddress->getEmail();

            //Build basket array
            $items = $attributes['ITEMS'];
            $form['basket'] = [];
            foreach ($items as $item) {
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
            $paymentId = $paymentArray['id'];

            $parameters = [
                "amount"             => $attributes['AMOUNT'],
                "continueurl"        => $this->url->getUrl('quickpay/payment/returnAction'),
                "cancelurl"          => $this->url->getUrl('quickpay/payment/cancelAction'),
                "callbackurl"        => $this->url->getUrl('quickpay/payment/callback'),
                "customer_email"     => $attributes['EMAIL'],
                "autocapture"        => $this->scopeConfig->isSetFlag(self::AUTOCAPTURE_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                "payment_methods"    => $this->helper->getPaymentMethods(),
                "language"           => $this->getLanguage(),
                "auto_fee"           => $this->scopeConfig->isSetFlag(self::TRANSACTION_FEE_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            ];

            if ($textOnStatement = $this->scopeConfig->getValue(self::TEXT_ON_STATEMENT_XML_PATH)) {
                $parameters['text_on_statement'] = $textOnStatement;
            }

            //Create payment link and return payment id
            $paymentLink = $client->request->put(sprintf('/payments/%s/link', $paymentId), $parameters)->asArray();
            $paymentArray['link'] = $paymentLink['url'];

            return $paymentArray;
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
    public function capture(array $attributes)
    {
        try {
            $api_key = $this->scopeConfig->getValue(self::PUBLIC_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $client = new QuickPay(":{$api_key}");

            $form = [
                'id'     => $attributes['TXN_ID'],
                'amount' => $attributes['AMOUNT'],
            ];

            $id = $attributes['TXN_ID'];

            $payments = $client->request->post("/payments/{$id}/capture", $form);
            $paymentArray = $payments->asArray();

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
    public function cancel(array $attributes)
    {
        $this->logger->debug("Cancel payment");
        try {
            $api_key = $this->scopeConfig->getValue(self::PUBLIC_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $client = new QuickPay(":{$api_key}");

            $form = [
                'id' => $attributes['TXN_ID'],
            ];

            $this->logger->debug(var_export($form, true));

            $id = $attributes['TXN_ID'];

            $payments = $client->request->post("/payments/{$id}/cancel", $form);
            $paymentArray = $payments->asArray();

            $this->logger->debug(var_export($paymentArray, true));

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
    public function refund(array $attributes)
    {
        $this->logger->debug("Refund payment");

        try {
            $api_key = $this->scopeConfig->getValue(self::PUBLIC_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $client = new QuickPay(":{$api_key}");

            $form = [
                'id' => $attributes['TXN_ID'],
                'amount' => $attributes['AMOUNT'],
            ];

            $this->logger->debug(var_export($form, true));

            $id = $attributes['TXN_ID'];

            $payments = $client->request->post("/payments/{$id}/refund", $form);
            $paymentArray = $payments->asArray();

            $this->logger->debug(var_export($paymentArray, true));

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
}
