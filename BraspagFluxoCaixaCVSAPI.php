<?php


header("Access-Control-Allow-Origin: *");

require 'Braspag/BraspagAgilizaCashflowAPIService.php';

$config_payments = include "payments_config.php";
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

    $variables = ["Acquirer", "dateFile", "pageSize", "pageIndex"];
    foreach ($variables as $var) {
        if(!isset($_GET[$var])){
            http_response_code(400);
            print_r(json_encode(["success" => false, "message" => "Parâmetro obrigatório não informado: " . $var ]));
            return;
        }
    }

    try{
        $braspagHelper = new BraspagAgilizaCashflowAPIService($config_payments['braspag_agiliza']['merchant_id']);
        $cashFlow = $braspagHelper->GetCashFlow($_GET["dateFile"], $_GET["Acquirer"], $_GET["pageIndex"], $_GET["pageSize"]);
        if($cashFlow)
        {
            http_response_code(200);
            print(json_encode(["success" => true, "response" => $cashFlow]));
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
