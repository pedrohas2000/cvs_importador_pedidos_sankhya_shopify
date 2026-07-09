<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';
use GuzzleHttp\Exception\ClientException;

class MercadoPagoAPIService
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
            self::$config = $paymentsConfig['mercadopago'];
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
            'Authorization' => 'Bearer ' . $config['access_token'],
        );
    }

    public function GetPaymentsByMoneyReleaseDate($date, $offset=0, $limit=200)
    {
        $config = self::getConfig();

        try
        {
            $url = $config['version'] . "/payments/search?limit=$limit&offset=$offset&sort=date_created&criteria=asc&range=money_release_date&begin_date=". $date. "T00:00:00.000-03:00&end_date=". $date. "T23:59:59.999-03:00";

            $response = $this->http_client->get($url, [ 'headers' => self::getHeader()]);
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

    public function GetPaymentsByExternalReference($external_reference)
    {
        $config = self::getConfig();

        try {
            $response = $this->http_client->get($config['version'] . "/payments/search?sort=date_created&criteria=asc&external_reference=$external_reference",
                ['headers' => self::getHeader()]);
            if ($response->getStatusCode() == 200) {
                $response = $response->getBody()->getContents();
                $response = json_decode($response);

                return $response;
            }
        } catch (ClientException $ex) {
            $response = $ex->getResponse();
            $statusCode = $response->getStatusCode();
            if (in_array($statusCode, [404, 400]))
                return null;
            else
                throw $ex;
        }
    }
}
