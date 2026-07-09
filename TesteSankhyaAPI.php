<?php

require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './IntegracaoSankhyaShopifyController.php';

use \PHPSankhyaAPI\SankhyaAPI;



$sankhya = new SankhyaAPI("http://10.0.0.11:8180");

$sankhya->Login("p.HENRIQUE", "456789");
$str = file_get_contents("order.json");
$order = json_decode($str, true);
//print("<pre>".print_r($order, true)."</pre>"); die();


$params_contato = array(
    "TIPPESSOA" => ["$"=> $tipo_pessoa],
    "NOMECONTATO" => ["$"=> mb_strimwidth($nome_razao_social,0,40, "")],
    "NUMEND" => ["$"=> mb_strimwidth($numero_endereco_faturamento,0,6, "")],
    "COMPLEMENTO" => ["$"=>  mb_strimwidth($complemento_endereco_faturamento,0,30, "")],
    "TELEFONE" => ["$"=> $telefone],
    "FAX" => ["$"=> ""],
    "EMAIL" => ["$"=> $email],
    "CEP" => ["$"=> $cep],
    "ATIVO" => ["$"=> "S"],
    "CODEND" => ["$"=> "S"],
    "CODBAI" => ["$"=> "C"],
    "CODCID" => ["$"=> "C"],
    "CODPARC" => ["$"=> "C"],
);

if($tipo_pessoa == "J")
{
    $params_contato["CNPJ"] = ["$"=> $cpf_cnpj];
}else
{
    $params_contato["CPF"] = ["$"=> $cpf_cnpj];
}

$contato = $sankhya->contatos->post($params_contato, "NOMECONTATO, CODCONTATO");

//$client_id = IntegracaoSankhyaShopifyController::save_client($order, $sankhya);

//$pedido_id = IntegracaoSankhyaShopifyController::insert_pedido($order, $client_id, $sankhya);


$sankhya->Logout();


?>