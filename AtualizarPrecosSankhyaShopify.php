<?php

header("Access-Control-Allow-Origin: *");

ini_set('memory_limit', '-1');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './utils.php';
require 'ShopifyController.php';

use \PHPSankhyaAPI\SankhyaAPI;
use \Shopify\Clients\Graphql;
use \Shopify\Clients;
use Shopify\Auth\FileSessionStorage;

$config_shopify = include "shopify_config.php";
$config_sankhya = include "sankhya_config.php";


    Shopify\Context::initialize($config_shopify["ApiKey"], $config_shopify["Password"], '
    read_products,write_products,read_product_listings', $config_shopify["ShopUrl"], new FileSessionStorage(sys_get_temp_dir() .'/php_sessions'), $config_shopify["ApiVersion"]);

$shopify = new PHPShopify\ShopifySDK($config_shopify);
$sankhya = new SankhyaAPI($config_sankhya["host"]);


$clientShopify = new Graphql(
    $config_shopify["ShopUrl"],
    $config_shopify["AccessToken"]
);



$sankhya->auth->login($config_sankhya["user"], $config_sankhya["password"]);

$produtos = $sankhya->db_explorer->execute_query("SELECT exc.*, tab.*, p.AD_SKUECOMMERCE, p.AD_TPMERCADO, p.AD_SINCWEB FROM TGFEXC as exc 
                                                            LEFT JOIN TGFTAB AS tab on tab.NUTAB = exc.NUTAB 
                                                            LEFT JOIN TGFPRO AS p on p.CODPROD = exc.CODPROD 
                                                            WHERE AD_SINCWEB = 'S' AND AD_DT_ULTIMA_ATUALIZACAO >=  DATEADD(day, -1, GETDATE()) and exc.NUTAB = 84
                                                            ORDER BY AD_DT_ULTIMA_ATUALIZACAO DESC;");



echo "Qtde Produtos: " . count($produtos) . "<br>";

if(count($produtos) == 0)
    die();

$cont = 0;


foreach($produtos as $produto)
{

    if($cont >= 999999999)
        break;
    $cont++;



    try
    {
        $is_pre_cadastrado = false;
        $sku = $produto["CODPROD"];

        if(strlen($produto["AD_SKUECOMMERCE"]) > 0)
        {
            $product_id = ShopifyController::get_product_id_by_sku($clientShopify, $produto["AD_SKUECOMMERCE"]);
            $is_pre_cadastrado = $product_id > 0;
        }
        else
        {
            $product_id = ShopifyController::get_product_id_by_sku($clientShopify, $produto["CODPROD"]);
        }

        $is_found = $product_id > 0;
        if(!$is_found)
            continue;



        $data_alteracao_sankhya = date_create_from_format("d/m/Y H:i:sT", $produto["AD_DT_ULTIMA_ATUALIZACAO"] . "-3");

        $data_alteracao_metafield = ShopifyController::get_product_metafield($shopify, $product_id, "sankhya", "data_ultima_alteracao_preco");
        if($data_alteracao_metafield)
        {
            //verifica a ultima data de alteração no sankhya que o produto foi atualizado no site shopify
            if($data_alteracao_sankhya->format(DATE_ATOM) == $data_alteracao_metafield["value"])
            {
                continue;
            }

        }


            $preco = $produto["VLRVENDA"];

           $status = $produto["AD_TPMERCADO"] == 'S' && $preco > 0 ? 'active' : 'archived'; //active, archived, draft

            //atualiza as informações do produto


            $params = array(
                "status" => $status,
            );

            $product = $shopify->Product($product_id)->put($params);

            $variants = $shopify->Product($product_id)->Variant->get();
            foreach($variants as $v)
            {
                    $params = array(
                        "price" => $preco,
                        "compare_at_price" => $preco,
                    );

                $shopify->Product($product_id)->Variant($v["id"])->put($params);
            }

        //Atualiza Data alteração no shopify
        ShopifyController::update_product_metafield($shopify, $product_id, "sankhya", "data_ultima_alteracao_preco", "date_time", $data_alteracao_sankhya->format(DATE_ATOM));


            print("<pre>".print_r($product,true)."</pre>");
            print("<pre>$cont</pre>");
        }
        catch (Exception $ex)
        {
            print("<pre>Houve um erro ao importar o produto de sku: $sku, erro: $ex </pre>");
        }

}






//
//
//    print("Total: " . count($orders) . "<br>");
//    print("updated_at_min: " . date(DATE_ATOM, strtotime('-14 days')) . "<br>");
//    print("Registro importados: " . $importados . "<br>");
//    print("<pre>".print_r($orders,true)."</pre>");
//	print("Total: " . count($orders) . "<br>");
//    print("updated_at_min: " . date(DATE_ATOM, strtotime('-14 days')) . "<br>");
//    print("Registro importados: " . $importados . "<br>");
//



 		
				
		
		
	
	