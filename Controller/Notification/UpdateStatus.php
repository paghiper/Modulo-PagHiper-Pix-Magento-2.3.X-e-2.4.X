<?php

namespace Paghiper\Magento2\Controller\Notification;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Webapi\Exception as ExceptionWebapi;
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
use Paghiper\Magento2\Model\CreateInvoice;

class UpdateStatus extends Action implements CsrfAwareActionInterface
{
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

    /**
     * @var CurlFactory
     */
    private $_curlFactory;
    
    /**
     * @var CreateInvoice
     */
    protected $createInvoice;

    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        Data $helper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        CurlFactory $_curlFactory,
        CreateInvoice $createInvoice
    ) {
        $this->orderRepository = $orderRepository;
        $this->helperData = $helper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_logger = $logger;
        $this->_curlFactory = $_curlFactory;
        $this->createInvoice = $createInvoice;
        return parent::__construct($context);
    }

  /**
   * Execute action based on request and return result
   *
   * @return bool|ResponseInterface|\Magento\Framework\Controller\ResultInterface
   * @throws ExceptionWebapi
   */
    public function execute()
    {
        try {
            $params = $this->getRequest()->getParams();
            if ($params['apiKey'] &&
              $params['transaction_id'] &&
              $params['notification_id'] &&
              $params['notification_date']
            ) {
                $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(
                    'paghiper_transaction',
                    $params['transaction_id'],
                    'eq'
                )->create();

                $collection = $this->orderRepository->getList($searchCriteria);

                /** @var \Magento\Sales\Model\Order $order */
                foreach ($collection as $order) {
                    $paymentMethod = $order->getPayment()->getMethod();

                    $request = [
                      'token' => $this->helperData->getToken(),
                      'apiKey' => $this->helperData->getAcessToken(),
                      'transaction_id' => $params['transaction_id'],
                      'notification_id' => $params['notification_id']
                    ];
                    
                    $url = $paymentMethod == static::PAGHIPER_BOLETO ? static::URL_BOLETO : static::URL_PIX;
                    $curlHeaders = [
                      "Content-Type: application/json",
                      "Accept: application/json"
                    ];
                    $curlBody = json_encode($request);
  
                    /** @var \Magento\Framework\HTTP\Adapter\Curl $curlObject */
                    $curlObject = $this->_curlFactory->create();
                    $curlObject->setConfig([
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 0,
                      CURLOPT_FOLLOWLOCATION => true,
                    ]);
  
                      $curlObject->connect($url);
                      $curlObject->write(\Zend_Http_Client::POST, $url, '1.1', $curlHeaders, $curlBody);
                      $response = $curlObject->read();
                      $curlObject->close();
  
                      $response = preg_split('/^\r?$/m', $response, 2);
                      $response = trim($response[1]);

                      $base = json_decode($response)->status_request;
                      
                    if ($base->result === static::STATUS_SUCCESS) {
                        if (!$order->getId()) {
                            throw new ExceptionWebapi(__("Order Id not found"), 0, ExceptionWebapi::HTTP_NOT_FOUND);
                        }
                        
                        $event = $base->status;
                        
                        if ($event == static::STATUS_PAID || $event == static::STATUS_RESERVED) {
                            $totalPaid = $base->value_cents_paid / 100;
                            $paghiperTax = $totalPaid - $order->getGrandTotal();
                            $order->setBasePaghiperFeeAmount($paghiperTax);
                            $order->setPaghiperFeeAmount($paghiperTax);
                            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                            $order->setDiscountAmount($paghiperTax);
                            $order->setGrandTotal($totalPaid);
                            $order->setTotalPaid($totalPaid);
                          
                            $this->createInvoice->execute($order);
                          
                            $this->orderRepository->save($order);
                        } elseif ($event == static::STATUS_REFUNDED || $event == static::STATUS_CANCELED) {
                            $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
                            $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                            $this->orderRepository->save($order);
                        }
                    }
                }
            }
        } catch (ExceptionWebapi $e) {
            $this->_logger->notice($e->getMessage());
            throw new ExceptionWebapi(__("Erro interno!"), 0, ExceptionWebapi::HTTP_INTERNAL_ERROR);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
