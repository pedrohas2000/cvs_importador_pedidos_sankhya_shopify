<?php


header("Access-Control-Allow-Origin: *");

require 'Paypal/PaypalAPIService.php';

$integradores = include "api_keys_integradores.php";

// get request method
$method = $_SERVER['REQUEST_METHOD'];
$headers = getallheaders();


if(!isset($headers['API_KEY'])){
    http_response_code(400);
    print_r(json_encode(["success" => false, "message" => "Nenhuma chave de API foi informada" ]));
    return;
}

if(isset($headers['API_KEY'])){

    $found = false;
    foreach ($integradores as $int)
    {
        if($int["apiKey"] == $headers['API_KEY']) {
            $found = true;
            break;
        }
    }
    if(!$found){
        http_response_code(400);
        print_r(json_encode(["success" => false, "message" => "Chave de API inválida" ]));
    }

}


if ($method == 'GET') {

    $variables = ["start_date", "end_date", "page_size", "page"];
    foreach ($variables as $var) {
        if(!isset($_GET[$var])){
            http_response_code(400);
            print_r(json_encode(["success" => false, "message" => "Parâmetro obrigatório não informado: " . $var ]));
            return;
        }
    }

    try{
        $paypalService = new PaypalAPIService();
        $transactions = $paypalService->GetTransactions($_GET["start_date"], $_GET["end_date"], $_GET["page"], $_GET["page"]);

        if($transactions)
        {
            http_response_code(200);
            print(json_encode(["success" => true, "response" => $transactions]));
            return;
        }
    }catch (GuzzleHttp\Exception\ClientException $e){

        $response = $e->getResponse();
        http_response_code($response->getStatusCode());
        $responseBodyAsString = $response->getBody()->getContents();
        print($responseBodyAsString);

    }





}

if ($method == 'POST') {

}
if ($method == 'PUT') {

}
if ($method == 'DELETE') {

}

