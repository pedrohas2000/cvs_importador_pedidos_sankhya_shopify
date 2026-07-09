<?php

require 'vendor/autoload.php';

class BraspagAgilizaCashflowAPIService
{
    const HOST = "https://agiliza.braspag.com.br/api";
    const HOST_AUTH = "https://auth.braspag.com.br/oauth2/token";
    private $MERCHANT_ID;

    private $http_client = null;

    public function __construct($merchant_id)
    {
        $this->MERCHANT_ID = $merchant_id; //4127
        $this->http_client = new \GuzzleHttp\Client(array('cookies' => true, 'base_uri' => self::getHost()));
    }

    public function GetCashFlow($date_file, $acquirer=1, $page_index =1, $page_size=100){

        $response = $this->http_client->getAsync(self::getHost() . "/merchant/cashflow/$this->MERCHANT_ID?Acquirer=$acquirer&dateFile=$date_file&pageSize=$page_size&pageIndex=$page_index",  ['headers' => self::getHeader()]);
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
            'Authorization' => 'Basic ZWQ2NzU3OWUtZjExMS00MTg2LWEzODQtNWRmN2EyNmMyYmMzOmNjRGRWVVgzUkUvZWV1WFJTKzhCd3R4Y3IzcktCcjRhMS8vYmsyUnAwd289'
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