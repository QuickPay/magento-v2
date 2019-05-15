## QuickPay_Gateway

This is a major release - a remake of the old 2.x module (QuickPay_Payment).

The main reason for the remake was to fix two main issues of QuickPay_Payment, namely:
* Marking unsuccessful orders as "Authorized"
* Making it not possible to cancel such orders

Currently in beta release, use at your own risk. We highly suggest to test it on a staging/development environment first.

Tested for Magento 2.0 / 2.1 / 2.2 / 2.3 (PHP version up to 7.2.11.)

### Installation
```
composer config repositories.repo-name vcs https://github.com/QuickPay/magento-v2.git
composer require quickpay/magento2:dev-QuickPay_Gateway-remake
php bin/magento module:enable QuickPay_Gateway
php bin/magento setup:upgrade
php bin/magento setup:di:compile
``` 

**Please note that FTP installation will not work as this module has requirements that will be auto installed when using composer**