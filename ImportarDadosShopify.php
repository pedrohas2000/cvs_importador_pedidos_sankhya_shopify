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
    else if(isset($_GET["created_at_max"]))
    {
        $params["created_at_max"] = date_format(date_create($_GET["created_at_max"]),DATE_ATOM) ;
    }
    else if(isset($_GET["created_at_min"]))
    {
        $params["created_at_min"] = date_format(date_create($_GET["created_at_min"]),DATE_ATOM) ;
    }
    else if(isset($_GET["data_inicial"]) && isset($_GET["data_final"]))
    {
        $params["created_at_min"] = date(DATE_ATOM, strtotime($_GET['data_inicial'] . ' 00:00:00-0300 -1 days')) ;
        $params["created_at_max"] = date(DATE_ATOM, strtotime($_GET['data_final'] . ' 23:59:59-0300 -1 days')) ;

    }
    else if(isset($_GET["data_inicial"]))
    {
        $params["created_at_min"] = date(DATE_ATOM, strtotime($_GET['data_inicial'] . ' 00:00:00-0300 -1 days')) ;
        $params["created_at_max"] = date(DATE_ATOM, strtotime($_GET['data_final'] . ' 23:59:59-0300 -1 days')) ;

    }
    else if(isset($_GET["created_at_min"]))
    {
        $params["created_at_min"] = date_format(date_create($_GET["created_at_min"]),DATE_ATOM) ;
    }
    else
    {
        $params["updated_at_min"] = date(DATE_ATOM, strtotime('-7 days'));
    }


    $orders = $shopify->Order->get($params);
	
	sort($orders);



    $importados = 0;
    $erros = 0;
    $current_order = null;
    try
	{
        $sankhya->Login($config_sankhya["user"], $config_sankhya["password"]);

        foreach ($orders as $order) {


            $current_order = $order;
            
            if(!IntegracaoSankhyaShopifyController::pedido_exists($order["order_number"], sankhya:  $sankhya))
            {
                try
                {

                    $order['transactions'] = $shopify->Order($order["id"])->Transaction()->get();
                    $order["authorization_code"] = "";
                    if(isset($order['transactions']) && count($order['transactions']) > 0)
                        $order["authorization_code"] = $order['transactions'][0]["authorization"];

                    $order["gateway"] = isset($order["gateway"]) ? $order["gateway"] : $order["payment_gateway_names"][0];
                    $order["gateway_payment_id"] = IntegracaoShopifyHelpers::GetGatewayPaymentID($order);

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


                    $result_ok = IntegracaoSankhyaShopifyController::insert_pedido($order, $sankhya, $shopify);
                    if($result_ok){
                        $importados++;
                        sleep(1);
                        //faz dupla checkagem
                        IntegracaoSankhyaShopifyController::verifica_pedido($order["order_number"], $sankhya);
                        sleep(1);
                        IntegracaoSankhyaShopifyController::verifica_pedido($order["order_number"], $sankhya);

                    }
                   // Adiciona delay de 5 segundos após cada importação
                   //sleep(1);

                
                }
                catch(Exception $ex)
                {
                    $erros++;
                    $json = json_encode($current_order);
                    $order_name = $current_order["name"];
                    logMsg("Erro no processamento da ordem Nº $order_name, Erro: $ex,  json: $json");
                }
            }
            else
            {
                try
                {
                    //faz dupla checkagem
                    IntegracaoSankhyaShopifyController::verifica_pedido($order["order_number"], $sankhya);
                      sleep(1);
                    IntegracaoSankhyaShopifyController::verifica_pedido($order["order_number"], $sankhya);
                }
                catch(Exception $ex)
                {
                    $erros++;
                    $json = json_encode($current_order);
                    $order_name = $current_order["name"];
                    logMsg("Erro ao verificar ordem Nº $order_name, Erro: $ex,  json: $json");
                }
            
            }

        }


    }
    catch(Exception $ex)
    {
        $json = json_encode($current_order);
        $order_name = $current_order["name"];
        logMsg("Erro no processamento da ordem Nº $order_name, Erro: $ex,  json: $json");
     
    }
    finally {
        $sankhya->Logout();
    }

    print("Total: " . count($orders) . "<br>");
    print("updated_at_min: " . date(DATE_ATOM, strtotime('-14 days')) . "<br>");
    print("Registro importados: " . $importados . "<br>");
    print("Erros: " . $erros . "<br>");
    print("<pre>".print_r($orders,true)."</pre>");
	print("Total: " . count($orders) . "<br>");
    print("updated_at_min: " . date(DATE_ATOM, strtotime('-14 days')) . "<br>");
    print("Registro importados: " . $importados . "<br>");
	



 		
				
		
		
	
	