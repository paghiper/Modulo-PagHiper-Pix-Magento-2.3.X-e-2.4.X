<?php
/**
 * @author Mathias Matas Hennig <mathias@tezus.com.br>
 */

namespace Paghiper\Magento2\Model\Order\Payment\Operations;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Class Order
 */
class OrderOperation extends \Magento\Sales\Model\Order\Payment\Operations\OrderOperation
{
    /**
     * @param OrderPaymentInterface $payment
     * @param string|float $amount
     * @return OrderPaymentInterface
     */
    public function order(OrderPaymentInterface $payment, $amount)
    {
        /**
         * @var $payment Payment
         */
        // update totals
        $amount = $payment->formatAmount($amount, true);

        // do ordering
        $order = $payment->getOrder();

        $method = $payment->getMethodInstance();
        $method->setStore($order->getStoreId());
        $method->order($payment, $amount);

        return $payment;
    }
}
