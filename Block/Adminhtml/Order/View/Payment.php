<?php
namespace QuickPay\Gateway\Block\Adminhtml\Order\View;
class Payment extends \Magento\Backend\Block\Template
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve order model object
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->_coreRegistry->registry('sales_order');
    }

    /**
     * @return bool|mixed
     */
    public function getPaymentLink(){
        $additional = $this->getOrder()->getPayment()->getAdditionalData();
        if($additional){
            $data = json_decode($additional, true);
            if(isset($data['payment_link'])){
                return $data['payment_link'];
            }
        }

        return false;
    }
}