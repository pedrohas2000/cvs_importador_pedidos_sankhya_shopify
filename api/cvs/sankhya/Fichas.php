<?php


header("Access-Control-Allow-Origin: *");

 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './IntegracaoSankhyaShopify/IntegracaoSankhyaShopifyController.php';
require './utils.php';
use \PHPSankhyaAPI\SankhyaAPI;

$config_sankhya  = include "sankhya_config.php";
$sankhya = new SankhyaAPI($config_sankhya["host"]);

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


}

if ($method == 'POST') {



}

if ($method == 'PUT') {
    // Recebe o JSON do corpo da requisição
    $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['NUMNOTA']) || !isset($input['AD_URLIMGCVS']) || !isset($input['SERIENOTA'])) {
            http_response_code(400);
            print_r(json_encode(["success" => false, "message" => "Campos obrigatórios: NUMNOTA, SERIENOTA e AD_URLIMGCVS"]));
            return;
        }

        // Verifica se a nota existe usando db_explorer
        $numNota = $input['NUMNOTA'];
        $serieNota = $input['SERIENOTA'];
        $query = "SELECT c.NUMNOTA, c.SERIENOTA, d.* FROM DTCDIST as d left join TGFCAB as c on c.NUNOTA = d.NUNOTAVEN WHERE NUMNOTA = {$numNota} and SERIENOTA = {$serieNota}";
        $notaResult = $sankhya->db_explorer->execute_query($query);
        if (empty($notaResult['data'])) {
            http_response_code(404);
            print_r(json_encode(["success" => false, "message" => "Nota fiscal não encontrada para os parâmetros informados."]));
            return;
        }

    // Usa a instância já criada em $sankhya->destinatarios
    $distribuicao = $sankhya->destinatarios;

    // Busca o registro pelo campo AD_NUMNOTA
    $expression = "AD_NUMNOTA = '{$input['AD_NUMNOTA']}'";
    $result = $distribuicao->get($expression);
    if (empty($result['data'])) {
        http_response_code(404);
        print_r(json_encode(["success" => false, "message" => "Registro não encontrado para o AD_NUMNOTA informado."]));
        return;
    }

    // Considera o primeiro registro encontrado
    $registro = $result['data'][0];
    // Monta a chave primária (ajuste conforme necessário)
    $pk = ["AD_NUMNOTA" => $input['AD_NUMNOTA']];
    $payload = ["AD_URLIMGCVS" => $input['AD_URLIMGCVS']];

    $updateResult = $distribuicao->update($pk, $payload);
    if (!empty($updateResult['success'])) {
        http_response_code(200);
        print_r(json_encode(["success" => true, "message" => "Campo AD_URLIMGCVS atualizado com sucesso."]));
    } else {
        http_response_code(500);
        $msg = isset($updateResult['message']) ? $updateResult['message'] : 'Erro ao atualizar registro.';
        print_r(json_encode(["success" => false, "message" => $msg]));
    }
    return;
}


if ($method == 'DELETE') {

}

