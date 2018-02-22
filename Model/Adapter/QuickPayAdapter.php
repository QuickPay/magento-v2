<?php
namespace QuickPay\Payment\Model\Adapter;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\ResolverInterface;
use Psr\Log\LoggerInterface;
use \Magento\Framework\UrlInterface;
use QuickPay\QuickPay;

/**
 * Class QuickPayAdapter
 */
class QuickPayAdapter
{
    const PUBLIC_KEY_XML_PATH      = 'payment/quickpay/public_key';
    const TRANSACTION_FEE_XML_PATH = 'payment/quickpay/transaction_fee';
    const AUTOCAPTURE_XML_PATH = 'payment/quickpay/autocapture';

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
        ResolverInterface $resolver
    )
    {
        $this->logger = $logger;
        $this->url = $url;
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->resolver = $resolver;
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
