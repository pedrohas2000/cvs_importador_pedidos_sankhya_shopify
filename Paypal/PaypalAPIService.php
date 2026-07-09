<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';

class PaypalAPIService
{
    const HOST = "https://api-m.paypal.com/v1";
    const HOST_AUTH = "https://api-m.paypal.com/v1/oauth2/token";

    private $http_client = null;

    public function __construct()
    {
        $this->http_client = new \GuzzleHttp\Client(array('cookies' => true, 'base_uri' => self::getHost()));
    }

    public function GetTransactions($start_date, $end_date, $page_index =1, $page_size=100){

        $response = $this->http_client->getAsync(self::getHost() . "/reporting/transactions?fields=all&start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&pageSize=$page_size&page=$page_index",  ['headers' => self::getHeader()]);
        $response = $response->wait()->getBody()->getContents();
        $response = json_decode($response);
        return $response;

    }

    public function GetPaymentsByTransactionID($transaction_id, $start_date, $end_date){

        $response = $this->http_client->getAsync(self::getHost() . "/reporting/transactions?fields=all&start_date=$start_date&end_date=$end_date&transaction_id=$transaction_id",  ['headers' => self::getHeader()]);
        $response = $response->wait()->getBody()->getContents();
        $response = json_decode($response);
        return $response;

    }



    public static function getHost(){
        return self::HOST;
    }

    public static function getHostAuth(){
        return self::HOST_AUTH;
    }
    public static function getHeader() {
        $token = self::GetToken()->access_token;

        $headers = ['Content-Type' => 'application/json', 'Authorization' => "Bearer $token", "Connection" => "keep-alive" ];
        return $headers;
    }

    public static function GetToken()
    {

        $client = new \GuzzleHttp\Client();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic QVNUM3ZraWRtSGlaQ0FNRm40U3FPUUEwM2dQVC0yM2tzVE5HSGlYYmlqczlXQTF2eUpieEJ0Nk1zeXBOWU9zRm13WXhPTjg1VlpaTUYzVXg6RURBNHdBYkVjZEVFazNMekZCVlJGX0dvRmxNcTVTdjBNTnBaUVpSSl96bXAtQnhjNERqOHdUZm5ubFhwN0RMYlRtUEc3dGdhNjNOY2FpTmo='
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