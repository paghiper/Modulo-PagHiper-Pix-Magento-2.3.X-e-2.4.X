<?php

namespace Paghiper\Magento2\Block\Order\Payment;

class Info extends \Magento\Framework\View\Element\Template {
  protected $_checkoutSession;
  protected $_orderFactory;

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

  public function getPaymentMethod() {
    $order_id = $this->getRequest()->getParam('order_id');
    $order = $this->_orderFactory->load($order_id);
    $payment = $order->getPayment();
    return $payment->getMethod();
  }

  public function getPaymentInfo() {
    $order_id = $this->getRequest()->getParam('order_id');
    $order = $this->_orderFactory->load($order_id);
    if ($payment = $order->getPayment()) {
      $paymentMethod = $payment->getMethod();
      switch ($paymentMethod) {
        case 'paghiper_boleto':
          return array(
            'tipo' => 'Boleto',
            'url' => $order->getPaghiperBoleto(),
            'texto' => 'Clique aqui para visualizar seu boleto.',
            'linha-digitavel' => $order->getPaghiperBoletoDigitavel()
          );
          break;
        case 'paghiper_pix':
          return array(
            'tipo' => 'Pix',
            'url' => $order->getPaghiperPix(),
            'texto' => 'Clique aqui para ver seu QRCode.',
            'chavepix' => $order->getPaghiperChavepix()
          );
          break;
      }
    }
    return false;
  }
}
