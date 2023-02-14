<?php

namespace Paghiper\Magento2\Block\Adminhtml\Order\View;

class Custom extends \Magento\Backend\Block\Template
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Sales\Model\Order $order,
        array $data = []
    ) {
        $this->order = $order;
        parent::__construct($context, $data);
    }

    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function getPaymentMethod()
    {
        $order_id = $this->getRequest()->getParam('order_id');
        $order = $this->order->load($order_id);
        $payment = $order->getPayment();
        return $payment->getMethod();
    }

    public function getPaymentInfo()
    {
        $order_id = $this->getRequest()->getParam('order_id');
        $order = $this->order->load($order_id);
        if ($payment = $order->getPayment()) {
            $paymentMethod = $payment->getMethod();
            switch ($paymentMethod) {
                case 'paghiper_boleto':
                    return [
                'tipo' => 'Boleto',
                'url' => $order->getPaghiperBoleto(),
                'texto' => 'Clique aqui para imprimir seu boleto.'
                ];
                case 'paghiper_pix':
                    return [
                'tipo' => 'Pix',
                'url' => $order->getPaghiperPix(),
                'texto' => 'Clique aqui para ver seu QRCode.'
                ];
            }
        }
        return false;
    }
}
