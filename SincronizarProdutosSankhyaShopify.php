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

return;
    Shopify\Context::initialize($config_shopify["ApiKey"], $config_shopify["Password"], '
    read_products,write_products,read_product_listings', $config_shopify["ShopUrl"], new FileSessionStorage(sys_get_temp_dir() .'/php_sessions'), $config_shopify["ApiVersion"]);

$shopify = new PHPShopify\ShopifySDK($config_shopify);
$sankhya = new SankhyaAPI($config_sankhya["host"]);


$clientShopify = new Graphql(
    $config_shopify["ShopUrl"],
    $config_shopify["AccessToken"]
);



$sankhya->auth->login($config_sankhya["user"], $config_sankhya["password"]);

$sku = isset($_GET['SKU']) ? $_GET['SKU'] : "";
$sku_ecommerce = isset($_GET['SKU_ECOMMERCE']) ? $_GET['SKU_ECOMMERCE'] : "";

$query = "AD_SINCWEB = 'S' AND DTALTER >=  DATEADD(day, -1, GETDATE()) " . ($sku ? " AND CODPROD = '$sku'" : "") . ($sku_ecommerce ? " AND AD_SKUECOMMERCE = '$sku_ecommerce'" : "");


$produtos = $sankhya->produtos->get_all($query, "CODPROD,DESCRPROD,LOCAL,MARCA,CODVOL,CODLOCALPADRAO,CODBARTRIBDIFGTIN, CODBARDIFGTIN, IMAGEM, ATIVO, PESOBRUTO, PESOLIQ, ENDIMAGEM, CARACTERISTICAS, AD_INFOCOMPLEMENTAR, AD_TPMERCADO, TIPOKIT, AD_SKUECOMMERCE, DTALTER", 0);
echo "Qtde Produtos: " . $produtos["total"] . "<br>";
$cont = 0;

/*
foreach($produtos["data"] as $produto) {
if($produto["AD_SKUECOMMERCE"] == 18)
    echo "<br> " . $produto["CODPROD"] . " - ". $produto["DESCRPROD"];
} die();
*/


foreach($produtos["data"] as $produto)
{

    if($cont >= 10)
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

        if(!$is_pre_cadastrado)
        {
            $product_id = ShopifyController::get_product_id_by_sku($clientShopify, $produto["CODPROD"]);
        }

        $is_found = $product_id > 0;

        $product = $is_found ? ShopifyController::get_product_by_id($shopify, $product_id) : null;


        $data_alteracao_sankhya = date_create_from_format("d/m/Y H:i:sT", $produto["DTALTER"] . " -3");

        if($is_found)
        {

            $data_alteracao_metafield = ShopifyController::get_product_metafield($shopify, $product_id, "sankhya", "data_alteracao");
            print_r($data_alteracao_metafield);
            if($data_alteracao_metafield)
            {
                //verifica a ultima data de alteração no sankhya que o produto foi atualizado no site shopify
                if($data_alteracao_sankhya->format(DATE_ATOM) == $data_alteracao_metafield["value"])
                {
                    continue;
                }

            }
        }



        $preco_produto = $sankhya->db_explorer->execute_query("SELECT TOP 1  * FROM tgfexc WHERE codprod = $sku ORDER BY NUTAB DESC");

        $preco = count($preco_produto) > 0 && $preco_produto[0]["VLRVENDA"] != "" ? $preco_produto[0]["VLRVENDA"] : 0;


        $informacao_nutricional = $sankhya->db_explorer->execute_query("SELECT * FROM AD_INFONUTRICIONAL WHERE codprod = $sku ;");
        $body_html = $produto["AD_INFOCOMPLEMENTAR"] . "<br><br>" . $produto["CARACTERISTICAS"];

        if(count($informacao_nutricional) > 0){
            $body_html .= "<br><br>";
            $body_html .= "<br><h2>Informações Nutricionais:</h2><br>";
            $body_html .= "<table><tbody>";
            $body_html .= "<tr><td>Item</td><td>Quantidade</td><td>Valor Diário</td></tr>";
            foreach ($informacao_nutricional as $info){
                $body_html .= "<tr>";
                $body_html .= "<td>" . $info["ITEM"]. "</td>";
                $body_html .= "<td>" . $info["QTDPORCAO"]. "</td>";
                $body_html .= "<td>" . $info["VLRDIARIO"]. "</td>";
                $body_html .= "</tr>";
            }
            $body_html .= "</tbody></table>";
        }


            if(!$is_found) {
                //cadastra o produto com status ativo, para que possa aparecer nos canais de venda, caso seja atualizado com status arquivado
                $status = "active";
                $params = array(
                    'title' => $produto["DESCRPROD"] . " - " . $produto["MARCA"],
                    "tags" => "MERCADO",
                    "body_html" => $body_html,
                    "status" => $status,
                    "options" => [
                        "name" => "Ciclo de compra",
                        "values" => [
                            "Compra única",
                            "Compra mensal"
                        ]
                    ],
                    "variants" => array(['sku' => $produto["CODPROD"],
                        "weight" => $produto["PESOLIQ"] ,
                        "weight_unit" => "kg",
                        "price" => $preco,
                        "compare_at_price" => $preco,
                        "barcode" => $produto["CODBARTRIBDIFGTIN"],
                        "option1" => "Compra única"
                    ],['sku' => $produto["CODPROD"],
                        "weight" => $produto["PESOLIQ"] ,
                        "weight_unit" => "kg",
                        "price" => $preco * 0.95,
                        "compare_at_price" => $preco,
                        "barcode" => $produto["CODBARTRIBDIFGTIN"],
                        "option1" => "Compra mensal"
                    ]
                    ),
                );
                $product = $shopify->Product()->post($params);
            }



            $status = $produto["AD_TPMERCADO"] == 'S' && $preco > 0 ? 'active' : 'archived'; //active, archived, draft
            print_r($status);
            //atualiza as informações do produto

             $product_id = $is_found ?  $product_id : $product["id"];

            if($is_pre_cadastrado)//caso o produto já foi pré-cadastrado, evita atualizar  informações (para evitar substituir informações pre cadastradas no shopify
            {

                $params = array(
                    "status" => $status,
                );
                $product = $shopify->Product($product_id)->put($params);
            }
            else
            {
                $params = array(
                    'title' => $produto["DESCRPROD"] . (strlen($produto["MARCA"]) > 0 ? " - " . $produto["MARCA"] : ""),
                    "tags" => "MERCADO",
                    "body_html" => $body_html,
                    "status" => $status,
                    "published_scope" => "global",// web, global

                );

                $product = $shopify->Product($product_id)->put($params);

            }


            $variants = $shopify->Product($product_id)->Variant->get();

            foreach($variants as $v)
            {

                $params = [];
                $is_mensal = str_contains($v["title"], 'mensal');
                if($is_pre_cadastrado) //caso for precadastrado atualiza somente o preço
                {
                    $params = array(
                        'sku' => $is_pre_cadastrado ? $produto["AD_SKUECOMMERCE"] : $produto["CODPROD"],
                        "price" => $preco,
                        "compare_at_price" => $preco,
                    );
                }
                else
                {
                    $params = array(
                        'sku' => $is_pre_cadastrado ? $produto["AD_SKUECOMMERCE"] : $produto["CODPROD"],
                        "weight" => $produto["PESOLIQ"] ,
                        "weight_unit" => "kg",
                        "price" =>   $is_mensal ? $preco * 0.95 : $preco,
                        "compare_at_price" => $preco,
                        "barcode" => $produto["CODBARTRIBDIFGTIN"],
                    );
                }


                $shopify->Product($product_id)->Variant($v["id"])->put($params);
            }



            if(!$is_pre_cadastrado)
            {
                $images = $sankhya->db_explorer->execute_query("select  IMAGEM from TGFPRO  where  CODPROD = " . $produto["CODPROD"]);

                $shopify_images = $shopify->Product($product["id"])->Image()->get();
                foreach ($shopify_images as $simg)
                {
                    $shopify->Product($product["id"])->Image($simg["id"])->delete();
                }

                $image = null;
                if(count($images) > 0 && $images[0]["IMAGEM"] != null){
                    $params = array(
                        'attachment' => $sankhya->db_explorer->hex_to_base64($images[0]["IMAGEM"]),
                    );
                    $image = $shopify->Product($product["id"])->Image()->post($params);
                }
                //liberando memoria
                $image = null;
                $params = null;
                $images = null;

            }



            //Atualiza Data alteração no shopify
            ShopifyController::update_product_metafield($shopify, $product_id, "sankhya", "data_alteracao", "date_time",
                $data_alteracao_sankhya->format(DATE_ATOM));


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



 		
				
		
		
	
	