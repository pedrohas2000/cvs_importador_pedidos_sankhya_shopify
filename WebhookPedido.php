<?php



ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define('SHOPIFY_APP_SECRET', 'cc325338a251f00042f8dd45ee464ea3aff3dfe014b7014cbc4286f3da22a5f5');

require 'PHPShopify/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './IntegracaoSankhyaShopifyController.php';
require './utils.php';


$sankhya = new SankhyaAPI("http://10.0.0.11:8180");

function verify_webhook($data, $hmac_header)
{
    $calculated_hmac = base64_encode(hash_hmac('sha256', $data, SHOPIFY_APP_SECRET, true));
    return hash_equals($hmac_header, $calculated_hmac);
}

try {

    $shopUrl = "cvs-cestas-de-alimentos.myshopify.com";
    $apikey = "6987e7f7f71de10e73c6b89e820c4b35";
    $password = "c10cbeacb1ebc10b6af35fedd96df207";

    $config = array(
        'ShopUrl' => $shopUrl,
        'ApiKey' => $apikey,
        'Password' => $password,
        'AccessToken' => $password
    );


    $json = file_get_contents('php://input');
    //file_put_contents("log_webhook_last.txt", $json, FILE_APPEND);
    $hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
    $verified = verify_webhook($json, $hmac_header);
    $order = null;
    if ($verified && strlen($json)) {


        $sankhya->Login("p.HENRIQUE", "456789");
        $order = json_decode($json, true);
        $client_id = IntegracaoSankhyaShopifyController::save_client($order, $sankhya);
        $pedido_id = IntegracaoSankhyaShopifyController::insert_pedido($order, $client_id, $sankhya);
        $sankhya->Logout();
    }

} catch (Exception $ex) {

    file_put_contents("log_webhook_err.txt", $ex, FILE_APPEND);
    $json = json_encode($order);
    logMsg("Erro no processamento da ordem json: $json - Erro: " . $ex);
}

