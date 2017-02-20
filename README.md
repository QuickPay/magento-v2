## QuickPay_Magento2

Module QuickPay\Payment implements integration with the QuickPay payment service provider.

Currently in beta release, use at your own risk. Pull requests welcome!

Coded and tested in Magento 2.1.2, compatibility with other versions has not been tested yet.

Implemented so far:
* Authorize
* Capture 
* Partial Capture
* Refund
* Partial Refund
* Cancel
* Payment Fees

### Installation
```
composer require quickpay/magento2
php bin/magento module:enable QuickPay_Payment
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```