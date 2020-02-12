<?php
namespace QuickPay\Gateway\Block\Checkout;

use Magento\Store\Model\ScopeInterface;

class MobilePay extends \Magento\Framework\View\Element\Template
{
    const MOBILEPAY_TITLE_XML_PATH      = 'payment/quickpay_gateway/mobilepay/title';
    const MOBILEPAY_DESCRIPTION_XML_PATH  = 'payment/quickpay_gateway/mobilepay/description';
    const MOBILEPAY_POPUP_DESCRIPTION_XML_PATH  = 'payment/quickpay_gateway/mobilepay/popup_description';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $url;

    /**
     * @var \Magento\CheckoutAgreements\Model\ResourceModel\Agreement\CollectionFactory
     */
    protected $_agreementCollectionFactory;

    /**
     * @var \QuickPay\Gateway\Model\Carrier\Shipping
     */
    protected $shipping;

     /**
     * MobilePay constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\UrlInterface $url
     * @param \Magento\CheckoutAgreements\Model\ResourceModel\Agreement\CollectionFactory $agreementCollectionFactory
     * @param \QuickPay\Gateway\Model\Carrier\Shipping $shipping
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\UrlInterface $url,
        \Magento\CheckoutAgreements\Model\ResourceModel\Agreement\CollectionFactory $agreementCollectionFactory,
        \QuickPay\Gateway\Model\Carrier\Shipping $shipping
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->_agreementCollectionFactory = $agreementCollectionFactory;
        $this->shipping = $shipping;
        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function getTitle(){
        return $this->scopeConfig->getValue(self::MOBILEPAY_TITLE_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDescription(){
        return $this->scopeConfig->getValue(self::MOBILEPAY_DESCRIPTION_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPopupDescription(){
        return $this->scopeConfig->getValue(self::MOBILEPAY_POPUP_DESCRIPTION_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function getRedirectUrl(){
        return $this->url->getUrl('quickpaygateway/payment/mobilepayredirect');
    }

    /**
     * @return mixed
     */
    public function getAgreements()
    {
        if (!$this->hasAgreements()) {
            $agreements = [];
            if ($this->scopeConfig->isSetFlag('checkout/options/enable_agreements', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
                /** @var \Magento\CheckoutAgreements\Model\ResourceModel\Agreement\Collection $agreements */
                $agreements = $this->_agreementCollectionFactory->create();
                $agreements->addStoreFilter($this->_storeManager->getStore()->getId());
                $agreements->addFieldToFilter('is_active', 1);
            }
            $this->setAgreements($agreements);
        }
        return $this->getData('agreements');
    }

    /**
     * @return mixed
     */
    public function getShippingMethods(){
        return $this->shipping->getMobilePayMethods();
    }
}