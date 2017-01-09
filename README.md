## QuickPay_Magento2

Module QuickPay\Payment implements integration with the QuickPay payment service provider.

Currently in alpha release, use at your own risk.

Implemented so far:
* Authorize
* Capture
* Refund
* Cancel

### Installation
```
composer require quickpay/magento2
php bin/magento setup:upgrade
```

If running in production you may need to recompile DI using
```
php bin/magento setup:di:compile
```