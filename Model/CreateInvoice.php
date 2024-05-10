<?php

/**
 * @author Mathias Matas Hennig <mathias@tezus.com.br>
 */

namespace Paghiper\Magento2\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Paghiper\Magento2\Helper\Data;

class CreateInvoice
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var Data
     */
    protected $data;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Transaction $transaction
     * @param Data $data
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceService           $invoiceService,
        InvoiceSender            $invoiceSender,
        Transaction              $transaction,
        Data                     $data
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceService =  $invoiceService;
        $this->transaction =     $transaction;
        $this->invoiceSender =   $invoiceSender;
        $this->data =            $data;
    }

    /**
     * Execute
     *
     * @param mixed $order
     * @throws LocalizedException
     * @throws \Exception
     */
    public function execute($order)
    {
        if ((int) $this->data->getInvoiceAfterConfirmation() === 1) {
            /** @var \Magento\Sales\Model\Order $order */
            if ($order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->save();

                $transactionSave = $this->transaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());

                $transactionSave->save();

                $this->invoiceSender->send($invoice);

                $order->addCommentToStatusHistory(__(
                    'Invoice Number #%1 has been created. PagHiper Transaction Id: %2',
                    [$invoice->getId(), $order->getData('paghiper_transaction')]
                ));
            }
        }
    }
}
