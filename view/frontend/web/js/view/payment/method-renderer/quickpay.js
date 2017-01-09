define(
    [
        'Magento_Checkout/js/view/payment/default',
        'QuickPay_Payment/js/action/redirect-on-success'
    ],
    function (Component, quickpayRedirect) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'QuickPay_Payment/payment/form',
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
                return 'quickpay';
            },
            getData: function() {
                return {
                    'method': this.item.method,
                };
            },
            afterPlaceOrder: function() {
                quickpayRedirect.execute();
            }
        });
    }
);