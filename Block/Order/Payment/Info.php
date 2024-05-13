<?php

namespace Paghiper\Magento2\Block\Order\Payment;

class Info extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_orderFactory;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order $orderFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order $orderFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
    }

    /**
     * Get payment method
     *
     * @return string
     */
    public function getPaymentMethod()
    {
        $order_id = $this->getRequest()->getParam('order_id');
        $order = $this->_orderFactory->load($order_id);
        $payment = $order->getPayment();
        return $payment->getMethod();
    }

    /**
     * Get payment info
     *
     * @return array|false
     */
    public function getPaymentInfo()
    {
        $order_id = $this->getRequest()->getParam('order_id');
        $order = $this->_orderFactory->load($order_id);
        if ($payment = $order->getPayment()) {
            $paymentMethod = $payment->getMethod();
            switch ($paymentMethod) {
                case 'paghiper_boleto':
                    return [
                'tipo' => 'Boleto',
                'url' => $order->getPaghiperBoleto(),
                'texto' => 'Clique aqui para visualizar seu boleto.',
                'linha-digitavel' => $order->getPaghiperBoletoDigitavel()
                ];
                case 'paghiper_pix':
                    return [
                'tipo' => 'Pix',
                'url' => $order->getPaghiperPix(),
                'texto' => 'Clique aqui para ver seu QRCode.',
                'chavepix' => $order->getPaghiperChavepix()
                ];
            }
        }
        return false;
    }
}
