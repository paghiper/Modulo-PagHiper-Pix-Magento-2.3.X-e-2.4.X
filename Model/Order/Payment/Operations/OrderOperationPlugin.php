<?php
/**
 * @author Mathias Matas Hennig <mathias@tezus.com.br>
 * @updated_for_magento_2.4.9
 */

namespace Paghiper\Magento2\Plugin\Order\Payment\Operations;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Operations\OrderOperation as SubjectOrderOperation;

class OrderOperationPlugin
{
    /**
     * Intercepta o método order para aplicar a regra de negócio do PagHiper
     * de forma compatível com o Magento 2.4.9 e PHP 8.4+
     *
     * @param SubjectOrderOperation $subject
     * @param callable $proceed
     * @param OrderPaymentInterface $payment
     * @param string|float $amount
     * @return OrderPaymentInterface
     */
    public function aroundOrder(
        SubjectOrderOperation $subject,
        callable $proceed,
        OrderPaymentInterface $payment,
        $amount
    ) {
        /** @var Payment $payment */
        
        // Atualiza os totais utilizando o formato do próprio objeto de pagamento
        $amount = $payment->formatAmount($amount, true);

        // Obtém o pedido associado
        $order = $payment->getOrder();

        // Obtém e configura a instância do método de pagamento PagHiper
        $method = $payment->getMethodInstance();
        $method->setStore($order->getStoreId());
        
        // Executa a chamada de order do método (Pix/Boleto)
        $method->order($payment, $amount);

        // Retornamos o pagamento sem chamar o '$proceed()', 
        // evitando que o core do Magento crie transações indesejadas neste ponto.
        return $payment;
    }
}