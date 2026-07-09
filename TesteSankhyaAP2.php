<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './IntegracaoSankhyaShopify/IntegracaoSankhyaShopifyController.php';
require './utils.php';


use \PHPSankhyaAPI\SankhyaAPI;

    $config_shopify = include "shopify_config.php";
    $config_sankhya  = include "sankhya_config.php";

    $sankhya = new SankhyaAPI($config_sankhya["host"]);

    $shopify = new PHPShopify\ShopifySDK($config_shopify);

    $params = array(
        'financial_status' =>  'paid',
        'status' => 'open',
       // 'fulfillment_status' => 'unfulfilled',
        'limit' => 250
    );

    if(isset($_GET["order_number"])){
        $params["name"] = $_GET["order_number"];

    }

    $orders = $shopify->Order->get($params);
	
	sort($orders);
$order = $orders[0];

$sankhya->Login($config_sankhya["user"], $config_sankhya["password"]);
$ret = $sankhya->pedidos->faturar_pedido(2756069, 1100, 3,$sankhya->login["jsessionid"]);
print("<pre>".print_r($ret, true)."</pre>");
$sankhya->Logout();
die();

print_r(IntegracaoShopifyHelpers::create_endereco_from_shopify_address($order["shipping_address"]));

$cep = preg_replace('/[^0-9]/', '', $order['shipping_address']['zip']);
$endereco_viacep = buscarEnderecoPorCep($cep);

print_r(IntegracaoShopifyHelpers::create_endereco_from_viacep($endereco_viacep));

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
print("<pre>".print_r($order, true)."</pre>"); 

?>