<?php

namespace Paghiper\Magento2\Model\Method;

use Exception as ExceptionSituation;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Magento\Framework\HTTP\Client\Curl;

class Pix extends \Magento\Payment\Model\Method\AbstractMethod
{
    protected const CODE = 'paghiper_pix';
    protected $_code = self::CODE;
	
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = false;    
    
    /**
     * ATENÇÃO: Força o Magento a usar o fluxo de inicialização assíncrona,
     * impedindo o bloqueio por 'Payment Review'.
     */
    protected $_isInitializeNeeded = true;

    protected $helperData;
    protected $_loggerInterface;
    private $_curlFactory;
    protected $curl;
    protected $_storeManager;

    public function __construct(
        Curl $curl,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Paghiper\Magento2\Helper\Data $helper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        LoggerInterface $loggerInterface,
        \Magento\Framework\HTTP\Adapter\CurlFactory $_curlFactory,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->helperData = $helper;
        $this->_loggerInterface = $loggerInterface;
        $this->_curlFactory = $_curlFactory;
        $this->curl = $curl;
        $this->_storeManager = $storeManager;
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $infoInstance = $this->getInfoInstance();
        $additionalData = $data->getAdditionalData();

        if (is_array($additionalData)) {
            $cpfCnpj = $additionalData['pix_cpf'] ?? $additionalData['cpfCnpj'] ?? '';
            if (!empty($cpfCnpj)) {
                $infoInstance->setAdditionalInformation('paghiper_cpf_cnpj', preg_replace('/[^0-9]/', '', (string)$cpfCnpj));
            }
        }
        return $this;
    }

