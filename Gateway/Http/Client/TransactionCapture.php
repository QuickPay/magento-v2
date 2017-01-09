<?php

namespace QuickPay\Payment\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\Client\Zend;

class TransactionCapture extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(array $data)
    {
        return $this->adapter->capture($data);
    }
}