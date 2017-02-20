<?php

namespace QuickPay\Payment\Gateway\Http\Client;

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