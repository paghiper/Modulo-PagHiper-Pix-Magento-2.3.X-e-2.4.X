<?php

namespace Paghiper\Magento2\Model\Invoice\Total;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class Tax extends AbstractTotal
{
    /**
     * @param Invoice $invoice
     * @return $this
     */
    public function collect(Invoice $invoice): Tax
    {
        $invoice->setData('paghiper_fee_amount', 0);
        $invoice->setData('base_paghiper_fee_amount', 0);

        $amount = $invoice->getOrder()->getDataByKey('paghiper_fee_amount');
        $invoice->setData('paghiper_fee_amount', $amount);
        $amount = $invoice->getOrder()->getDataByKey('base_paghiper_fee_amount');
        $invoice->setData('base_paghiper_fee_amount', $amount);

        $invoice->setGrandTotal($invoice->getGrandTotal() + $invoice->getDataByKey('paghiper_fee_amount'));
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $invoice->getDataByKey('base_paghiper_fee_amount'));

        return $this;
    }
}
