<?php

namespace QuickPay\Gateway\Model\Config\Source;

class PaymentMethods implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => '',
                'label' => __('All Payment Methods')
            ],
            [
                'value' => 'creditcard',
                'label' => __('All Creditcards')
            ],
            [
                'value' => 'specified',
                'label' => __('As Specified')
            ]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            '' => __('All Payment Methods'),
            'creditcard' => __('All Creditcards'),
            'specified' => __('As Specified')
        ];
    }
}
