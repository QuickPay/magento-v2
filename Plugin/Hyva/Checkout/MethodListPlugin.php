<?php
/**
 * QuickPay Gateway - Hyva Checkout MethodList Plugin
 * Automatically adds metadata from ConfigProvider to Hyva checkout payment methods
 * Works with and without Hyva installation
 */

declare(strict_types=1);

namespace QuickPay\Gateway\Plugin\Hyva\Checkout;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use QuickPay\Gateway\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;

class MethodListPlugin
{
    private $configProvider;
    private $methodMetaDataFactory;
    private $logger;

    public function __construct(
        ConfigProvider $configProvider,
        LoggerInterface $logger,
        $methodMetaDataFactory = null
    ) {
        $this->configProvider = $configProvider;
        $this->methodMetaDataFactory = $methodMetaDataFactory;
        $this->logger = $logger;
    }

    /**
     * After getting method metadata, add QuickPay specific data from ConfigProvider
     */
    public function afterGetMethodMetaData(
        $subject,
        $result,
        Template $parent,
        PaymentMethodInterface $method
    ) {
        // Check if Hyva classes are available
        if (!interface_exists('Hyva\Checkout\Model\MethodMetaDataInterface') || 
            !class_exists('Hyva\Checkout\Model\MethodMetaDataFactory')) {
            return $result;
        }
        $methodCode = $method->getCode();

        // Check if it's a QuickPay payment method
        if (strpos($methodCode, 'quickpay') === 0) {
            try {
                $config = $this->configProvider->getConfig();
                $paymentConfig = $config['payment'][$methodCode] ?? [];

                // Add icon data if available (use paymentLogo for both Luma and Hyva compatibility)
                if (isset($paymentConfig['paymentLogoPath']) && is_array($paymentConfig['paymentLogoPath'])) {
                    if (!empty($paymentConfig['paymentLogoPath'])) {
                        // Use only the first logo (Hyva checkout limitation)
                        $firstLogo = $paymentConfig['paymentLogoPath'][0];

                        $iconData = [
                            'src' => $firstLogo,
                            'attributes' => [
                                'width' => '60',
                                'height' => '',
                                'alt' => $methodCode
                            ]
                        ];

                        $result->setData('icon', $iconData);
                    }
                }

                // Add description as subtitle if available
                if (isset($paymentConfig['description']) && !empty($paymentConfig['description'])) {
                    $result->setData('subtitle', $paymentConfig['description']);
                }
            } catch (\Exception $e) {
                $this->logger->error('QuickPay MethodListPlugin: Error processing ' . $methodCode, ['exception' => $e]);
            }
        }

        return $result;
    }
}
