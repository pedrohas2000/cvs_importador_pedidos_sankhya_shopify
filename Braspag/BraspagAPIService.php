<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';
use GuzzleHttp\Exception\ClientException;
class BraspagAPIService
{
    const HOST = "https://apiquery.braspag.com.br";
    const version = "v2";
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
        'MerchantId' => '5EB58E27-9FF7-8232-23A0-7E0433AD557C',
        'MerchantKey'     => 'bGNJTi8XbWnDWvxpjAWsLgifriV1Ww46iH0NuLKr',
    );

}

public function GetPaymentsByID($paymentID){


    try
    {
        $response = $this->http_client->get(self::version . "/sales/$paymentID",  [ 'headers' => self::getHeader()]);
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


public function GetPaymentsByMerchantOrderID($merchantOrderId){



        try{

            $response = $this->http_client->get(self::version . "/sales/?merchantOrderId=$merchantOrderId",  [  'headers' => self::getHeader()]);
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