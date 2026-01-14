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
        \Paghiper\Magento2\Helper\Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $registry, $data);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->helperData = $helper;
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

  /**
   * Get Pix Expiration in Minutes
   *
   * @return mixed
   */
  public function getExpirationPix()
  {
    return  $this->formatExpirationTime($this->helperData->getPixExpirationInMinutes());
  }

  function formatExpirationTime($minutes)
  {
    if ($minutes < 60) {
      return $minutes . ' minuto' . ($minutes !== 1 ? 's' : '');
    }

    if ($minutes < 1440) {
      $hours = intdiv($minutes, 60);
      $remainingMinutes = $minutes % 60;

      $result = $hours . ' hora' . ($hours !== 1 ? 's' : '');

      if ($remainingMinutes > 0) {
        $result .= ' e ' . $remainingMinutes . ' minuto' . ($remainingMinutes !== 1 ? 's' : '');
      }

      return $result;
    }

    $days = intdiv($minutes, 1440);
    $remainingMinutes = $minutes % 1440;
    $hours = intdiv($remainingMinutes, 60);

    $result = $days . ' dia' . ($days !== 1 ? 's' : '');

    if ($hours > 0) {
      $result .= ' e ' . $hours . ' hora' . ($hours !== 1 ? 's' : '');
    }

    return $result;
  }
  
}
