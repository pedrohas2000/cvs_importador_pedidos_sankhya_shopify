<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './IntegracaoSankhyaShopify/IntegracaoSankhyaShopifyController.php';
require './utils.php';

$config_shopify = include "shopify_config.php";
$config_sankhya = include "sankhya_config.php";

$sankhya = new SankhyaAPI($config_sankhya["host"]);

function verify_webhook($data, $hmac_header, $webhook_secret)
{
    $calculated_hmac = base64_encode(hash_hmac('sha256', $data, $webhook_secret, true));
    return hash_equals($hmac_header, $calculated_hmac);
}

try {
    $json = file_get_contents('php://input');
    $hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
    $webhook_secret = $config_shopify['WebhookSecret'] ?? '';
    $verified = $webhook_secret && verify_webhook($json, $hmac_header, $webhook_secret);
    $order = null;

    if ($verified && strlen($json)) {
        $sankhya->Login($config_sankhya["user"], $config_sankhya["password"]);
        $order = json_decode($json, true);
        $client_id = IntegracaoSankhyaShopifyController::save_client($order, $sankhya);
        $pedido_id = IntegracaoSankhyaShopifyController::insert_pedido($order, $sankhya);
        $sankhya->Logout();
    }

} catch (Exception $ex) {
    file_put_contents("log_webhook_err.txt", $ex, FILE_APPEND);
    $json = json_encode($order);
    logMsg("Erro no processamento da ordem json: $json - Erro: " . $ex);
}
