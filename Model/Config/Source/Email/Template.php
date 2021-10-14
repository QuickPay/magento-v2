<?php

namespace QuickPay\Gateway\Model\Config\Source\Email;

class Template extends \Magento\Framework\DataObject implements \Magento\Framework\Option\ArrayInterface
{
    const DEFAULT_TEMPLATE_ID = 'quickpay_makepayment_email_template';

    private $_coreRegistry;

    private $_emailConfig;

    protected $_templatesFactory;


    public function __construct(
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Email\Model\ResourceModel\Template\CollectionFactory $templatesFactory,
        \Magento\Email\Model\Template\Config $emailConfig,
        array $data = []
    ) {
        parent::__construct($data);
        $this->_coreRegistry = $coreRegistry;
        $this->_templatesFactory = $templatesFactory;
        $this->_emailConfig = $emailConfig;
    }

    /**
     * Generate list of email templates
     *
     * @return array
     */
    public function toOptionArray()
    {
        /** @var $collection \Magento\Email\Model\ResourceModel\Template\Collection */
        if (!($collection = $this->_coreRegistry->registry('config_system_email_template'))) {
            $collection = $this->_templatesFactory->create();
            $collection->load();
            $this->_coreRegistry->register('config_system_email_template', $collection);
        }

        $defaultTemplate = [];
        $defaultTemplate[] = [
            'value' => self::DEFAULT_TEMPLATE_ID,
            'label' => $this->_emailConfig->getTemplateLabel(self::DEFAULT_TEMPLATE_ID)
        ];

        $options = array_merge($defaultTemplate, $collection->toOptionArray());

        return $options;
    }
}