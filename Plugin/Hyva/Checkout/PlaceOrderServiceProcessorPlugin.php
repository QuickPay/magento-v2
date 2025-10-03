<?php
/**
 * QuickPay Gateway - Hyva Checkout Plugin
 * Handles redirect functionality for QuickPay payment methods in Hyva checkout
 * Works with and without Hyva installation
 */

declare(strict_types=1);

namespace QuickPay\Gateway\Plugin\Hyva\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class PlaceOrderServiceProcessorPlugin
{
    private $checkoutSession;
    private $urlBuilder;
    private $logger;
    private $evaluationResultFactory;

    public function __construct(
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder,
        LoggerInterface $logger,
        $evaluationResultFactory = null
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
        $subject,
        $result,
        $component,
        $data = null
    ) {
        // Check if Hyva classes are available
        if (!class_exists('Hyva\Checkout\Model\Magewire\Component\Evaluation\Redirect') ||
            !class_exists('Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory')) {
            return $result;
        }

        try {
            // Check if order was placed successfully
            if (method_exists($subject, 'hasSuccess') && $subject->hasSuccess()) {
                $order = $this->checkoutSession->getLastRealOrder();

                if ($order && $order->getId()) {
                    $payment = $order->getPayment();
                    $method = $payment->getMethod();

                    // Check if it's a QuickPay payment method
                    if (strpos($method, 'quickpay') === 0) {
                        // Build redirect URL using the same pattern as ConfigProvider
                        $redirectUrl = $this->urlBuilder->getUrl('quickpaygateway/payment/redirect');

                        if ($redirectUrl) {
                            // Get EvaluationResultFactory using ObjectManager
                            $evaluationResultFactory = ObjectManager::getInstance()->get('Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory');

                            // Create redirect evaluation result
                            $redirectResult = $evaluationResultFactory->create(
                                'Hyva\Checkout\Model\Magewire\Component\Evaluation\Redirect',
                                ['url' => $redirectUrl]
                            );

                            // Add the redirect result to the component
                            if (method_exists($component, 'getEvaluationResultBatch')) {
                                $component->getEvaluationResultBatch()->push($redirectResult);
                            }
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
