<?php

namespace QuickPay\Gateway\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Shipping\Model\Config;

class ShippingMethods implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Config
     */
    protected $shippingmodelconfig;

    /**
     * ShippingMethods constructor.
     * @param Config $shippingmodelconfig
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(Config $shippingmodelconfig, ScopeConfigInterface $scopeConfig){
        $this->shippingmodelconfig = $shippingmodelconfig;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $shippings = $this->shippingmodelconfig->getActiveCarriers();
        $methods = array();
        foreach($shippings as $shippingCode => $shippingModel)
        {
            if($carrierMethods = $shippingModel->getAllowedMethods())
            {
                foreach ($carrierMethods as $methodCode => $method)
                {
                    $code = $shippingCode.'_'.$methodCode;
                    $carrierTitle = $this->scopeConfig->getValue('carriers/'. $shippingCode.'/title');
                    $methods[] = array('value'=>$code,'label'=>$carrierTitle);
                }
            }
        }
        return $methods;
    }
}
