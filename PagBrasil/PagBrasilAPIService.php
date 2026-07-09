<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';
use GuzzleHttp\Exception\ClientException;

class PagBrasilAPIService
{
    private static $config = null;
    private $http_client = null;

    public function __construct()
    {
        $this->http_client = new \GuzzleHttp\Client(array('cookies' => true,  'base_uri' => self::getHost()));
    }

    private static function getConfig()
    {
        if (self::$config === null) {
            $paymentsConfig = include dirname(__DIR__, 1) . '/payments_config.php';
            self::$config = $paymentsConfig['pagbrasil'];
        }

        return self::$config;
    }

    public static function getHost()
    {
        return self::getConfig()['host'];
    }

    public static function getHeader()
    {
        return array(
            'Content-Type' => 'application/x-www-form-urlencoded',
        );
    }

    public function GetPaymentsByID($order_id)
    {
        $config = self::getConfig();

        try
        {
            $response = $this->http_client->post("/api/order/get",  [ 'headers' => self::getHeader(),
                'form_params' => ['pbtoken' => $config['token'], 'order' => $order_id, 'secret' => $config['secret']]]);

            if($response->getStatusCode() == 200)
            {
                $response = $response->getBody()->getContents();
                $xml = simplexml_load_string($response, null, LIBXML_NOERROR |  LIBXML_ERR_NONE);
                return $xml;
            }
        } catch (ClientException $ex) {
            $response = $ex->getResponse();
            $statusCode = $response->getStatusCode();
            if(in_array($statusCode, [404, 400, 412]))
                return null;
            else
                throw $ex;
        }
    }

    const ORDER_STATUS = [
        "PC" => "Payment Completed",
        "PF" => "Payment Failed",
        "PR" => "Payment Rejected",
        "RR" => "Refund Requested",
        "RP" => "Refund Processed",
        "CB" => "Chargeback",
    ];

    const PAYMENT_METHODS = [
        "C" => "Cartão de Crédito",
        "D" => "Cartão de Débito",
        "B" => "Boleto Bancario",
        "F" => "Boleto Flash",
        "X" => "Pix",
    ];

    const CREDIT_CARD_BRANDS = [
        "M" => "Mastercard",
        "V" => "Visa",
        "D" => "Diners",
        "A" => "Amex",
        "H" => "Hipercard",
        "E" => "Elo",
    ];
}
