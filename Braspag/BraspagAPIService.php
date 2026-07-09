<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';
use GuzzleHttp\Exception\ClientException;

class BraspagAPIService
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
            self::$config = $paymentsConfig['braspag'];
        }

        return self::$config;
    }

    public static function getHost()
    {
        $config = self::getConfig();
        return $config['host'] . "/" . $config['version'];
    }

    public static function getHeader()
    {
        $config = self::getConfig();

        return array(
            'MerchantId' => $config['merchant_id'],
            'MerchantKey' => $config['merchant_key'],
        );
    }

    public function GetPaymentsByID($paymentID)
    {
        $config = self::getConfig();

        try
        {
            $response = $this->http_client->get($config['version'] . "/sales/$paymentID",  [ 'headers' => self::getHeader()]);
            if($response->getStatusCode() == 200)
            {
                $response = $response->getBody()->getContents();
                $response = json_decode($response);
                return $response;
            }
        } catch (ClientException $ex) {
            $response = $ex->getResponse();
            $statusCode = $response->getStatusCode();
            if(in_array($statusCode, [404, 400]))
                return null;
            else
                throw $ex;
        }
    }

    public function GetPaymentsByMerchantOrderID($merchantOrderId)
    {
        $config = self::getConfig();

        try{
            $response = $this->http_client->get($config['version'] . "/sales/?merchantOrderId=$merchantOrderId",  [  'headers' => self::getHeader()]);
            if($response->getStatusCode() == 200)
            {
                $response = $response->getBody()->getContents();
                $response = json_decode($response);
                return $this->GetPaymentsByID($response->Payments[0]->PaymentId);
            }
            if($response->getStatusCode() == 404)
                return null;
        } catch (ClientException $ex) {
            $response = $ex->getResponse();
            $statusCode = $response->getStatusCode();
            if(in_array($statusCode, [404, 400]))
                return null;
            else
                throw $ex;
        }
    }
}
