<?php
namespace QuickPay\Gateway\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;

class Shipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'mobilepay';

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $_currencyInterface;

    /**
     * Request instance
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * Shipping constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface          $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory  $rateErrorFactory
     * @param \Psr\Log\LoggerInterface                                    $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory                  $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array                                                       $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Framework\Pricing\PriceCurrencyInterface $currencyInterface,
        \Magento\Framework\App\RequestInterface $request,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->_currencyInterface = $currencyInterface;
        $this->request = $request;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return bool
     */
    public function isTrackingAvailable(){
        return false;
    }

    /**
     * get allowed methods
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * @return float
     */
    private function getShippingPrice()
    {
        $configPrice = $this->getConfigData('price');

        $shippingPrice = $this->getFinalPriceWithHandlingFee($configPrice);

        return $shippingPrice;
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        if(!$this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) || !$this->request->getParam('mobilepay')){
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        $amount = $this->getShippingPrice();

        $method->setPrice($amount);
        $method->setCost($amount);

        $result->append($method);

        return $result;
    }

    /**
     * @return array
     */
    private function getAvailableMethods(){
        return [
            'store_pick_up' => $this->getShipping1Title(),
            'home_delivery' => $this->getShipping2Title(),
            'registered_box' => $this->getShipping3Title(),
            'unregistered_box' => $this->getShipping4Title(),
            'pick_up_point' => $this->getShipping5Title(),
            'own_delivery' => $this->getShipping6Title()
        ];
    }

    /**
     * @return array
     */
    public function getMobilePayMethods(){
        $methods = $this->getAvailableMethods();
        $data = [];
        foreach($methods as $code => $title){
            $price = $this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/shipping_'.$code, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $data[$code] = [
                'title' => $title,
                'price' => $this->_currencyInterface->format($price, false)
            ];
        }
        return $data;
    }

    /**
     * @param $code
     * @return array|bool
     */
    public function getMethodByCode($code){
        $methods = $this->getAvailableMethods();
        if(isset($methods[$code])){
            $price = $this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/shipping_'.$code, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            return [
                'title' => $methods[$code],
                'price' => number_format($price, 2)
            ];
        }
        return false;
    }

    /**
     * @return \Magento\Framework\Phrase|mixed|string
     */
    public function getShipping1Title(){
        $title = $this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/shipping_store_pick_up_title', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $title ? $title : __('Hent i butikken');
    }

    /**
     * @return \Magento\Framework\Phrase|mixed|string
     */
    public function getShipping2Title(){
        $title = $this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/shipping_home_delivery_title', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $title ? $title : __('Ordren leveres til din hjemmeadresse');
    }

    /**
     * @return \Magento\Framework\Phrase|mixed|string
     */
    public function getShipping3Title(){
        $title = $this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/shipping_registered_box_title', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $title ? $title : __('Afhentning i en pakkeshop (registered_box)');
    }

    /**
     * @return \Magento\Framework\Phrase|mixed|string
     */
    public function getShipping4Title(){
        $title = $this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/shipping_unregistered_box_title', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $title ? $title : __('Afhentning i en pakkeshop (unregistered_box)');
    }

    /**
     * @return \Magento\Framework\Phrase|mixed|string
     */
    public function getShipping5Title(){
        $title = $this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/shipping_pick_up_point_title', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $title ? $title : __('Afhentning i en pakkeshop (pick_up_point)');
    }

    /**
     * @return \Magento\Framework\Phrase|mixed|string
     */
    public function getShipping6Title(){
        $title = $this->_scopeConfig->getValue('payment/quickpay_gateway/mobilepay/shipping_own_delivery_title', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $title ? $title : __('Ordren leveres til din hjemmeadresse');
    }
}