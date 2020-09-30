<?php

namespace QuickPay\Gateway\Plugin;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\CsrfValidator;
use Magento\Framework\App\RequestInterface;

class CsrfValidatorSkip
{
    /**
     * @todo Remove and
     *
     * @param CsrfValidator $subject
     * @param callable $proceed
     * @param RequestInterface $request
     * @param ActionInterface $action
     * @return bool
     */
    public function aroundValidate(
        CsrfValidator $subject,
        callable $proceed,
        RequestInterface $request,
        ActionInterface $action
    ) {
        if ($action instanceof \QuickPay\Gateway\Controller\Payment\Callback) {
            return true;
        }
        return $proceed($request, $action);
    }
}
