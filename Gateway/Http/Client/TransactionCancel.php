<?php

namespace QuickPay\Payment\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\Client\Zend;

class TransactionCancel extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(array $data)
    {
        return $this->adapter->cancel($data);
    }
}