    /**
     * Utiliza o fluxo de Initialize nativo para controle absoluto de status
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();
        $billingAddress = $order->getBillingAddress();
        
        // Proteção essencial para produtos Virtuais/Downloadáveis que não possuem dados de entrega tradicionais
        $street = $billingAddress ? $billingAddress->getStreet() : [];

        $taxvat = $payment->getAdditionalInformation('paghiper_cpf_cnpj');
        
        if (empty($taxvat) && $billingAddress) {
            $taxvat = $order->getCustomerTaxvat() ?: $billingAddress->getVatId();
        }

        $apiKey = method_exists($this->helperData, 'getApiKey') ? $this->helperData->getApiKey() : $this->_scopeConfig->getValue('payment/paghiper_pix/token', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
      
        $expirationInMinutes = method_exists($this->helperData, 'getPixExpirationInMinutes') ? $this->helperData->getPixExpirationInMinutes() : 10;
        $discount = ($discount = str_replace("-", "", $order->getDiscountAmount()) * 100) > 0 ? $discount : 0;

        try {
            // Toda a estrutura de dados foi movida para dentro do TRY para evitar falhas silenciosas
            $postFields = [
                'apiKey' => $apiKey,
                'partners_id' => 'KAPK109D',
                'order_id' => $order->getIncrementId(),
                'payer_email' => $order->getCustomerEmail(),
                'payer_name' => $order->getCustomerName() ?: ($billingAddress ? ($billingAddress->getFirstname() . ' ' . $billingAddress->getLastname()) : ''),
                'payer_cpf_cnpj' => preg_replace('/[^0-9]/', '', (string)$taxvat),
                'payer_phone' => preg_replace('/[^0-9]/', '', (string)($billingAddress ? $billingAddress->getTelephone() : '')),
                'payer_street' => isset($street[0]) ? $street[0] : '',
                'payer_number' => isset($street[1]) ? $street[1] : '1',
                'payer_complement' => isset($street[2]) ? $street[2] : '',
                'payer_district' => isset($street[3]) ? $street[3] : '',
                'payer_city' => $billingAddress ? $billingAddress->getCity() : '',
                'payer_state' => $billingAddress ? $billingAddress->getRegionCode() : '',
                'payer_zip_code' => preg_replace('/[^0-9]/', '', (string)($billingAddress ? $billingAddress->getPostcode() : '')),
                'notification_url' => $this->_storeManager->getStore($storeId)->getBaseUrl() . "paghiper/notification/updatestatus",
                'shipping_methods' => $order->getShippingDescription() ?: 'Flat Rate - Fixed',
                'shipping_price_cents' => (int)round(($order->getShippingAmount() ?? 0) * 100), // Nulo-seguro para Downloadable
                'fixed_description' => true,
                'days_due_date' => 0,
                'minutes_due_date' => (int)$expirationInMinutes,
                'discount_cents' => $discount,
                'items' => []
            ];

            // Abordagem Universal e segura integrada: Funciona em Simples, Downloadable, Configurable e Bundles
            foreach ($order->getAllItems() as $item) {
                // 1. Ignora filhos de produtos Configuráveis
                if ($item->getParentItem() && $item->getParentItem()->getProductType() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                    continue;
                }

                // 2. Filtros de estabilidade para Bundles (Fixos e Dinâmicos)
                if ($item->getProductType() === \Magento\Bundle\Model\Product\Type::TYPE_CODE) {
                    if ((float)$item->getPrice() <= 0) {
                        continue;
                    }
                }
                if ($item->getParentItem() && $item->getParentItem()->getProductType() === \Magento\Bundle\Model\Product\Type::TYPE_CODE) {
                    if ((float)$item->getParentItem()->getPrice() > 0) {
                        continue;
                    }
                }

                $postFields['items'][] = [
                    'description' => $item->getName(),
                    'quantity'    => (int)round($item->getQtyOrdered()),
                    'item_id'     => (string)$item->getProductId(),
                    'price_cents' => (int)round($item->getPrice() * 100)
                ];
            }

            if ($order->getTaxAmount() > 0) {
              $postFields['items'][] = [
                'description' => "Taxas/Impostos",
                'quantity'    => 1,
                'item_id'     => 'taxes',
                'price_cents' => $order->getTaxAmount() * 100
              ];
            }

            $response = $this->doPayment($postFields);

            if (empty($response) || !isset($response['pix_create_request'])) {
                $msg = $response['status_request']['margin_message'] ?? 'Erro de resposta da API Pix PagHiper (Sem dados retornados).';
                throw new ExceptionSituation($msg);
            }

            if (isset($response['pix_create_request']['result']) && $response['pix_create_request']['result'] === 'reject') {
                throw new ExceptionSituation($response['pix_create_request']['response_message']);
            }

            $pixcode = $response['pix_create_request']['pix_code']['qrcode_image_url'];
            $emv = $response['pix_create_request']['pix_code']['emv'];
            $transactionToken = $response['pix_create_request']['pix_code']['transaction_id'] ?? $response['pix_create_request']['transaction_id'];

            $order->setPaghiperTransaction($transactionToken);
            $order->setPaghiperPix($pixcode);
            $order->setPaghiperChavepix($emv);

            $payment->setTransactionId($transactionToken);
            $payment->setLastTransId($transactionToken);

            $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            
            $configStatus = $this->_scopeConfig->getValue('payment/paghiper_pix/order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $stateObject->setStatus($configStatus ?: 'pending');
            $stateObject->setIsNotified(false);

        } catch (\Throwable $e) {
            // Grava o erro exato com Backtrace nos logs do servidor para depuração ágil
            $this->_loggerInterface->error('PagHiper Pix Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }

        return $this;
    }

    public function doPayment($data)
    {
        $url = 'https://pix.paghiper.com/invoice/create/';
        $headers = ["Content-Type" => "application/json", "Accept" => "application/json"];
        $curlBody = json_encode($data);
        $this->curl->setHeaders($headers);
        $this->curl->post($url, $curlBody);
        $response = $this->curl->getBody();
        return json_decode((string)$response, true) ?? [];
    }
}