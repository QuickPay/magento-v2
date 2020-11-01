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
    const CODE_MOBILEPAY = 'quickpay_mobilepay';

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
                self::CODE_MOBILEPAY => [
                    'paymentLogo' => $this->getMobilePayLogo()
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

    public function getMobilePayLogo(){
        $items = [];

        $items[] = $this->assetRepo->getUrl("QuickPay_Gateway::images/mobilepay_payment.png");

        return $items;
    }
}
