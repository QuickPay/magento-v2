<?php

namespace QuickPay\Payment\Gateway\Http\Client;

class TransactionAuthorize extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(array $data)
    {
        return $this->adapter->authorizeAndCreatePaymentLink($data);
    }
}