<?php

namespace Paghiper\Magento2\Controller\Notification;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Paghiper\Magento2\Helper\Data;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class UpdateStatus extends Action implements CsrfAwareActionInterface {

  const STATUS_SUCCESS = 'success';
  const STATUS_PENDING = 'pending';
  const STATUS_PAID = 'paid';
  const STATUS_RESERVED = 'reserved';
  const STATUS_REFUNDED = 'refunded';
  const STATUS_CANCELED = 'canceled';
  const URL_BOLETO = "https://api.paghiper.com/transaction/notification/";
  const URL_PIX = "https://pix.paghiper.com/invoice/notification/";
  const PAGHIPER_PIX = 'paghiper_pix';
  const PAGHIPER_BOLETO = 'paghiper_boleto';

  /**
   * @var Data
   */
  protected $helperData;

  /**
   * @var OrderRepositoryInterface
   */
  protected $orderRepository;

  /** 
   * @var LoggerInterface 
   */
  private $_logger;

  public function __construct(
    Context $context,
    OrderRepositoryInterface $orderRepository,
    Data $helper,
    SearchCriteriaBuilder $searchCriteriaBuilder,
    LoggerInterface $logger
  ) {
    $this->orderRepository = $orderRepository;
    $this->helperData = $helper;
    $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    $this->_logger = $logger;
    return parent::__construct($context);
  }

  /**
   * Execute action based on request and return result
   *
   * @return bool|ResponseInterface|\Magento\Framework\Controller\ResultInterface
   * @throws \Exception
   */
  public function execute() {
    try {
      $params = $this->getRequest()->getParams();
      if (
        $params['apiKey'] &&  $params['transaction_id'] &&
        $params['notification_id'] && $params['notification_date']
      ) {

        $searchCriteria = $this->searchCriteriaBuilder
          ->addFilter(
            'paghiper_transaction',
            $params['transaction_id'],
            'eq'
          )->create();

        $collection = $this->orderRepository->getList($searchCriteria);

        foreach ($collection as $order) {
          $paymentMethod = $order->getPayment()->getMethod();

          $request = [
            'token' => $this->helperData->getToken(),
            'apiKey' => $this->helperData->getAcessToken(),
            'transaction_id' => $params['transaction_id'],
            'notification_id' => $params['notification_id']
          ];

          $curl = curl_init();

          curl_setopt_array($curl, array(
            CURLOPT_URL => ($paymentMethod == static::PAGHIPER_BOLETO ? static::URL_BOLETO : static::URL_PIX),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_HTTPHEADER => array(
              "Content-Type: application/json"
            ),
          ));

          $response = curl_exec($curl);

          $this->_logger->notice($response);
          curl_close($curl);

          $base = json_decode($response)->status_request;

          if ($base->result === static::STATUS_SUCCESS) {
            if (!$order->getId()) {
              throw new \Magento\Framework\Webapi\Exception(__("Order Id not found"), 0, \Magento\Framework\Webapi\Exception::HTTP_NOT_FOUND);
            }
            $event = $base->status;
            if ($event == static::STATUS_PAID or $event == static::STATUS_RESERVED) {
              $totalPaid = $base->value_cents_paid / 100;
              $paghiperTax = $totalPaid - $order->getGrandTotal();
              $order->setBasePaghiperFeeAmount($paghiperTax);
              $order->setPaghiperFeeAmount($paghiperTax);
              $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
              $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
              $order->setDiscountAmount($paghiperTax);
              $order->setGrandTotal($totalPaid);
              $order->setTotalPaid($totalPaid);
              $this->orderRepository->save($order);
            } elseif ($event == static::STATUS_REFUNDED or $event == static::STATUS_CANCELED) {
              $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
              $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
              $this->orderRepository->save($order);
            }
          }
        }
      }
    } catch (\Exception $e) {
      $this->_logger->notice($e->getMessage());
      throw new \Magento\Framework\Webapi\Exception(__("Erro interno!"), 0, \Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR);
    }
  }

  public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException {
    return null;
  }

  public function validateForCsrf(RequestInterface $request): ?bool {
    return true;
  }
}
