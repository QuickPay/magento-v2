<?php

declare(strict_types=1);

namespace QuickPay\Gateway\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use QuickPay\Gateway\Model\Ui\ConfigProvider;

class QuickPay extends Template
{
    /**
     * @var ConfigProvider
     */
    protected $configProvider;

    /**
     * QuickPay constructor.
     *
     * @param Context $context
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        Context $context,
        ConfigProvider $configProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProvider = $configProvider;
    }

    /**
     * Return payment logo, label, and description based on selected payment method
     *
     * @param string $methodCode
     * @return array
     */
    public function getPaymentConfigByMethod(string $methodCode)
    {
        $config = $this->configProvider->getConfig();
        $methodConfig = $config['payment'][$methodCode] ?? null;

        return [
            'paymentLogo' => $methodConfig['paymentLogo'] ?? [],
            'description' => $this->configProvider->getDescription($methodCode),
            'label'       => $methodCode
        ];
    }
}
