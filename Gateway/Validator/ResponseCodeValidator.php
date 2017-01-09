<?php

namespace QuickPay\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Psr\Log\LoggerInterface;

class ResponseCodeValidator extends AbstractValidator
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param LoggerInterface $logger
     */
    public function __construct(ResultInterfaceFactory $resultFactory, LoggerInterface $logger)
    {
        parent::__construct($resultFactory);
        $this->logger = $logger;
    }

    /**
     * Performs validation of http status code
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']) || !is_array($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];

        if ($this->isSuccessfulTransaction($response)) {
            return $this->createResult(
                true,
                []
            );
        } else {
            return $this->createResult(
                false,
                [__('Gateway rejected the transaction.')]
            );
        }
    }

    /**
     * Validate transaction response
     *
     * @param $response
     * @return bool
     */
    private function isSuccessfulTransaction($response)
    {
        //Check that the transaction was accepted
        $responseObject = $response['object'];
        if (isset($responseObject)) {
            if (isset($responseObject['accepted'])) {
                if ($responseObject['accepted'] === true) {
                    return true;
                }
            }
        }

        return false;
    }
}