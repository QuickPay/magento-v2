define(
    [
        'mage/url'
    ],
    function (url) {
        'use strict';

        return {
            redirectUrl: window.checkoutConfig.payment.quickpay_gateway.redirectUrl,

            /**
             * Provide redirect to page
             */
            execute: function () {
                // Check if we're in Hyva checkout environment
                if (typeof Magewire !== 'undefined' && Magewire.isAvailable) {
                    // Use Hyva's redirect mechanism
                    window.dispatchEvent(new CustomEvent('hyva:checkout:redirect', {
                        detail: {
                            url: url.build(this.redirectUrl),
                            method: 'replace'
                        }
                    }));
                } else {
                    // Use standard Magento redirect
                    window.location.replace(url.build(this.redirectUrl));
                }
            }
        };
    }
);
