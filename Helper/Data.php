<?php

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
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param Customer $customer
     * @param Context $context
     * @param ProductMetadataInterface $productMetadata
     * @param ModuleListInterface $moduleList
     * @param Curl $curl
     * @param SerializerInterface $serializer
     * @param RemoteAddress $remoteAddress
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        Customer $customer,
        Context $context,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList,
        Curl $curl,
        SerializerInterface $serializer,
        RemoteAddress $remoteAddress,
        EncryptorInterface $encryptor
    ) {
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->customerRepo = $customer;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
        $this->curl = $curl;
        $this->serializer = $serializer;
        $this->remoteAddress = $remoteAddress;
        $this->encryptor = $encryptor;
        parent::__construct($context);
    }

    /**
     * Get config
     *
     * @param mixed $path
     * @return mixed
     */
    public function getConfig($path)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue($path, $storeScope);
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return "https://api.paghiper.com/transaction/create/";
    }

    /**
     * Get acess token
     *
     * @return mixed
     */
    public function getAcessToken()
    {
        return $this->getConfig('payment/paghiper/api_key');
    }

    /**
     * Get token
     *
     * @return mixed
     */
    public function getToken()
    {
        return $this->getConfig('payment/paghiper/token');
    }

    /**
     * Get days
     *
     * @return mixed
     */
    public function getDays()
    {
        return $this->getConfig('payment/paghiper/validade');
    }

    /**
     * Get invoice after confirmation
     *
     * @return mixed
     */
    public function getInvoiceAfterConfirmation()
    {
        return $this->getConfig('payment/paghiper/invoice_auto');
    }

    /**
     * Get module enabled
     *
     * @return mixed
     */
    private function getModuleEnabled()
    {
        return $this->getConfig('payment/paghiper/enabled');
    }

    /**
     * Get status billet
     *
     * @return bool
     */
    public function getStatusBillet()
    {
        if ($this->getModuleEnabled() && $this->getConfig('payment/paghiper_boleto/ativar_boleto')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get status pix
     *
     * @return bool
     */
    public function getStatusPix()
    {
        if ($this->getModuleEnabled() && $this->getConfig('payment/paghiper_pix/ativar_pix')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get info juros
     *
     * @return array
     */
    public function getInfoJuros()
    {
        $data['juros'] = $this->getConfig('payment/paghiper_boleto/juros_atraso');
        $data['multa'] = $this->getConfig('payment/paghiper_boleto/percentual_multa');
        $data['dias'] = $this->getConfig('payment/paghiper_boleto/numero_apos_vencimento');
        return $data;
    }

    /**
     * Get info discount
     *
     * @return array
     */
    public function getInfoDiscount()
    {
        $data['dias'] = $this->getConfig('payment/paghiper_boleto/dias_pagamento_antecipado');
        $data['valor'] = $this->getConfig('payment/paghiper_boleto/valor_desconto_antecipado');
        return $data;
    }

    /**
     * Check states
     *
     * @param mixed $stateName
     * @return false|int|string
     */
    public function checkStates($stateName)
    {
        $brazilianStates = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins'
        ];
        $result = array_search($stateName, $brazilianStates);
        return $result;
    }
}
