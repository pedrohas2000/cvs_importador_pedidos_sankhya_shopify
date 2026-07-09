<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';
use GuzzleHttp\Exception\ClientException;




class MercadoPagoAPIService
{

    const HOST = "https://api.mercadopago.com";
    const version = "v1";
    private $http_client = null;

    public function __construct()
    {

        $this->http_client = new \GuzzleHttp\Client(array('cookies' => true,  'base_uri' => self::getHost()));
    }

    public static function getHost(){
        return self::HOST . "/" . self::version;
    }
    public static function getHeader() {

        return array(
            'Authorization' => 'Bearer APP_USR-5363776155753925-060217-532724ac449281362c0e870f400f7f14-436409525',
        );

    }


public function GetPaymentsByMoneyReleaseDate($date, $offset=0, $limit=200){

    try
    {
        $url = self::version . "/payments/search?limit=$limit&offset=$offset&sort=date_created&criteria=asc&range=money_release_date&begin_date=". $date. "T00:00:00.000-03:00&end_date=". $date. "T23:59:59.999-03:00";

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

        try {
            $response = $this->http_client->get(self::version . "/payments/search?sort=date_created&criteria=asc&external_reference=$external_reference",
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