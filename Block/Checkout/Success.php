<?php
/**
 * @updated_for_magento_2.4.9_and_php_8.4
 */

namespace Paghiper\Magento2\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order;
use Paghiper\Magento2\Helper\Data as HelperData;

class Success extends Template
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var Order|null
     */
    protected $order = null;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param OrderFactory $orderFactory
     * @param HelperData $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        OrderFactory $orderFactory,
        HelperData $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->orderFactory = $orderFactory;
        $this->helperData = $helper;
    }

    /**
     * Get pix code
     *
     * @return string|null
     */
    public function getPixcode()
    {
        return $this->checkoutSession->getPixcode();
    }

    /**
     * Get order
     *
     * @return Order|null
     */
    public function getOrder()
    {
        if ($this->order === null) {
            $lastRealOrderId = $this->checkoutSession->getLastRealOrderId();
            if ($lastRealOrderId) {
                $this->order = $this->orderFactory->create()->loadByIncrementId($lastRealOrderId);
            }
        }
        return $this->order;
    }

    /**
     * Get payment method
     *
     * @return string|null
     */
    public function getPaymentMethod(): ?string
    {
        $order = $this->getOrder();
        if (!$order) {
            return null;
        }
        $payment = $order->getPayment();
        return $payment ? $payment->getMethod() : null;
    }

    /**
     * Get payment info
     *
     * @return array|false
     */
    public function getPaymentInfo()
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }

        $payment = $order->getPayment();
        if ($payment) {
            $paymentMethod = $payment->getMethod();
            switch ($paymentMethod) {
                case 'paghiper_boleto':
                    return [
                        'tipo'  => 'Boleto',
                        'url'   => $order->getPaghiperBoleto(),
                        'texto' => 'Clique aqui para imprimir seu boleto.'
                    ];
                case 'paghiper_pix':
                    return [
                        'tipo'  => 'Pix',
                        'url'   => $order->getPaghiperPix(),
                        'texto' => 'Clique aqui para ver seu QRCode.'
                    ];
            }
        }
        return false;
    }

    /**
     * Get customer id
     *
     * @return int|null
     */
    public function getCustomerId()
    {
        $customer = $this->customerSession->getCustomer();
        return $customer ? $customer->getId() : null;
    }

    /**
     * Get Pix Expiration in Minutes
     *
     * @return string
     */
    public function getExpirationPix()
    {
        $minutes = (int)$this->helperData->getPixExpirationInMinutes();
        return $this->formatExpirationTime($minutes);
    }

    /**
     * Format expiration time into a readable string
     *
     * @param int $minutes
     * @return string
     */
    protected function formatExpirationTime(int $minutes): string
    {
        if ($minutes < 1) {
            return 'imediatamente';
        }

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