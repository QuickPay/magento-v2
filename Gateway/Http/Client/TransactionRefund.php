<?php

namespace QuickPay\Payment\Gateway\Http\Client;

class TransactionRefund extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(array $data)
    {
        return $this->adapter->refund($data);
    }
}