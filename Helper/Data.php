<?php

namespace Paghiper\Magento2\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\Encryption\EncryptorInterface as encryptor;

class Data extends AbstractHelper {

  /**
   * @var \Magento\Framework\App\Config\ScopeConfigInterface
   */

  /**
   * returning config value
   **/
  public function __construct(
    \Magento\Store\Model\StoreManagerInterface $storeManager,
    \Magento\Checkout\Model\Session $checkoutSession,
    \Magento\Customer\Model\Customer $customer,
    \Magento\Framework\App\Helper\Context $context,
    \Magento\Framework\App\ProductMetadataInterface $productMetadata,
    \Magento\Framework\Module\ModuleListInterface $moduleList,
    \Magento\Framework\HTTP\Client\Curl $curl,
    \Magento\Framework\Serialize\SerializerInterface $serializer,
    \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
    encryptor $encryptor
  ) {
    $this->storeManager = $storeManager;
    $this->checkoutSession = $checkoutSession;
    $this->customerRepo = $customer;
    $this->productMetadata = $productMetadata;
    $this->moduleList = $moduleList;
    $this->_curl = $curl;
    $this->serializer = $serializer;
    $this->remoteAddress = $remoteAddress;
    $this->_encryptor = $encryptor;
    parent::__construct($context);
  }

  public function getConfig($path) {
    $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
    return $this->scopeConfig->getValue($path, $storeScope);
  }
  /**
   * Return url  
   **/

  public function getUrl() {
    return "https://api.paghiper.com/transaction/create/";
  }

  public function getAcessToken() {
    return $this->getConfig('payment/paghiper/api_key');
  }

  public function getToken(){
    return $this->getConfig('payment/paghiper/token');
  }

  public function getDays() {
    return $this->getConfig('payment/paghiper/validade');
  }

  private function getModuleEnabled() {
    return $this->getConfig('payment/paghiper/enabled');
  }

  public function getStatusBillet() {
    if ($this->getModuleEnabled() && $this->getConfig('payment/paghiper_boleto/ativar_boleto')) {
      return true;
    } else {
      return false;
    }
  }

  public function getStatusPix() {
    if ($this->getModuleEnabled() && $this->getConfig('payment/paghiper_pix/ativar_pix')) {
      return true;
    } else {
      return false;
    }
  }

  public function getInfoJuros() {
    $data['juros'] = $this->getConfig('payment/paghiper_boleto/juros_atraso');
    $data['multa'] = $this->getConfig('payment/paghiper_boleto/percentual_multa');
    $data['dias'] = $this->getConfig('payment/paghiper_boleto/numero_apos_vencimento');
    return $data;
  }

  public function getInfoDiscount() {
    $data['dias'] = $this->getConfig('payment/paghiper_boleto/dias_pagamento_antecipado');
    $data['valor'] = $this->getConfig('payment/paghiper_boleto/valor_desconto_antecipado');
    return $data;
  }

  public function checkStates($stateName) {
    $brazilianStates = array(
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
    );
    $result = array_search($stateName, $brazilianStates);
    return $result;
  }
}
