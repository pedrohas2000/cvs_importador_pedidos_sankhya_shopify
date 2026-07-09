<?php


header("Access-Control-Allow-Origin: *");

 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require '../../../vendor/autoload.php';
require '../../../PHPSankhyaAPI/SankhyaAPI.php';
require '../../../IntegracaoSankhyaShopify/IntegracaoSankhyaShopifyController.php';
require '../../../utils.php';
use \PHPSankhyaAPI\SankhyaAPI;

$config_sankhya  = include "../../../sankhya_config.php";
$sankhya = new SankhyaAPI($config_sankhya["host"]);

$integradores = include "../../../api_keys_integradores.php";

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

    try
	{
        $sankhya->Login($config_sankhya["user"], $config_sankhya["password"]);

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
        $query = "SELECT c.NUMNOTA, c.SERIENOTA, d.* FROM DTCDIST as d LEFT JOIN TGFCAB as c on c.NUNOTA = d.NUNOTAVEN WHERE NUMNOTA = {$numNota} and SERIENOTA = {$serieNota}";
        $notaResult = $sankhya->db_explorer->execute_query($query);
 
        if (count($notaResult) == 0) {
            http_response_code(404);
            print_r(json_encode(["success" => false, "message" => "Nota fiscal não encontrada para os parâmetros informados."]));
            return;
        }

        // Considera o primeiro registro encontrado
    $registro = $notaResult[0];
    // Monta a chave primária (ajuste conforme necessário)
    $pk = ["SEQDIST" => $registro['SEQDIST'], "NUNOTA" => $registro['NUNOTA']];
    $payload = ["AD_URLIMGCVS" => $input['AD_URLIMGCVS']];
    $distribuicao = $sankhya->destinatarios;
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

    }catch(Exception $e){
        http_response_code(500);
        print_r(json_encode(["success" => false, "message" => "Erro ao atualizar nota fiscal: " . $e->getMessage()]));
        return;
    }
    finally {
        $sankhya->Logout();
    }


}


if ($method == 'DELETE') {

}

