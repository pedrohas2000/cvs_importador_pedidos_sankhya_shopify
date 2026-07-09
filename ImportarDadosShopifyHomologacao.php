<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './IntegracaoSankhyaShopify/IntegracaoSankhyaShopifyController.php';
require './utils.php';
use \PHPSankhyaAPI\SankhyaAPI;


$config_sankhya = include "sankhya_config.php";
$config_shopify = include "shopify_config.php";

$sankhya = new SankhyaAPI($config_sankhya["host"]);

    $shopify = new PHPShopify\ShopifySDK($config_shopify);

    $params = array(
        'financial_status' =>  'paid',
        'status' => 'open',
        //'fulfillment_status' => 'unfulfilled',
        'limit' => 1,
    );


    if(isset($_GET["order_number"])){
        $params["name"] = $_GET["order_number"];

    }
    else
    {
        $params["updated_at_min"] = date(DATE_ATOM, strtotime('-7 days'));
    }


print_r($params);

$orders = $shopify->Order->get($params);
	
	sort($orders);

    $erros = 0;
    $importados = 0;
    $current_order = null;
    try
	{
        $sankhya->Login($config_sankhya["user"], $config_sankhya["password"]);


        foreach ($orders as $order) {

            $fake_order = file_get_contents("order.json");
            $fake_order = json_decode($fake_order, true);

            $order["note_attributes"] = $fake_order["note_attributes"];
            $order["customer"] = $fake_order["customer"];
            $order["billing_address"] = $fake_order["billing_address"];
            $order["payment_gateway_names"] = $fake_order["payment_gateway_names"];

            $current_order = $order;

            //if(!IntegracaoSankhyaShopifyController::pedido_exists($order["order_number"], sankhya:  $sankhya))
            {
                try
                {

                    $order['transactions'] = $shopify->Order($order["id"])->Transaction()->get();
                    $order["authorization_code"] = "";
                    if(isset($order['transactions']) && count($order['transactions']) > 0){
                        $order["authorization_code"] = $order['transactions'][0]["authorization"];
                    }

                    $order["gateway"] = isset($order["gateway"]) ? $order["gateway"] : $order["payment_gateway_names"][0];

                    /*
                    $isMercado = false;
                    foreach ($order['note_attributes'] as $note){
                        if (strtoupper($note['name']) == 'MERCADO' && strtoupper($note['value']) == "SIM") {
                            $isMercado = true;
                            break;
                        }
                    }

                    $tags = strtoupper($order["tags"]);
                    if(!$isMercado && !preg_match('/(MERCADO)/', $tags)) //verifica se é mercado, caso for, importa para o Sankhya
                        continue;
                     */

                    $result_ok = IntegracaoSankhyaShopifyController::insert_pedido($order, $sankhya);
                    if($result_ok)
                        $importados++;

                    sleep(5);
                
                }
                catch(Exception $ex)
                {
                    $erros++;
                    $json = json_encode($current_order);
                    $order_name = $current_order["name"];
                    logMsg("Erro no processamento da ordem Nº $order_name, Erro: $ex,  json: $json");
                }
            }

        }

        $sankhya->Logout();
    }
    catch(Exception $ex)
    {
        $json = json_encode($current_order);
        $order_name = $current_order["name"];
        logMsg("Erro no processamento da ordem Nº $order_name, Erro: $ex,  json: $json");
      echo $ex;

    }

    print("Total: " . count($orders) . "<br>");
    print("updated_at_min: " . date(DATE_ATOM, strtotime('-14 days')) . "<br>");
    print("Registro importados: " . $importados . "<br>");
    print("Erros: " . $erros . "<br>");
    print("<pre>".print_r($orders,true)."</pre>");
	print("Total: " . count($orders) . "<br>");
    print("updated_at_min: " . date(DATE_ATOM, strtotime('-14 days')) . "<br>");
    print("Registro importados: " . $importados . "<br>");
	



 		
				
		
		
	
	