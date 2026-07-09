<?php

require dirname(__DIR__, 1) . '/vendor/autoload.php';
use GuzzleHttp\Exception\ClientException;

class PagBrasilAPIService
{
    const HOST = "https://connect.pagbrasil.com";
    const TOKEN = "44c2934337952eaee53327e5d9d70d77";
    const SECRET = "Secret CVS";
    private $http_client = null;
    public function __construct()
    {
        $this->http_client = new \GuzzleHttp\Client(array('cookies' => true,  'base_uri' => self::getHost()));
    }

public static function getHost(){
    return self::HOST;
}
public static function getHeader() {

    return array(
        'Content-Type' => 'application/x-www-form-urlencoded',
    );

}

public function GetPaymentsByID($order_id){


    try
    {

        $response = $this->http_client->post("/api/order/get",  [ 'headers' => self::getHeader(),
            'form_params' => ['pbtoken' => self::TOKEN, 'order' => $order_id, 'secret' => self::SECRET]]);



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




