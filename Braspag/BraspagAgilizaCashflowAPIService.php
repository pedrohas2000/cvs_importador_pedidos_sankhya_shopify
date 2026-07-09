<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';

class BraspagAgilizaCashflowAPIService
{
    private static $config = null;
    private $MERCHANT_ID;
    private $http_client = null;

    public function __construct($merchant_id = null)
    {
        $config = self::getConfig();
        $this->MERCHANT_ID = $merchant_id ?? $config['merchant_id'];
        $this->http_client = new \GuzzleHttp\Client(array('cookies' => true, 'base_uri' => self::getHost()));
    }

    private static function getConfig()
    {
        if (self::$config === null) {
            $paymentsConfig = include dirname(__DIR__, 1) . '/payments_config.php';
            self::$config = $paymentsConfig['braspag_agiliza'];
        }

        return self::$config;
    }

    public function GetCashFlow($date_file, $acquirer=1, $page_index =1, $page_size=100)
    {
        $response = $this->http_client->getAsync(self::getHost() . "/merchant/cashflow/$this->MERCHANT_ID?Acquirer=$acquirer&dateFile=$date_file&pageSize=$page_size&pageIndex=$page_index",  ['headers' => self::getHeader()]);
        $response = $response->wait()->getBody()->getContents();

        $response = json_decode($response);
        return $response;
    }

    public static function getHost()
    {
        return self::getConfig()['host'];
    }

    public static function getHostAuth()
    {
        return self::getConfig()['host_auth'];
    }

    public static function getHeader()
    {
        $token = self::GetToken()->access_token;
        return ['Content-Type' => 'application/json', 'Authorization' => "Bearer $token", "Connection" => "keep-alive" ];
    }

    public static function GetToken()
    {
        $config = self::getConfig();
        $client = new \GuzzleHttp\Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => $config['basic_authorization'],
        ];

        $options = [
            'headers' => $headers,
            'form_params' => [
                'grant_type' => 'client_credentials',
                'scope' => 'AgilizaSales AgilizaCashFlow AgilizaReport'
            ]];

        $response = $client->post(self::getHostAuth(), $options);
        $response = $response->getBody()->getContents();
        $response = json_decode($response);

        return $response;
    }
}
