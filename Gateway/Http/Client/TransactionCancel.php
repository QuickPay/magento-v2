<?php

namespace QuickPay\Payment\Gateway\Http\Client;

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