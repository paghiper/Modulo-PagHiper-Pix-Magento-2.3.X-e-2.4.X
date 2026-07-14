<?php

namespace Paghiper\Magento2\Model\Invoice\Total;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

/**
 * Class Tax
 * Updated for Magento 2.4.9 & PHP 8.4+
 */
class Tax extends AbstractTotal
{
  /**
   * Collect invoice totals for PagHiper fee and interest/fine
   *
   * @param Invoice $invoice
   * @return \Magento\Sales\Model\Order\Invoice\Total\AbstractTotal
   */
  public function collect(Invoice $invoice): \Magento\Sales\Model\Order\Invoice\Total\AbstractTotal
  {
    // Inicializa todas as variáveis de taxa e juros na fatura com zero
    $invoice->setData('paghiper_fee_amount', 0);
    $invoice->setData('base_paghiper_fee_amount', 0);
    $invoice->setData('paghiper_interest_amount', 0);
    $invoice->setData('base_paghiper_interest_amount', 0);
    $invoice->setData('paghiper_discount_amount', 0);
    $invoice->setData('base_paghiper_discount_amount', 0);

    // Obtém a instância do pedido associado a esta fatura
    $order = $invoice->getOrder();

    // Recupera com segurança os valores salvos originalmente no pedido (Order)
    $paghiperFeeAmount = (float)$order->getDataByKey('paghiper_fee_amount');
    $basePaghiperFeeAmount = (float)$order->getDataByKey('base_paghiper_fee_amount');

    // Recupera os valores de juros/multa que injetamos lá no UpdateStatus.php
    $paghiperInterestAmount = (float)$order->getDataByKey('paghiper_interest_amount');
    $basePaghiperInterestAmount = (float)$order->getDataByKey('base_paghiper_interest_amount');

    $paghiperDiscountAmount = (float)$order->getDataByKey('paghiper_discount_amount');
    $basePaghiperDiscountAmount = (float)$order->getDataByKey('base_paghiper_discount_amount');

    // 3. Grava/Vincula estes valores convertidos diretamente nos dados desta Fatura (Invoice)
    $invoice->setData('paghiper_fee_amount', $paghiperFeeAmount);
    $invoice->setData('base_paghiper_fee_amount', $basePaghiperFeeAmount);

    $invoice->setData('paghiper_interest_amount', $paghiperInterestAmount);
    $invoice->setData('base_paghiper_interest_amount', $basePaghiperInterestAmount);

    $invoice->setData('paghiper_discount_amount', $paghiperDiscountAmount);
    $invoice->setData('base_paghiper_discount_amount', $basePaghiperDiscountAmount);
    

    // B. Soma os juros/multas (em caso de pagamento a maior)
    if ($paghiperInterestAmount > 0) {
      $invoice->setGrandTotal((float)$invoice->getGrandTotal() + $paghiperInterestAmount);
      $invoice->setBaseGrandTotal((float)$invoice->getBaseGrandTotal() + $basePaghiperInterestAmount);
    }

    // C. Subtrai o desconto de pagamento antecipado (em caso de pagamento a menor)
    if ($paghiperDiscountAmount > 0) {
      $invoice->setGrandTotal((float)$invoice->getGrandTotal() - $paghiperDiscountAmount);
      $invoice->setBaseGrandTotal((float)$invoice->getBaseGrandTotal() - $basePaghiperDiscountAmount);
    }

    return $this;
  }
}