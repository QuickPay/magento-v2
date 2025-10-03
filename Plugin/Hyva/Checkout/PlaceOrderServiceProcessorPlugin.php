<?php
/**
 * QuickPay Gateway - Hyva Checkout Plugin
 * Handles redirect functionality for QuickPay payment methods in Hyva checkout
 */

declare(strict_types=1);

namespace QuickPay\Gateway\Plugin\Hyva\Checkout;

use Hyva\Checkout\Model\Magewire\Component\Evaluation\Redirect;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Payment\PlaceOrderServiceProcessor;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class PlaceOrderServiceProcessorPlugin
{
    private CheckoutSession $checkoutSession;
    private UrlInterface $urlBuilder;
    private LoggerInterface $logger;
    private EvaluationResultFactory $evaluationResultFactory;

    public function __construct(
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder,
        LoggerInterface $logger,
        EvaluationResultFactory $evaluationResultFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->evaluationResultFactory = $evaluationResultFactory;
    }

    /**
     * After successful order placement, check if QuickPay redirect is needed
     */
    public function afterProcess(
        PlaceOrderServiceProcessor $subject,
        $result,
        $component,
        $data = null
    ) {
        try {
            // Check if order was placed successfully
            if ($subject->hasSuccess()) {
                $order = $this->checkoutSession->getLastRealOrder();

                if ($order && $order->getId()) {
                    $payment = $order->getPayment();
                    $method = $payment->getMethod();

                    // Check if it's a QuickPay payment method
                    if (strpos($method, 'quickpay') === 0) {
                        // Build redirect URL using the same pattern as ConfigProvider
                        $redirectUrl = $this->urlBuilder->getUrl('quickpaygateway/payment/redirect');

                        if ($redirectUrl) {
                            // Create redirect evaluation result
                            $redirectResult = $this->evaluationResultFactory->create(
                                Redirect::class,
                                ['url' => $redirectUrl]
                            );

                            // Add the redirect result to the component
                            $component->getEvaluationResultBatch()->push($redirectResult);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in QuickPay Hyva checkout plugin: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }

        return $result;
    }
}
