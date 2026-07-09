<?php

require 'vendor/autoload.php';
require './ShopifyController.php';
require './utils.php';

$config_shopify = include "shopify_config.php";

$shopify = new PHPShopify\ShopifySDK($config_shopify);

$order = ShopifyController::get_order_by_number($shopify, "1009");

if($order)
{
    $message = "Pedido confirmado, e nota fiscal emitida";
    $status = 'confirmed';
    $location_id = "62054826089";
    ShopifyController::add_fulfillment($shopify, $order, $status, $message, $location_id);
    $retorno = ShopifyController::update_status_fulfillment($shopify, $order, $status);
    print_r($retorno );
}

