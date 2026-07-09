<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';

class PaypalAPIService
{
    private static $config = null;
    private $http_client = null;

    public function __construct()
    {
        $this->http_client = new \GuzzleHttp\Client(array('cookies' => true, 'base_uri' => self::getHost()));
    }

    private static function getConfig()
    {
        if (self::$config === null) {
            $paymentsConfig = include dirname(__DIR__, 1) . '/payments_config.php';
            self::$config = $paymentsConfig['paypal'];
        }

        return self::$config;
    }

    public function GetTransactions($start_date, $end_date, $page_index =1, $page_size=100)
    {
        $response = $this->http_client->getAsync(self::getHost() . "/reporting/transactions?fields=all&start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&pageSize=$page_size&page=$page_index",  ['headers' => self::getHeader()]);
        $response = $response->wait()->getBody()->getContents();
        $response = json_decode($response);
        return $response;
    }

    public function GetPaymentsByTransactionID($transaction_id, $start_date, $end_date)
    {
        $response = $this->http_client->getAsync(self::getHost() . "/reporting/transactions?fields=all&start_date=$start_date&end_date=$end_date&transaction_id=$transaction_id",  ['headers' => self::getHeader()]);
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
            ]];

        $response = $client->post(self::getHostAuth(), $options);
        $response = $response->getBody()->getContents();
        $response = json_decode($response);

        return $response;
    }
}
