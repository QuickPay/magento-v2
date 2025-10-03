<?php

namespace QuickPay\Gateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Order extends AbstractHelper
{
    /**
     * Get country path
     */
    const CONFIG_COUNTRY_CODE_PATH = 'general/country/default';

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
     * @var \Magento\Directory\Model\Country
     */
    protected $country;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param \Magento\Framework\App\Filesystem\DirectoryList $dir
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Directory\Model\Country $country
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Framework\App\Filesystem\DirectoryList $dir,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Directory\Model\Country $country
    ){
        $this->resource = $resource;
        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->dir = $dir;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->country = $country;
    }

    /**
     * @param $orderId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteByOrderId($orderId)
    {
        $connection = $this->resource->getConnection('core_read');

        $select = $connection->select()->from(
            $this->resource->getTableName('quote'),
            ['entity_id']
        )->where('reserved_order_id = ?', $orderId);

        $quoteId = $connection->fetchOne($select);

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
     * Get Country code by website scope
     *
     * @return string
     */
    public function getCountryByWebsite()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_COUNTRY_CODE_PATH,
            ScopeInterface::SCOPE_WEBSITES
        );
    }

    /**
     * @param string $code
     * @return mixed
     */
    public function convertCountryAlphas3To2($code = 'DK') {
        $countries = json_decode('{"AFG":"AF","ALA":"AX","ALB":"AL","DZA":"DZ","ASM":"AS","AND":"AD","AGO":"AO","AIA":"AI","ATA":"AQ","ATG":"AG","ARG":"AR","ARM":"AM","ABW":"AW","AUS":"AU","AUT":"AT","AZE":"AZ","BHS":"BS","BHR":"BH","BGD":"BD","BRB":"BB","BLR":"BY","BEL":"BE","BLZ":"BZ","BEN":"BJ","BMU":"BM","BTN":"BT","BOL":"BO","BIH":"BA","BWA":"BW","BVT":"BV","BRA":"BR","VGB":"VG","IOT":"IO","BRN":"BN","BGR":"BG","BFA":"BF","BDI":"BI","KHM":"KH","CMR":"CM","CAN":"CA","CPV":"CV","CYM":"KY","CAF":"CF","TCD":"TD","CHL":"CL","CHN":"CN","HKG":"HK","MAC":"MO","CXR":"CX","CCK":"CC","COL":"CO","COM":"KM","COG":"CG","COD":"CD","COK":"CK","CRI":"CR","CIV":"CI","HRV":"HR","CUB":"CU","CYP":"CY","CZE":"CZ","DNK":"DK","DKK":"DK","DJI":"DJ","DMA":"DM","DOM":"DO","ECU":"EC","Sal":"El","GNQ":"GQ","ERI":"ER","EST":"EE","ETH":"ET","FLK":"FK","FRO":"FO","FJI":"FJ","FIN":"FI","FRA":"FR","GUF":"GF","PYF":"PF","ATF":"TF","GAB":"GA","GMB":"GM","GEO":"GE","DEU":"DE","GHA":"GH","GIB":"GI","GRC":"GR","GRL":"GL","GRD":"GD","GLP":"GP","GUM":"GU","GTM":"GT","GGY":"GG","GIN":"GN","GNB":"GW","GUY":"GY","HTI":"HT","HMD":"HM","VAT":"VA","HND":"HN","HUN":"HU","ISL":"IS","IND":"IN","IDN":"ID","IRN":"IR","IRQ":"IQ","IRL":"IE","IMN":"IM","ISR":"IL","ITA":"IT","JAM":"JM","JPN":"JP","JEY":"JE","JOR":"JO","KAZ":"KZ","KEN":"KE","KIR":"KI","PRK":"KP","KOR":"KR","KWT":"KW","KGZ":"KG","LAO":"LA","LVA":"LV","LBN":"LB","LSO":"LS","LBR":"LR","LBY":"LY","LIE":"LI","LTU":"LT","LUX":"LU","MKD":"MK","MDG":"MG","MWI":"MW","MYS":"MY","MDV":"MV","MLI":"ML","MLT":"MT","MHL":"MH","MTQ":"MQ","MRT":"MR","MUS":"MU","MYT":"YT","MEX":"MX","FSM":"FM","MDA":"MD","MCO":"MC","MNG":"MN","MNE":"ME","MSR":"MS","MAR":"MA","MOZ":"MZ","MMR":"MM","NAM":"NA","NRU":"NR","NPL":"NP","NLD":"NL","ANT":"AN","NCL":"NC","NZL":"NZ","NIC":"NI","NER":"NE","NGA":"NG","NIU":"NU","NFK":"NF","MNP":"MP","NOR":"NO","OMN":"OM","PAK":"PK","PLW":"PW","PSE":"PS","PAN":"PA","PNG":"PG","PRY":"PY","PER":"PE","PHL":"PH","PCN":"PN","POL":"PL","PRT":"PT","PRI":"PR","QAT":"QA","REU":"RE","ROU":"RO","RUS":"RU","RWA":"RW","BLM":"BL","SHN":"SH","KNA":"KN","LCA":"LC","MAF":"MF","SPM":"PM","VCT":"VC","WSM":"WS","SMR":"SM","STP":"ST","SAU":"SA","SEN":"SN","SRB":"RS","SYC":"SC","SLE":"SL","SGP":"SG","SVK":"SK","SVN":"SI","SLB":"SB","SOM":"SO","ZAF":"ZA","SGS":"GS","SSD":"SS","ESP":"ES","LKA":"LK","SDN":"SD","SUR":"SR","SJM":"SJ","SWZ":"SZ","SWE":"SE","CHE":"CH","SYR":"SY","TWN":"TW","TJK":"TJ","TZA":"TZ","THA":"TH","TLS":"TL","TGO":"TG","TKL":"TK","TON":"TO","TTO":"TT","TUN":"TN","TUR":"TR","TKM":"TM","TCA":"TC","TUV":"TV","UGA":"UG","UKR":"UA","ARE":"AE","GBR":"GB","USA":"US","UMI":"UM","URY":"UY","UZB":"UZ","VUT":"VU","VEN":"VE","VNM":"VN","VIR":"VI","WLF":"WF","ESH":"EH","YEM":"YE","ZMB":"ZM","ZWE":"ZW","GBP":"GB","RUB":"RU","NOK":"NO"}',true);

        if(!isset($countries[$code])){
            $defaultCountry = $this->getCountryByWebsite();
            return $defaultCountry;
        } else {
            return $countries[$code];
        }
    }

    /**
     * @param $countryCode
     * @return mixed|null
     */
    public function getDefaultRegionByCountry($countryCode) {
        $regionCollection = $this->country->loadByCode($countryCode)->getRegions();
        $regions = $regionCollection->loadData()->toOptionArray(true);
        if(is_array($regions) && isset($regions[1])){
            return $regions[1];
        }
        return null;
    }

    /**
     * @param $order
     * @return bool
     */
    public function getOrderIsQuickpay($order){
        if(strpos($order->getPayment()->getMethodInstance()->getCode(), 'quickpay') !== false){
            return true;
        }

        return false;
    }
}