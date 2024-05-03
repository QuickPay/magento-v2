define(
    [
        'Magento_Checkout/js/view/payment/default',
        'QuickPay_Gateway/js/action/redirect-on-success'
    ],
    function (Component, quickpayRedirect) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'QuickPay_Gateway/payment/form',
                paymentReady: false
            },
            redirectAfterPlaceOrder: false,

            /**
             * @return {exports}
             */
            initObservable: function () {
                this._super()
                    .observe('paymentReady');

                return this;
            },

            /**
             * @return {*}
             */
            isPaymentReady: function () {
                return this.paymentReady();
            },

            getCode: function() {
                return 'quickpay_vipps';
            },
            getData: function() {
                return {
                    'method': this.item.method,
                };
            },
            afterPlaceOrder: function() {
                quickpayRedirect.execute();
            },
            getPaymentLogo: function () {
                return window.checkoutConfig.payment.quickpay_vipps.paymentLogo;
            },
            getDescription: function () {
                return window.checkoutConfig.payment.quickpay_vipps.description;
            }
        });
    }
);