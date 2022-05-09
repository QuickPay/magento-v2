<?php

namespace QuickPay\Gateway\Cron;

use \Magento\Sales\Model\Order;

class AutomaticallyOrderCancel
{
    const CONFIG_PATH_CANCEL_PERIOD = 'payment/quickpay_gateway/cancel_period';

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $_timezone;

    /**
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    )
    {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->_timezone = $timezone;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $period = $this->getCancelPeriod();
        if(!$period){
            return;
        }

        $collection = $this->_orderCollectionFactory->create()
            ->addFieldToFilter(
                'state',
                ['in' => [Order::STATE_PENDING_PAYMENT, Order::STATE_NEW]]
            )
            ->addFieldToFilter('created_at', ['lteq' => $period]);

        $collection->getSelect()
            ->join(
                ['payment' => 'sales_order_payment'],
                'main_table.entity_id = payment.parent_id',
                array('method')
            )
            ->where('payment.method LIKE "quickpay%"');

        if($collection->getSize()){
            foreach($collection as $order){
                if ($order->getId() && ! $order->isCanceled()) {
                    $order->registerCancellation('')->save();
                }
            }
        }
    }

    /**
     * @return string|null
     */
    public function getCancelPeriod(){
        $hours = $this->_scopeConfig->getValue(self::CONFIG_PATH_CANCEL_PERIOD, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if($hours) {
            $date = new \DateTime();
            $date->modify("-{$hours} hours");

            return $date->format('Y-m-d H:i:s');
        }
        
        return null;
    }
}
