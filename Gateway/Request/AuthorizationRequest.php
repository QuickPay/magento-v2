<?php
namespace QuickPay\Payment\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class AuthorizationRequest implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(SubjectReader $subjectReader)
    {
        $this->subjectReader = $subjectReader;
    }

    /**
     * Builds authorization request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $amount = $this->subjectReader->readAmount($buildSubject) * 100;
        /** @var Order $order */
        $order = $paymentDO->getOrder();
        if ($order->getIsVirtual()) {
            $address = $order->getBillingAddress();
        } else {
            $address = $order->getShippingAddress();
        }

        return [
            'INCREMENT_ID' => $order->getOrderIncrementId(),
            'AMOUNT'       => $amount, //Get order total in cents
            'CURRENCY'     => $order->getCurrencyCode(),
            'EMAIL'        => $address->getEmail(),
        ];
    }
}
