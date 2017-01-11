<?php
namespace QuickPay\Payment\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Psr\Log\LoggerInterface;

class CaptureRequest implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param SubjectReader $subjectReader
     * @param LoggerInterface $logger
     */
    public function __construct(SubjectReader $subjectReader, LoggerInterface $logger)
    {
        $this->subjectReader = $subjectReader;
        $this->logger = $logger;
    }

    /**
     * Builds capture request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $payment = $paymentDO->getPayment();
        $amount = $this->subjectReader->readAmount($buildSubject) * 100;

        ContextHelper::assertOrderPayment($payment);

        return [
            'TXN_ID'       => $payment->getLastTransId(),
            'AMOUNT'       => $amount,
        ];
    }
}
