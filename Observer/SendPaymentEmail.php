<?php

namespace QuickPay\Gateway\Observer;
use QuickPay\Gateway\Model\Ui\ConfigProvider;
use Magento\Framework\Event\ObserverInterface;

class SendPaymentEmail implements ObserverInterface
{
    const PAYMENT_LINK_EMAIL_XML_PATH = 'payment/quickpay_gateway/payment_template';

    /**
     * @var QuickPay\Gateway\Model\Adapter\QuickPayAdapter
     */
    protected $adapter;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $_inlineTranslation;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $_transportBuilder;

    protected $_scopeConfig;

    /**
     * SendPaymentEmail constructor.
     * @param \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter
     * @param \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     */
    public function __construct(
        \QuickPay\Gateway\Model\Adapter\QuickPayAdapter $adapter,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->adapter = $adapter;
        $this->_inlineTranslation = $inlineTranslation;
        $this->_transportBuilder = $transportBuilder;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $payment = $order->getPayment();
        if (in_array($payment->getMethod(),[
	        ConfigProvider::CODE,
	        ConfigProvider::CODE_KLARNA,
	        ConfigProvider::CODE_MOBILEPAY,
	        ConfigProvider::CODE_VIPPS,
	        ConfigProvider::CODE_PAYPAL,
	        ConfigProvider::CODE_VIABILL,
	        ConfigProvider::CODE_SWISH,
	        ConfigProvider::CODE_TRUSTLY,
            ConfigProvider::CODE_ANYDAY,
            ConfigProvider::CODE_APPLEPAY,
            ConfigProvider::CODE_GOOGLEPAY
        ])) {
            $this->savePaymentLink($order);
            $this->sendPaymentEmail($order);
        }
    }

    public function savePaymentLink($order){
        $response = $this->adapter->CreatePaymentLink($order, \Magento\Framework\App\Area::AREA_ADMINHTML);

        if(isset($response['url'])){
            $payment = $order->getPayment();
            $additional = $payment->getAdditionalData();
            if(!$additional){
                $additional = [];
            }
            $additional['payment_link'] = $response['url'];

            $payment->setAdditionalData(json_encode($additional));
            $payment->save();

        }
    }

    public function sendPaymentEmail($order)
    {
        try
        {
            $additional = $order->getPayment()->getAdditionalData();

            if($additional){
                $additional = json_decode($additional, true);
                if(!isset($additional['payment_link'])){
                    return;
                }
            } else {
                return;
            }

            // Send Mail
            $this->_inlineTranslation->suspend();

            $sender = [
                'name' => $this->_scopeConfig->getValue('trans_email/ident_sales/name',\Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                'email' => $this->_scopeConfig->getValue('trans_email/ident_sales/email',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ];

            $sentToEmail = $order->getCustomerEmail();
            $sentToName = $order->getCustomerName();

            $templateId = $this->_scopeConfig->getValue(self::PAYMENT_LINK_EMAIL_XML_PATH,\Magento\Store\Model\ScopeInterface::SCOPE_STORE, $order->getStoreId());

            $transport = $this->_transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $order->getStoreId(),
                    ]
                )
                ->setTemplateVars([
                    'paymentlink'  => $additional['payment_link'],
                    'order' => $order
                ])
                ->setFrom($sender)
                ->addTo($sentToEmail,$sentToName)
                ->getTransport();

            $transport->sendMessage();

            $this->_inlineTranslation->resume();
        } catch(\Exception $e){
            throw new \Exception($e->getMessage());
        }



    }
}
