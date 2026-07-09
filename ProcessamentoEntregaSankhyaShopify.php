<?php

header("Access-Control-Allow-Origin: *");

ini_set('memory_limit', '-1');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './utils.php';
require './ShopifyController.php';
use \Shopify\Clients\Graphql;
use Shopify\Auth\FileSessionStorage;


$config_shopify = include "shopify_config.php";
$config_sankhya = include "sankhya_config.php";

use \PHPSankhyaAPI\SankhyaAPI;

return;
$shopify = new PHPShopify\ShopifySDK($config_shopify);
$sankhya = new SankhyaAPI($config_sankhya["host"]);

$clientShopifyGraphql = new Graphql($config_shopify["ShopUrl"], $config_shopify["AccessToken"]);
Shopify\Context::initialize($config_shopify["ApiKey"], $config_shopify["Password"], '
    read_products,write_products,read_product_listings, read_orders, read_orders_listings', $config_shopify["ShopUrl"], new FileSessionStorage(sys_get_temp_dir() .'/php_sessions'), $config_shopify["ApiVersion"]);



$sankhya->auth->login($config_sankhya["user"], $config_sankhya["password"]);


    try
    {
        $nfe_pedidos = $sankhya->pedidos->get_all("AD_STATUS_ENTREGA IS NOT NULL AND (AD_STATUS_ENTREGA != 5 OR (AD_STATUS_ENTREGA = 5 AND AD_APPCHECKOUTDH >=  DATEADD(day, -1, GETDATE()))) AND TIPMOV = 'P'", "NUNOTA,STATUSNFE, CODEMP,CODPARC,DTNEG,STATUSNFE, AD_STATUS_ENTREGA, AD_NUMPEDEMCOMMERCE,AD_ECOMMERCE_CHECKOUTID ", 0);
    }
    catch (Exception $ex)
    {
        print_r($ex->getMessage());
    }

    $pedido_id = 0;
    $numpedido_shopify = 0;
    foreach ($nfe_pedidos["data"] as $nfe) {

        try
        {
            $pedido_id = $nfe["NUNOTA"];
            $numpedido_shopify = $nfe["AD_NUMPEDEMCOMMERCE"];

            $order = ShopifyController::get_order_by_order_number($clientShopifyGraphql, $shopify,  $numpedido_shopify);

            if($order && $order["order_number"] == $numpedido_shopify)
            {

                $location_id = "62054826089";
                $message = "";
                $status = "";
                $tracking_url = "";
                $tracking_number = "EM_BREVE";

                if($nfe["AD_STATUS_ENTREGA"] == '1')//NOTA_EMITIDA
                {
                    $message = "Pedido confirmado, e nota fiscal emitida";
                    $status = 'confirmed';
                }
                else if($nfe["AD_STATUS_ENTREGA"] == '2')//EM_TRANSITO
                {
                    $message = "Pedido está sendo preparado para entrega";
                    $status = 'in_transit';
                }
                else if($nfe["AD_STATUS_ENTREGA"] == '3')//SAIU_PARA_ENTREGA
                {
                    $message = "Pedido saiu para entrega";
                    $status = 'out_for_delivery';

                }
                else if($nfe["AD_STATUS_ENTREGA"] == '5')//ENTREGUE
                {
                    $message = "Pedido foi entregue";
                    $status = 'delivered';
                    $tracking_url = "http://appsankhya.cvscestas.com.br:8180/mge/AD_APPENTASSINATURA@ASSINATURAIMG@NUNOTA=$pedido_id.dbimage";
                    $tracking_number = "ASSINATURA";
                }
                else if($nfe["AD_STATUS_ENTREGA"] == '4')//OCORRENCIA
                {
                    $last_ocorrencia = $sankhya->db_explorer->execute_query("select  * from AD_APPENTROCORRNOT  where  NUNOTA = $pedido_id order by SEQ desc; ");
                    $message = "Houve falha na entrega" . (count($last_ocorrencia) > 0 ? " - " . $last_ocorrencia[0]["DESCROCOR"] : "");
                    $status = 'failure';
                }

                $fulfillment = count($order['fulfillments']) > 0 ? $order['fulfillments'][0] : null;
                if($fulfillment == null)
                {

                    try {
                        $fulfillment = ShopifyController::create_fulfillment($shopify, $order, $status, $message, $location_id, $tracking_number, $tracking_url);
                        ShopifyController::update_status_fulfillment($shopify, $order, $status, $message);
                    }catch (Exception $ex){
                        print_r($ex->getMessage());
                    }
                }
                else
                {
                    ShopifyController::update_status_fulfillment($shopify, $order, $status, $message);
                    ShopifyController::update_tracking_fulfillment($shopify, $order, $tracking_number, $tracking_url);
                }
            }


        }catch(Exception $ex)
        {
            print_r("Erro ao processar o pedido $pedido_id, Order number: $numpedido_shopify, erro: " . $ex->getMessage());
        }
    }



?>


				
		
		
	
	