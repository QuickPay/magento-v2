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
            }
        );

        return Component.extend({});
    }
);