<?php
/**
 * @author Mathias Matas Hennig <mathias@tezus.com.br>
 * @updated_for_magento_2.4.9
 */

namespace Paghiper\Magento2\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class Data Helper
 * Updated for Magento 2.4.9 & PHP 8.4+
 */
class Data extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var Session
     */
    protected Session $checkoutSession;

    /**
     * @var Customer
     */
    protected Customer $customerRepo;

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;

    /**
     * @var ModuleListInterface
     */
    protected ModuleListInterface $moduleList;

    /**
     * @var Curl
     */
    protected Curl $curl;

    /**
     * @var SerializerInterface
     */
    protected SerializerInterface $serializer;

    /**
     * @var RemoteAddress
     */
    protected RemoteAddress $remoteAddress;

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
    * @var \Magento\Framework\App\Config\ScopeConfigInterface
    */
    protected $_scopeConfig; // O tipo nativo do PHP foi removido para bater com a classe Pai

    /**
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param Customer $customerRepo
     * @param ProductMetadataInterface $productMetadata
     * @param ModuleListInterface $moduleList
     * @param Curl $curl
     * @param SerializerInterface $serializer
     * @param RemoteAddress $remoteAddress
     * @param EncryptorInterface $encryptor
     * @param Context $context
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        Customer $customerRepo,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList,
        Curl $curl,
        SerializerInterface $serializer,
        RemoteAddress $remoteAddress,
        EncryptorInterface $encryptor,
        Context $context
    ) {
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->customerRepo = $customerRepo;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
        $this->curl = $curl;
        $this->serializer = $serializer;
        $this->remoteAddress = $remoteAddress;
        $this->encryptor = $encryptor;
        $this->scopeConfig = $context->getScopeConfig();
        
        parent::__construct($context);
    }

    /**
     * Get config value safely
     *
     * @param string $path
     * @return mixed
     */
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get Access Token
     *
     * @return string|null
     */
    public function getAcessToken()
    {
        return $this->getConfig('payment/paghiper_general/token');
    }

    /**
     * Get API Key
     *
     * @return string|null
     */
    public function getApiKey()
    {
        return $this->getConfig('payment/paghiper_general/api_key');
    }

    /**
     * Get Status Billet
     *
     * @return bool
     */
    public function getStatusBillet()
    {
        return (bool)$this->getConfig('payment/paghiper_boleto/active');
    }

    /**
     * Get Status Pix
     *
     * @return bool
     */
    public function getStatusPix()
    {
        return (bool)$this->getConfig('payment/paghiper_pix/active');
    }

    /**
     * Get Pix Expiration In Minutes
     *
     * @return int
     */
    public function getPixExpirationInMinutes()
    {
        return (int)$this->getConfig('payment/paghiper_pix/pix_expiration');
    }

    /**
     * Get Invoice After Confirmation Setting
     *
     * @return int
     */
    public function getInvoiceAfterConfirmation()
    {
        return (int)$this->getConfig('payment/paghiper_general/invoice_after_confirmation');
    }

    /**
     * Get Days for Billet Expiration
     *
     * @return int
     */
    public function getDays()
    {
        $days = (int)$this->getConfig('payment/paghiper_general/dias_vencimento');
        return $days > 0 ? $days : 3;
    }

    /**
     * Get Info Juros
     *
     * @return array
     */
    public function getInfoJuros()
    {
        $data = [];
        $data['juros'] = (int)$this->getConfig('payment/paghiper_boleto/juros_atraso');
        $data['multa'] = (int)$this->getConfig('payment/paghiper_boleto/percentual_multa');
        $data['dias'] = (int)$this->getConfig('payment/paghiper_boleto/numero_apos_vencimento');
        return $data;
    }

    /**
     * Get info discount
     *
     * @return array
     */
    public function getInfoDiscount()
    {
        $data = [];
        $data['dias'] = (int)$this->getConfig('payment/paghiper_boleto/dias_pagamento_antecipado');
        $data['valor'] = $this->getConfig('payment/paghiper_boleto/valor_desconto_antecipado');
        return $data;
    }

    /**
     * Check states and return abbreviation
     *
     * @param string|mixed $stateName
     * @return false|int|string
     */
    public function checkStates($stateName)
    {
        $stateNameClean = trim((string)$stateName);
        
        $brazilianStates = [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
        ];

        if (array_key_exists(strtoupper($stateNameClean), $brazilianStates)) {
            return strtoupper($stateNameClean);
        }

        $search = array_search(mb_convert_case($stateNameClean, MB_CASE_TITLE, "UTF-8"), $brazilianStates);
        if ($search !== false) {
            return $search;
        }

        return 'SP'; 
    }

    /**
     * Safely json decode using Magento Serializer
     *
     * @param string|mixed $json
     * @return array
     */
    public function jsonDecode($json)
    {
        if (empty($json) || !is_string($json)) {
            return [];
        }
        try {
            return (array)$this->serializer->unserialize($json);
        } catch (\Exception $e) {
            return [];
        }
    }
}