<?php


namespace QuickPay\Payment\Gateway\Response;


use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class CardDetailsHandler implements HandlerInterface
{
    const CARD_TYPE = 'type';

    const CARD_EXP_MONTH = 'exp_month';

    const CARD_EXP_YEAR = 'exp_year';

    const CARD_LAST4 = 'last4';

    const CARD_NUMBER = 'cc_number';

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = SubjectReader::readPayment($handlingSubject);

        $payment = $paymentDO->getPayment();
        ContextHelper::assertOrderPayment($payment);

        $responseObject = $response['object'];

        $metadata = $responseObject['metadata'];

        /** @var $payment \Magento\Sales\Model\Order\Payment */
        $payment->setCcLast4($metadata[self::CARD_LAST4]);
        $payment->setCcExpMonth($metadata[self::CARD_EXP_MONTH]);
        $payment->setCcExpYear($metadata[self::CARD_EXP_YEAR]);

//        $payment->setCcType($this->getCreditCardType($creditCard[self::CARD_TYPE]));

        // set card details to additional info
        $payment->setAdditionalInformation(self::CARD_NUMBER, 'xxxx-' . $metadata[self::CARD_LAST4]);
        $payment->setAdditionalInformation(OrderPaymentInterface::CC_TYPE, $metadata[self::CARD_TYPE]);
    }
}