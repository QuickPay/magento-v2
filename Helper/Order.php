<?php

namespace QuickPay\Gateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Order extends AbstractHelper
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $dir;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Order constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param \Magento\Framework\App\Filesystem\DirectoryList $dir
     * @param \Psr\Log\LoggerInterface $logger
     * @throws \Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Framework\App\Filesystem\DirectoryList $dir,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ){
        $this->resource = $resource;
        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->dir = $dir;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;

        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler($this->dir->getRoot().'/var/log/quickpay.log'));
    }

    /**
     * @param $orderId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteByOrderId($orderId)
    {
        $connection = $this->resource->getConnection('core_read');

        $table = $this->resource->getTableName('quote');
        $quoteId = $connection->fetchOne('SELECT entity_id FROM ' . $table . ' WHERE reserved_order_id = "'.$orderId.'"');

        return $this->quoteRepository->get($quoteId);
    }

    /**
     * @param $name
     * @return array
     */
    public function splitCustomerName($name)
    {
        $name = trim($name);
        if (strpos($name, ' ') === false) {
            // you can return the firstname with no last name
            return array('firstname' => $name, 'lastname' => '');

            // or you could also throw an exception
            throw Exception('Invalid name specified.');
        }

        $parts     = explode(" ", $name);
        $lastname  = array_pop($parts);
        $firstname = implode(" ", $parts);

        return array('firstname' => $firstname, 'lastname' => $lastname);
    }

    /**
     * @param $data
     * @return \Magento\Framework\Model\AbstractExtensibleModel|\Magento\Sales\Api\Data\OrderInterface|object|void|null
     */
    public function createOrderByQuote($quote, $shippingData){
        if(!$quote){
            $this->logger->debug('Failed to load quote');
            return;
        }
        $response = [];

        if(!$quote->getCustomerId() && !$quote->getCustomerEmail()){
            $quote->setCustomerEmail('dnk@dnk.dk');
            $quote->setCustomerIsGuest(1);
        }

        $defaultValue = 'DNK';
        $defaultAddress = [
            'firstname' => $defaultValue,
            'lastname' => $defaultValue,
            'street' => $defaultValue,
            'city' => $defaultValue,
            'country_id' => 'DK',
            'region' => $defaultValue,
            'postcode' => $defaultValue,
            'telephone' => $defaultValue,
            'vat_id' => '',
            'save_in_address_book' => 0
        ];

        $quote->getBillingAddress()->addData($defaultAddress);
        $quote->getShippingAddress()->addData($defaultAddress);

        $shippingMethod = 'mobilepay_mobilepay';
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod($shippingMethod);

        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => 'quickpay_gateway']);

        // Collect Totals & Save Quote
        $quote->collectTotals()->save();

        try {
            // Create Order From Quote
            $order = $this->quoteManagement->submit($quote);

            $shippingPrice = $shippingData['price'];
            $grandTotal = $order->getGrandTotal() + $shippingPrice;
            $order->setShippingAmount($shippingPrice);
            $order->setBaseShippingAmount($shippingPrice);
            $order->setShippingDescription('MobilePay - '.$shippingData['title']);
            $order->setGrandTotal($grandTotal);
            $order->setBaseGrandTotal($grandTotal);
            $order->save();

            return $order;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $response['error'] = $e->getMessage();
            return $response;
        }
    }

    /**
     * @param $order
     * @param $data
     */
    public function updateOrderByCallback($order, $data){
        $shippingAddress = $data->shipping_address;
        $billingAddress = $data->invoice_address;

        if($shippingAddress && !$billingAddress){
            $billingAddress = $shippingAddress;
        }

        if(!$shippingAddress && $billingAddress){
            $shippingAddress = $billingAddress;
        }

        if(!$shippingAddress && !$billingAddress){
            $this->logger->debug('Empty address data from mobilepay');
            return;
        }

        if(!$order->getCustomerId()){
            $order->setCustomerEmail($billingAddress->email);
        }

        $billingName = $this->splitCustomerName($billingAddress->name);
        $billingStreet = [$billingAddress->street, $billingAddress->house_number];
        $order->getBillingAddress()->addData(
            [
                'firstname' => $billingName['firstname'],
                'lastname' => $billingName['lastname'],
                'street' => implode(' ',$billingStreet),
                'city' => $billingAddress->city ? $billingAddress->city : '-',
                'country_id' => $billingAddress->country_code ? $billingAddress->country_code : 'DK',
                'region' => $billingAddress->region,
                'postcode' => $billingAddress->zip_code ? $billingAddress->zip_code : '-',
                'telephone' => $billingAddress->phone_number ? $billingAddress->phone_number : '-',
                'vat_id' => $billingAddress->vat_no,
                'save_in_address_book' => 0
            ]
        );

        $shippingName = $this->splitCustomerName($shippingAddress->name);
        $shippingStreet = [$shippingAddress->street, $shippingAddress->house_number];
        $order->getShippingAddress()->addData([
            'firstname' => $shippingName['firstname'],
            'lastname' => $shippingName['lastname'],
            'street' => implode(' ',$shippingStreet),
            'city' => $shippingAddress->city ? $shippingAddress->city : '-',
            'country_id' => $shippingAddress->country_code ? $shippingAddress->country_code : 'DK',
            'region' => $shippingAddress->region,
            'postcode' => $shippingAddress->zip_code ? $shippingAddress->zip_code : '-',
            'telephone' => $shippingAddress->phone_number ? $shippingAddress->phone_number : '-',
            'vat_id' => $shippingAddress->vat_no,
            'save_in_address_book' => 0
        ]);

        try {
            $order->save();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return $order;
    }

    public function createOrder($data){
        $quote = $this->getQuoteByOrderId($data->order_id);
        if(!$quote){
            $this->logger->debug('Failed to load quote');
            return;
        }

        $shippingAddress = $data->shipping_address;
        $billingAddress = $data->invoice_address;

        if($shippingAddress && !$billingAddress){
            $billingAddress = $shippingAddress;
        }

        if(!$shippingAddress && $billingAddress){
            $shippingAddress = $billingAddress;
        }

        if(!$shippingAddress && !$billingAddress){
            $this->logger->debug('Empty address data from mobilepay');
            return;
        }

        if(!$quote->getCustomerId()){
            $quote->setCustomerEmail($billingAddress->email);
            $quote->setCustomerIsGuest(1);
        }

        $billingName = $this->splitCustomerName($billingAddress->name);
        $billingStreet = [$billingAddress->street, $billingAddress->house_number];
        if($quote->getBillingAddress()){
            $quote->getBillingAddress()->addData(
                [
                    'firstname' => $billingName['firstname'],
                    'lastname' => $billingName['lastname'],
                    'street' => implode(' ',$billingStreet),
                    'city' => $billingAddress->city ? $billingAddress->city : '-',
                    'country_id' => $billingAddress->country_code ? $billingAddress->country_code : 'DK',
                    'region' => $billingAddress->region,
                    'postcode' => $billingAddress->zip_code ? $billingAddress->zip_code : '-',
                    'telephone' => $billingAddress->phone_number ? $billingAddress->phone_number : '-',
                    'vat_id' => $billingAddress->vat_no,
                    'save_in_address_book' => 0
                ]
            );
        }

        $shippingName = $this->splitCustomerName($shippingAddress->name);
        $shippingStreet = [$shippingAddress->street, $shippingAddress->house_number];
        if($quote->getShippingAddress()){
            $quote->getShippingAddress()->addData([
                'firstname' => $shippingName['firstname'],
                'lastname' => $shippingName['lastname'],
                'street' => implode(' ',$shippingStreet),
                'city' => $shippingAddress->city ? $shippingAddress->city : '-',
                'country_id' => $shippingAddress->country_code ? $shippingAddress->country_code : 'DK',
                'region' => $shippingAddress->region,
                'postcode' => $shippingAddress->zip_code ? $shippingAddress->zip_code : '-',
                'telephone' => $shippingAddress->phone_number ? $shippingAddress->phone_number : '-',
                'vat_id' => $shippingAddress->vat_no,
                'save_in_address_book' => 0
            ]);
        }


        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => 'quickpay_gateway']);

        // Collect Totals & Save Quote
        $quote->collectTotals()->save();

        try {
            // Create Order From Quote
            $order = $this->quoteManagement->submit($quote);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return $order;
    }
}