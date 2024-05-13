<?php

namespace Paghiper\Magento2\Block\Checkout;

class Success extends \Magento\Sales\Block\Order\Totals
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $registry, $data);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
    }

    /**
     * Get pix code
     *
     * @return mixed
     */
    public function getPixcode()
    {
        return $this->checkoutSession->getPixcode();
    }

    /**
     * Get order
     *
     * @return \Magento\Sales\Model\Order|null
     */
    public function getOrder()
    {
        return  $this->_order = $this->_orderFactory->create()->loadByIncrementId(
            $this->checkoutSession->getLastRealOrderId()
        );
    }

    /**
     * Get customer id
     *
     * @return mixed
     */
    public function getCustomerId()
    {
        return $this->customerSession->getCustomer()->getId();
    }
}
