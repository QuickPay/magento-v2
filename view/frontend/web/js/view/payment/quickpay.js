define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'quickpay_gateway',
                component: 'QuickPay_Gateway/js/view/payment/method-renderer/quickpay'
            },
            {
                type: 'quickpay_klarna',
                component: 'QuickPay_Gateway/js/view/payment/method-renderer/klarna'
            },
            {
                type: 'quickpay_mobilepay',
                component: 'QuickPay_Gateway/js/view/payment/method-renderer/mobilepay'
            },
            {
                type: 'quickpay_vipps',
                component: 'QuickPay_Gateway/js/view/payment/method-renderer/vipps'
            },
            {
                type: 'quickpay_paypal',
                component: 'QuickPay_Gateway/js/view/payment/method-renderer/paypal'
            },
            {
                type: 'quickpay_viabill',
                component: 'QuickPay_Gateway/js/view/payment/method-renderer/viabill'
            },
            {
                type: 'quickpay_swish',
                component: 'QuickPay_Gateway/js/view/payment/method-renderer/swish'
            },
            {
                type: 'quickpay_trustly',
                component: 'QuickPay_Gateway/js/view/payment/method-renderer/trustly'
            }
        );

        return Component.extend({});
    }
);