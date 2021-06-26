<?php
namespace QuickPay\Gateway\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'quickpay_gateway';
    const CODE_KLARNA = 'quickpay_klarna';
    const CODE_APPLEPAY = 'quickpay_applepay';
    const CODE_MOBILEPAY = 'quickpay_mobilepay';
    const CODE_VIPPS= 'quickpay_vipps';
    const CODE_PAYPAL = 'quickpay_paypal';
    const CODE_VIABILL = 'quickpay_viabill';
    const CODE_SWISH = 'quickpay_swish';
    const CODE_TRUSTLY = 'quickpay_trustly';

    const XML_PATH_CARD_LOGO = 'payment/quickpay_gateway/cardlogos';

    protected $scopeConfig;

    protected $assetRepo;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\Asset\Repository $assetRepo
    ){
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'redirectUrl' => 'quickpaygateway/payment/redirect',
                    'paymentLogo' => $this->getQuickPayCardLogo()
                ],
                self::CODE_KLARNA => [
                    'paymentLogo' => $this->getKlarnaLogo()
                ],
                self::CODE_APPLEPAY => [
                    'paymentLogo' => $this->getApplePayLogo()
                ],
                self::CODE_MOBILEPAY => [
                    'paymentLogo' => $this->getMobilePayLogo()
                ],
                self::CODE_VIPPS => [
                    'paymentLogo' => $this->getVippsLogo()
                ],
                self::CODE_PAYPAL => [
                    'paymentLogo' => $this->getPaypalLogo()
                ],
                self::CODE_VIABILL => [
                    'paymentLogo' => $this->getViaBillLogo()
                ],
                self::CODE_SWISH => [
                    'paymentLogo' => $this->getSwishLogo()
                ],
                self::CODE_TRUSTLY => [
                    'paymentLogo' => $this->getTrustlyLogo()
                ]
            ]
        ];
    }

    public function getQuickPayCardLogo(){
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $cards = explode(',', $this->scopeConfig->getValue(self::XML_PATH_CARD_LOGO, $storeScope));

        $items = [];

        if(count($cards)) {
            foreach ($cards as $card) {
                if($card) {
                    $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/logo/{$card}.png");
                }
            }
        }

        return $items;
    }

    public function getKlarnaLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/klarna.png");

        return $items;
    }

    public function getApplePayLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/applepay.png");

        return $items;
    }

    public function getMobilePayLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/mobilepay_payment.png");

        return $items;
    }

    public function getVippsLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/vipps.png");

        return $items;
    }

    public function getPaypalLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/paypal.png");

        return $items;
    }

    public function getViaBillLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/viabill.png");

        return $items;
    }

    public function getSwishLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/swish.png");

        return $items;
    }

    public function getTrustlyLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/trustly.svg");

        return $items;
    }
}
