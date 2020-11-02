## QuickPay_Gateway

This is a major release - a remake of the old 2.x module (QuickPay_Payment).

The main reason for the remake was to fix two main issues of QuickPay_Payment, namely:
* Marking unsuccessful orders as "Authorized"
* Making it not possible to cancel such orders

We highly suggest to test it on a staging/development environment first.

Tested on Magento 2.3 / 2.4 (PHP version up to 7.4). 
If you have trouble running it on an older Magento version, please, try to update it first.

### Installation
```
composer require quickpay/magento2
php bin/magento module:enable QuickPay_Gateway
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
php bin/magento setup:di:compile
php bin/magento cache:clean
``` 

**Please note that FTP installation will not work as this module has requirements that will be auto installed when using composer**

Pull requests welcome.
