## QuickPay_Magento2

Module QuickPay\Payment implements integration with the QuickPay payment service provider.

Currently in alpha release, use at your own risk. Pull requests welcome!

Coded and tested in Magento 2.1.2, compatibility with other versions has not been tested yet.

Implemented so far:
* Authorize
* Capture
* Refund
* Cancel

### Installation
```
composer require quickpay/magento2
php bin/magento module:enable QuickPay_Payment
php bin/magento setup:upgrade
```

If running in production you may need to recompile DI using
```
php bin/magento setup:di:compile
```
