<?php
/**
 * @author Mathias Matas Hennig <mathias@tezus.com.br>
 * @updated_for_magento_2.4.9 - Correção de Payload Real PagHiper
 */

namespace Paghiper\Magento2\Controller\Notification;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Paghiper\Magento2\Helper\Data;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Paghiper\Magento2\Model\CreateInvoice;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;

class UpdateStatus implements ActionInterface, CsrfAwareActionInterface
{
    protected const STATUS_SUCCESS = 'success';
    protected const STATUS_PAID = 'paid';
    protected const STATUS_REFUNDED = 'refunded';
    protected const STATUS_CANCELED = 'canceled';
    protected const URL_BOLETO = "https://api.paghiper.com/transaction/notification/";
    protected const URL_PIX = "https://pix.paghiper.com/invoice/notification/";

    protected $request;
    protected $orderRepository;
    protected $helperData;
    protected $searchCriteriaBuilder;
    protected $_logger;
    protected $resultFactory;
    protected $createInvoice;
    protected $curl;
    protected $scopeConfig;

    public function __construct(
        RequestInterface $request,
        OrderRepositoryInterface $orderRepository,
        Data $helperData,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        ResultFactory $resultFactory,
        CreateInvoice $createInvoice,
        Curl $curl,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->helperData = $helperData;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_logger = $logger;
        $this->resultFactory = $resultFactory;
        $this->createInvoice = $createInvoice;
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Gravação de Log para acompanhamento em var/log/paghiper_webhook.log
     */
    private function debugLog($message) {
      // Verifica se a flag de debug está ativa no painel do Magento
      $isDebugEnabled = $this->scopeConfig->isSetFlag(
        'payment/paghiper_general/debug_log',
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
      );
      
      if($isDebugEnabled){
        $logPath = BP . '/var/log/paghiper_webhook.log';
        $formattedMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($logPath, $formattedMessage, FILE_APPEND);
      }
    }

    public function execute()
    {
        $resultRaw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $params = $this->request->getParams();
        
        $this->debugLog("=== NOVA NOTIFICAÇÃO RECEBIDA ===");
        $this->debugLog("Parâmetros do Webhook: " . json_encode($params));

        if (empty($params) || !isset($params['notification_id'], $params['transaction_id'])) {
            $this->debugLog("Erro: Parâmetros obrigatórios ausentes no envio da PagHiper.");
            $resultRaw->setHttpResponseCode(400);
            $resultRaw->setContents('Invalid parameters');
            return $resultRaw;
        }

        try {
            $notificationId = (string) $params['notification_id'];
            $transactionId  = (string) $params['transaction_id'];

            // Identifica dinamicamente se a chamada veio do fluxo Pix ou Boleto
            if (isset($params['source_api']) && $params['source_api'] === 'https://pix.paghiper.com') {
                $methodCode = 'paghiper_pix';
            } elseif (isset($params['source_api']) && $params['source_api'] === 'https://api.paghiper.com') {
                $methodCode = 'paghiper_boleto';
            }else{
                $this->debugLog("Erro: ausência no indicador de origem do retorno");
                $resultRaw->setHttpResponseCode(400);
                $resultRaw->setContents('Invalid parameters source_api');
                return $resultRaw;
            }

            // Carrega credenciais do escopo correto do painel
            $token = $this->scopeConfig->getValue("payment/paghiper_general/token", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
              ?: (method_exists($this->helperData, 'getAcessToken') ? $this->helperData->getAcessToken() : '');
            $apiKey = $this->scopeConfig->getValue("payment/paghiper_general/api_key", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
              ?: (method_exists($this->helperData, 'getApiKey') ? $this->helperData->getApiKey() : '');

            $url = ($methodCode === 'paghiper_pix') ? static::URL_PIX : static::URL_BOLETO;
            
            $postFields = [
                'token' => $token,
                'apiKey' => $apiKey,
                'transaction_id' => $transactionId,
                'notification_id' => $notificationId
            ];
            $this->debugLog("Parâmetros do payload: " . json_encode($postFields) . "url: " . $url);
            // Envia a validação de segurança para a API da PagHiper
            $this->curl->setHeaders(["Content-Type" => "application/json", "Accept" => "application/json"]);
            $this->curl->post($url, json_encode($postFields));
            $responseBody = $this->curl->getBody();
            
            $this->debugLog("Resposta da validação PagHiper: " . $responseBody);
            $responseParsed = $this->helperData->jsonDecode($responseBody);
            
            $rootNode = 'status_request';

            if (!isset($responseParsed[$rootNode])) {
                $this->debugLog("Erro: O nó 'status_request' não existe no retorno. Verifique os Tokens/APIKeys configurados.");
                $resultRaw->setHttpResponseCode(200);
                $resultRaw->setContents('OK');
                return $resultRaw;
            }

            $dataNotification = $responseParsed[$rootNode];
            $orderIncrementId = $dataNotification['order_id'] ?? null;

            if (!$orderIncrementId) {
                $this->debugLog("Erro: 'order_id' não foi enviado dentro do status_request.");
                return $resultRaw->setHttpResponseCode(200)->setContents('OK');
            }

            // Busca o pedido no Magento pelo ID correspondente retornado da PagHiper
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $orderIncrementId, 'eq')->create();
            $orderList = $this->orderRepository->getList($searchCriteria);
            
            if ($orderList->getTotalCount() === 0) {
                $this->debugLog("Erro: Pedido de ID #{$orderIncrementId} não localizado no banco do Magento.");
                return $resultRaw->setHttpResponseCode(200)->setContents('OK');
            }

            $orders = $orderList->getItems();
            $order = reset($orders);

            $resultStatus = $dataNotification['result'] ?? '';
            $event = $dataNotification['status'] ?? '';

            $this->debugLog("Pedido Magento: #{$orderIncrementId} | Status na PagHiper: {$event} | Resultado: {$resultStatus}");

            if ($resultStatus === static::STATUS_SUCCESS) {
                if ($event === static::STATUS_PAID) {
                    if ($order->hasInvoices()) {
                        $this->debugLog("Aviso: O pedido #{$orderIncrementId} já foi faturado anteriormente. Ignorando.");
                    } else {
                        // Mapeia corretamente as taxas baseadas no payload real enviado (value_fee_cents)
                        $paghiperTax = (float) (($dataNotification['value_fee_cents'] ?? 0) / 100);
                        $totalPaid   = (float) (($dataNotification['value_cents_paid'] ?? 0) / 100);
    
                        $order->setData('paghiper_fee_amount', $paghiperTax);
                        $order->setData('base_paghiper_fee_amount', $paghiperTax);
                        
                        $originalGrandTotal = (float) $order->getGrandTotal();
                     
                        // Se o valor pago for maior que o total original do pedido, houve juros/multa
                        if ($totalPaid > $originalGrandTotal) {
                          $interestAmount = $totalPaid - $originalGrandTotal;
    
                          // Salva o valor do juros/multa em campos customizados para histórico/relatórios
                          $order->setData('paghiper_interest_amount', $interestAmount);
                          $order->setData('base_paghiper_interest_amount', $interestAmount);
    
                          // Força o Grand Total do pedido a subir para o valor real pago.
                          // Isso garante que a Fatura (Invoice) nativa seja gerada com o valor exato recebido.
                          $order->setGrandTotal($totalPaid);
                          $order->setBaseGrandTotal($totalPaid);
    
                          $this->debugLog("Juros/Multa detectados: R$ " . number_format($interestAmount, 2, ',', '.') . ". Atualizando Grand Total do Pedido #{$orderIncrementId} para R$ " . number_format($totalPaid, 2, ',', '.'));
                        }elseif($totalPaid < $originalGrandTotal) {
                          $discountAmount = $originalGrandTotal - $totalPaid;

                          // Salva o valor do desconto antecipado em campos customizados para histórico/relatórios
                          $order->setData('paghiper_discount_amount', $discountAmount);
                          $order->setData('base_paghiper_discount_amount', $discountAmount);

                          // Força o Grand Total do pedido a descer para o valor real pago.
                          $order->setGrandTotal($totalPaid);
                          $order->setBaseGrandTotal($totalPaid);

                          $this->debugLog("Desconto antecipado detectado: R$ " . number_format($discountAmount, 2, ',', '.') . ". Atualizando Grand Total do Pedido #{$orderIncrementId} para R$ " . number_format($totalPaid, 2, ',', '.'));
                        }

                        try {
                            $this->debugLog("Gerando Fatura nativa (Invoice) no Magento...");
                            $this->createInvoice->execute($order);
                        } catch (\Exception $invEx) {
                            $this->debugLog("Alerta Invoice: Faturamento automático gerou uma mensagem (pode ser normal dependendo do fluxo): " . $invEx->getMessage());
                        }

                        // Altera forçadamente o Status e Estado para Processing (Pago)
                        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                        $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                        
                        $order->addCommentToStatusHistory(__(
                            'Pagamento confirmado via PagHiper. Valor Recebido: R$ %1. Taxa retida: R$ %2',
                            [number_format($totalPaid, 2, ',', '.'), number_format($paghiperTax, 2, ',', '.')]
                        ));

                        $this->orderRepository->save($order);
                        $this->debugLog("Sucesso: Pedido #{$orderIncrementId} atualizado com sucesso para Pago (Processing).");
                    }
                } elseif ($event === static::STATUS_REFUNDED || $event === static::STATUS_CANCELED) {
                    if ($order->canCancel()) {
                        $order->cancel();
                        $order->addCommentToStatusHistory(__('Pedido cancelado automaticamente via notificação PagHiper.'));
                        $this->orderRepository->save($order);
                        $this->debugLog("Sucesso: Pedido #{$orderIncrementId} cancelado no Magento.");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->debugLog("EXCEÇÃO INTERNA NO WEBHOOK: " . $e->getMessage());
        }

        $resultRaw->setHttpResponseCode(200);
        $resultRaw->setContents('OK');
        return $resultRaw;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException { return null; }
    public function validateForCsrf(RequestInterface $request): ?bool { return true; }
}