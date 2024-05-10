<?php

namespace Paghiper\Magento2\Model\Method;

use Exception as ExceptionSituation;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Magento\Framework\HTTP\Client\Curl;

/**
 * Class Payment Billet
 *
 * @see       https://www.paghiper.com.br Official Website
 * @copyright https://www.paghiper.com.br
 * @license   https://www.gnu.org/licenses/gpl-3.0.pt-br.html GNU GPL, version 3
 */
class Boleto extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * @var string
     */
    protected const CODE = 'paghiper_boleto';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * PagHiper Helper
     *
     * @var \Paghiper\Magento2\Helper\Data;
     */
    protected $helperData;

    /**
     * @var LoggerInterface
     */
    protected $_loggerInterface;

    /**
     * @var CurlFactory
     */
    private $_curlFactory;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @param Curl $curl
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Paghiper\Magento2\Helper\Data $helper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param LoggerInterface $loggerInterface
     * @param \Magento\Framework\HTTP\Adapter\CurlFactory $_curlFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\HTTP\Client\Curl $curl,
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
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
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
        $this->curl = $curl;
        $this->helperData = $helper;
        $this->_storeManager = $storeManager;
        $this->_loggerInterface = $loggerInterface;
        $this->_curlFactory = $_curlFactory;
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->helperData->getStatusBillet()) {
            return false;
        }
        return true;
    }

    /**
     * Order
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param mixed $amount
     * @throws LocalizedException
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            //Pegando informações adicionais do pagamento (CPF)
            $info = $this->getInfoInstance();
            $paymentInfo = $info->getAdditionalInformation();

            //Helper
            $url = $this->helperData->getUrl();
            $days = $this->helperData->getDays();
            $infoJuros = $this->helperData->getInfoJuros();
            $infoDiscount = $this->helperData->getInfoDiscount();

            //pegando dados do pedido do clioente
            $order = $payment->getOrder();
            $billingaddress = $order->getBillingAddress();
            $stateBillingAddress = $this->helperData->checkStates($order->getBillingAddress()->getRegion());

            $dataUser['apiKey'] = $this->helperData->getAcessToken();
            $dataUser['partners_id'] = 'KAPK109D';
            $dataUser['order_id'] = $order->getIncrementId();
            $dataUser['payer_email'] = $billingaddress->getEmail();
            $dataUser['payer_name'] = $billingaddress->getFirstName() . ' ' . $billingaddress->getLastName();
            $dataUser['payer_cpf_cnpj'] = $paymentInfo['cpfCnpjCustomer'];
            $dataUser['payer_phone'] = $billingaddress->getTelephone();

            if (!isset($billingaddress->getStreet()[2])) {
                throw new ExceptionSituation(__("Por favor, preencha seu endereço corretamente."), 1);
            }

            if (isset($billingaddress->getStreet()[3])) {
                $dataUser['payer_street'] = $billingaddress->getStreet()[0];
                $dataUser['payer_number'] = $billingaddress->getStreet()[1];
                $dataUser['payer_complement'] = $billingaddress->getStreet()[2];
                $dataUser['payer_district'] = $billingaddress->getStreet()[3];
            } else {
                $dataUser['payer_street'] = $billingaddress->getStreet()[0];
                $dataUser['payer_number'] = $billingaddress->getStreet()[1];
                $dataUser['payer_district'] = $billingaddress->getStreet()[2];
            }
            $dataUser['payer_city'] = $billingaddress->getCity();
            $dataUser['payer_state'] = $stateBillingAddress;
            $dataUser['payer_zip_code'] = str_replace("-", "", $billingaddress->getPostcode());
            $dataUser['notification_url'] =
              $this->_storeManager->getStore()->getBaseUrl() . 'paghiper/notification/updatestatus';

            $discount = str_replace("-", "", $order->getDiscountAmount()) * 100;
            if ($discount > 0) {
                $dataUser['discount_cents'] = $discount;
            }

            $dataUser['shipping_methods'] = $order->getShippingDescription();
            $dataUser['shipping_price_cents'] = $order->getShippingAmount() * 100;
            $dataUser['fixed_description'] = true;
            $dataUser['type_bank_slip'] = 'boletoA4';
            $dataUser['days_due_date'] = $days;
            $dataUser['late_payment_fine'] = $infoJuros['multa'];
            $dataUser['per_day_interest'] = $infoJuros['juros'];
            $dataUser['open_after_day_due'] = $infoJuros['dias'];
            $dataUser['early_payment_discounts_days'] = $infoDiscount['dias'];
            $dataUser['early_payment_discounts_cents'] = $infoDiscount['valor'] * 100;

            $items = $order->getAllItems();
            $i = 0;
            /** @var \Magento\Catalog\Model\Product */
            foreach ($items as $key => $item) {
                if ($item->getProductType() != 'configurable' && $item->getProductType() != 'bundle') {
                    if ($item->getPrice() == 0) {
                        $parentItem = $item->getParentItem();
                        $price = $parentItem->getPrice();
                    } else {
                        $price = $item->getPrice();
                    }
                    $dataUser['items'][$i]['description'] = $item->getName();
                    $dataUser['items'][$i]['quantity'] = $item->getQtyOrdered();
                    $dataUser['items'][$i]['item_id'] = $item->getProductId();
                    $dataUser['items'][$i]['price_cents'] = $price * 100;
                    $i++;
                }
            }

            if ($order->getTaxAmount() > 0) {
                $dataUser['items'][$i]['description'] = "Imposto";
                $dataUser['items'][$i]['quantity'] = '1';
                $dataUser['items'][$i]['item_id'] = 'taxes';
                $dataUser['items'][$i]['price_cents'] = $order->getTaxAmount() * 100;
            }
      
            $this->_loggerInterface->notice(json_encode($dataUser));
            $response = (array)$this->doPayment($dataUser);
            $this->_loggerInterface->notice(json_encode($response));

            if ($response['create_request']->result == 'reject') {
                throw new ExceptionSituation($response['create_request']->response_message, 1);
            }
            $transactionToken = $response['create_request']->transaction_id;
            $boletoUrl = $response['create_request']->bank_slip->url_slip_pdf;
            $linha_digitavel = $response['create_request']->bank_slip->digitable_line;

            $order->setPaghiperTransaction($transactionToken);
            $order->setPaghiperBoleto($boletoUrl);
            $order->setPaghiperBoletoDigitavel($linha_digitavel);

            $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);

        } catch (ExceptionSituation $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
        return $this;
    }

    /**
     * Do payment
     *
     * @param mixed $data
     * @return mixed
     */
    public function doPayment($data)
    {
        $url = 'https://api.paghiper.com/transaction/create/';
        $headers = ["Content-Type" => "application/json", "Accept" => "application/json"];
        $curlBody = json_encode($data);
        $this->curl->setHeaders($headers);
        $this->curl->post($url, $curlBody);
        $response = $this->curl->getBody();
        return json_decode($response);
    }

    /**
     * Assign data
     *
     * @param \Magento\Framework\DataObject $data
     * @return $this|Boleto
     * @throws LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('cpfCnpjCustomer', $data['additional_data']['cpfCnpj'] ?? null);
        return $this;
    }
}
