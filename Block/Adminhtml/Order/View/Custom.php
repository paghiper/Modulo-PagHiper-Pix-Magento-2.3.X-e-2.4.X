<?php
/**
 * @updated_for_magento_2.4.9_and_php_8.4
 */

namespace Paghiper\Magento2\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order;

class Custom extends Template
{
    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * Cache local para a instância do pedido atual
     * @var Order|null
     */
    protected $orderInstance = null;

    /**
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->orderFactory = $orderFactory;
    }

    /**
     * Recupera a ordem com cache em memória interna para evitar múltiplas queries
     *
     * @return Order|null
     */
    protected function getOrder(): ?Order
    {
        if ($this->orderInstance === null) {
            $orderId = $this->getRequest()->getParam('order_id');
            if ($orderId) {
                $order = $this->orderFactory->create()->load($orderId);
                if ($order->getId()) {
                    $this->orderInstance = $order;
                }
            }
        }
        return $this->orderInstance;
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
}