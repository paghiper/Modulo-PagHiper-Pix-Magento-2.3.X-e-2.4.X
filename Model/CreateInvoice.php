<?php
/**
 * @updated_for_magento_2.4.9
 */

namespace Paghiper\Magento2\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\App\ResourceConnection;
use Paghiper\Magento2\Helper\Data;

class CreateInvoice
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Data
     */
    protected $data;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param ResourceConnection $resourceConnection
     * @param Data $data
     */
    public function __construct(
        OrderRepositoryInterface   $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        InvoiceService             $invoiceService,
        InvoiceSender              $invoiceSender,
        ResourceConnection         $resourceConnection,
        Data                       $data
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceService =  $invoiceService;
        $this->invoiceSender =   $invoiceSender;
        $this->resourceConnection = $resourceConnection;
        $this->data =            $data;
    }

    /**
     * Execute Invoice Creation
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     * @throws LocalizedException
     * @throws \Exception
     */
    public function execute($order)
    {
        if ((int) $this->data->getInvoiceAfterConfirmation() === 1) {
            if ($order->canInvoice()) {
                // Prepara a fatura com base no pedido
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();

                // Obtém a conexão com a base de dados para garantir atomicidade (transação segura)
                $connection = $this->resourceConnection->getConnection();
                $connection->beginTransaction();

                try {
                    // Salva a fatura utilizando o repositório correto do Magento 2.4.9
                    $this->invoiceRepository->save($invoice);
                    
                    // Salva as alterações de estado ocorridas no pedido
                    $this->orderRepository->save($order);

                    // Confirma as alterações na base de dados
                    $connection->commit();
                } catch (\Exception $e) {
                    // Desfaz as alterações em caso de erro concorrente
                    $connection->rollBack();
                    throw $e;
                }

                // Envia o e-mail de notificação da fatura para o cliente
                $this->invoiceSender->send($invoice);

                // Adiciona o comentário ao histórico do pedido
                $order->addCommentToStatusHistory(__(
                    'Invoice Number #%1 has been created. PagHiper Transaction Id: %2',
                    [$invoice->getId(), $order->getData('paghiper_transaction')]
                ));
                
                // Persiste o comentário adicionado ao histórico
                $this->orderRepository->save($order);
            }
        }
    }
}