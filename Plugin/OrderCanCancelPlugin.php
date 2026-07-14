<?php
namespace Paghiper\Magento2\Plugin;

use Magento\Sales\Model\Order;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

class OrderCanCancelPlugin
{
  protected $scopeConfig;
  protected $appState;

  public function __construct(
    ScopeConfigInterface $scopeConfig,
    State $appState
  ) {
    $this->scopeConfig = $scopeConfig;
    $this->appState = $appState;
  }

  /**
   * Intercepta o método canCancel do Magento
   */
  public function afterCanCancel(Order $subject, $result)
  {
    // Se o Magento já decidiu que o pedido NÃO pode ser cancelado, mantém a decisão
    if (!$result) {
      return $result;
    }

    try {
      // Se a execução atual estiver vindo de dentro do CRON (crontab)
      if ($this->appState->getAreaCode() === Area::AREA_CRONTAB) {

        // Verifica se a trava de segurança está ativa no painel
        $isBypassActive = $this->scopeConfig->isSetFlag(
          'payment/paghiper_general/bypass_cron_expiration',
          \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
          $subject->getStoreId()
        );

        if ($isBypassActive) {
          $payment = $subject->getPayment();
          if ($payment) {
            $method = $payment->getMethod();

            // Se o pedido for da PagHiper, nós respondemos que ele NÃO pode ser cancelado pelo cron
            if ($method === 'paghiper_boleto' || $method === 'paghiper_pix') {
              return false;
            }
          }
        }
      }
    } catch (\Exception $e) {
      // Try/catch preventivo caso o areaCode não esteja inicializado em algum contexto
    }

    return $result;
  }
}