<?php
/**
 * @updated_for_magento_2.4.9_and_php_8.4
 */

namespace Paghiper\Magento2\Block\Adminhtml\Sales\Order\Invoice;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order\Invoice;

class Totals extends Template
{
    /**
     * Order invoice
     * @var Invoice|null
     */
    protected $_invoice;

    /**
     * @var DataObject|null
     */
    protected $_source;

    /**
     * Get data (totals) source model
     *
     * @return DataObject
     */
    public function getSource(): DataObject
    {
        return $this->getParentBlock()->getSource();
    }

    /**
     * Get invoice
     *
     * @return Invoice|null
     */
    public function getInvoice(): ?Invoice
    {
        $parentBlock = $this->getParentBlock();
        return $parentBlock ? $parentBlock->getInvoice() : null;
    }

    /**
     * Initialize payment fee totals
     *
     * @return self
     */
    public function initTotals(): self
    {
        $source = $this->getSource();
        if (!$source) {
            return $this;
        }

        $feeAmount = $source->getDataByKey('paghiper_fee_amount');
        if (!$feeAmount) {
            return $this;
        }

        $total = new DataObject(
            [
                'code'  => 'paghiper_fee',
                'value' => $feeAmount,
                'label' => __('Interest/Paghiper Fine')
            ]
        );

        $parentBlock = $this->getParentBlock();
        if ($parentBlock) {
            $parentBlock->addTotal($total, 'paghiper_fee');
        }

        return $this;
    }
}