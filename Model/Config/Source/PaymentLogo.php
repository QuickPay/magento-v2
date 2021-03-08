<?php

namespace QuickPay\Gateway\Model\Config\Source;

class PaymentLogo implements \Magento\Framework\Option\ArrayInterface
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
                'value' => 'dankort',
                'label' => __('Dankort')
            ],
            [
                'value' => 'forbrugsforeningen',
                'label' => __('Forbrugsforeningen')
            ],
            [
                'value' => 'visa',
                'label' => __('VISA')
            ],
            [
                'value' => 'visaelectron',
                'label' => __('VISA Electron')
            ],
            [
                'value' => 'mastercard',
                'label' => __('MasterCard')
            ],
            [
                'value' => 'maestro',
                'label' => __('Maestro')
            ],
            [
                'value' => 'jcb',
                'label' => __('JCB')
            ],
            [
                'value' => 'diners',
                'label' => __('Diners Club')
            ],
            [
                'value' => 'amex',
                'label' => __('AMEX')
            ],
            [
                'value' => 'sofort',
                'label' => __('Sofort')
            ],
            [
                'value' => 'viabill',
                'label' => __('ViaBill')
            ],
            [
                'value' => 'mobilepay',
                'label' => __('MobilePay')
            ],
            [
                'value' => 'paypal',
                'label' => __('Paypal')
            ],
            [
                'value' => 'applepay',
                'label' => __('Apple Pay')
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
