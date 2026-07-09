<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'vendor/autoload.php';
require './utils.php';
require 'Paypal/PaypalAPIService.php';
use \PHPSankhyaAPI\SankhyaAPI;

$config_shopify = include "shopify_config.php";

$shopify = new PHPShopify\ShopifySDK($config_shopify);

$params = array(
    'financial_status' =>  'paid',
    'status' => 'open',
    //'fulfillment_status' => 'unfulfilled',
    'limit' => 250
);

if(isset($_GET["order_number"])){
    $params["name"] = $_GET["order_number"];

}
else
{
    $params["updated_at_min"] = date(DATE_ATOM, strtotime('-7 days'));
}


$orders = $shopify->Order->get($params);

sort($orders);

$order = $orders[0];



$order['transactions'] = $shopify->Order($order["id"])->Transaction()->get();
$order["authorization_code"] = "";

if(!isset($order['transactions']) || count($order['transactions']) == 0)
    throw new Exception("Transação inexistente no pedido da shopify. Order Name: " . $order["name"]);

$transaction = $order['transactions'][0];

//print('<pre>' . print_r($order['transactions'], true)  . '</pre>');
//print('<pre>' . print_r($transaction, true)  . '</pre>');


$paypalService = new PaypalAPIService();
$transaction_date = $transaction["receipt"]["PaymentInfo"]["PaymentDate"];
$authorization_code = $transaction["authorization"];

$detalhes_pagamento = $paypalService->GetPaymentsByTransactionID($authorization_code, $transaction_date, $transaction_date);
if($detalhes_pagamento->total_items > 0)
print('<pre>' . print_r($detalhes_pagamento, true)  . '</pre>');


