<?php
/**
 * @updated_for_magento_2.4.9_and_php_8.4
 */

namespace Paghiper\Magento2\Block\Order\Payment;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\OrderFactory;

class Info extends Template
{
    /**
     * @var OrderFactory
     */
    protected $orderFactory;

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
        // Injeção corrigida da Factory legítima para respeitar a arquitetura do Magento 2
        $this->orderFactory = $orderFactory;
    }

    /**
     * Get payment method
     *
     * @return string|null
     */
    public function getPaymentMethod()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if (!$orderId) {
            return null;
        }

        // Carregamento isolado e seguro via Factory
        $order = $this->orderFactory->create()->load($orderId);
        if (!$order->getId()) {
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
        $orderId = $this->getRequest()->getParam('order_id');
        if (!$orderId) {
            return false;
        }

        $order = $this->orderFactory->create()->load($orderId);
        if (!$order->getId()) {
            return false;
        }

        $payment = $order->getPayment();
        if ($payment) {
            $paymentMethod = $payment->getMethod();
            switch ($paymentMethod) {
                case 'paghiper_boleto':
                    return [
                        'tipo'            => 'Boleto',
                        'url'             => $order->getPaghiperBoleto(),
                        'texto'           => 'Clique aqui para visualizar seu boleto.',
                        'linha-digitavel' => $order->getPaghiperBoletoDigitavel()
                    ];
                case 'paghiper_pix':
                    return [
                        'tipo'     => 'Pix',
                        'url'      => $order->getPaghiperPix(),
                        'texto'    => 'Clique aqui para ver seu QRCode.',
                        'chavepix' => $order->getPaghiperChavepix()
                    ];
            }
        }
        return false;
    }
}