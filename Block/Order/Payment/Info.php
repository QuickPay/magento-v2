<?php

namespace QuickPay\Gateway\Block\Order\Payment;

class Info extends \Magento\Payment\Block\Info{
    protected function _prepareSpecificInformation($transport = null)
    {
        if (strpos($this->getMethod()->getCode(), 'quickpay') !== false) {
            $payment = $this->getInfo();
            $additional = $payment->getAdditionalInformation();
            if(isset($additional['method_title'])){
                unset($additional['method_title']);
            }
            $transport = $additional;
        }

        return parent::_prepareSpecificInformation($transport);
    }
}