<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './IntegracaoSankhyaShopify/IntegracaoShopifyHelpers.php';
require './utils.php';

use \PHPSankhyaAPI\SankhyaAPI;

$config_shopify = include "shopify_config.php";
$config_sankhya = include "sankhya_config.php";

$shopify = new PHPShopify\ShopifySDK($config_shopify);
$sankhya = new SankhyaAPI($config_sankhya["host"]);

$params = array(
    'financial_status' =>  'paid',
    'status' => 'any',
    'limit' => 250
);

if(isset($_GET["order_number"]) && strlen($_GET["order_number"]) > 0)
{
    $params["name"] = $_GET["order_number"];
}
else if(isset($_GET["created_at_min"]) && strlen($_GET["created_at_min"]) > 0)
{
    $created_at_min = date_create($_GET["created_at_min"]);
    if($created_at_min)
    {
        $created_at_min->setTime(0, 0, 0);
        $params["created_at_min"] = $created_at_min->format(DATE_ATOM);
    }

    if(isset($_GET["created_at_max"]) && strlen($_GET["created_at_max"]) > 0)
    {
        $created_at_max = date_create($_GET["created_at_max"]);
        if($created_at_max)
        {
            $created_at_max->setTime(23, 59, 59);
            $params["created_at_max"] = $created_at_max->format(DATE_ATOM);
        }
    }
}
else
{
    $params["updated_at_min"] = date(DATE_ATOM, strtotime('-7 days'));
}

$total_orders = 0;
$orders_with_gateway_payment_id = 0;
$orders_not_found_in_sankhya = 0;
$orders_updated = 0;
$orders_skipped = 0;
$update_errors = 0;

$current_order_number = "";
$current_nunota = 0;

try
{
    $orders = $shopify->Order->get($params);
    $total_orders = count($orders);

    $sankhya->Login($config_sankhya["user"], $config_sankhya["password"]);

    foreach($orders as $order)
    {
        $current_order_number = $order["order_number"] ?? "";
        if(!$current_order_number)
        {
            $orders_skipped++;
            continue;
        }

        $gateway_payment_id = IntegracaoShopifyHelpers::GetGatewayPaymentID($order);
        if(!$gateway_payment_id)
        {
            $orders_skipped++;
            continue;
        }

        $orders_with_gateway_payment_id++;
        $gateway_payment_id = trim((string)$gateway_payment_id);

        $pedidos_sankhya = $sankhya->pedidos->get(
            "AD_NUMPEDEMCOMMERCE = $current_order_number",
            "NUNOTA,AD_NUMPEDEMCOMMERCE,AD_ECOMMERCE_GATEWAY_PAYMENT_ID"
        );

        if(!isset($pedidos_sankhya["success"]) || !$pedidos_sankhya["success"] || !isset($pedidos_sankhya["total"]) || $pedidos_sankhya["total"] == 0)
        {
            $orders_not_found_in_sankhya++;
            print("Pedido não encontrado no Sankhya: $current_order_number<br>");
            continue;
        }

        foreach($pedidos_sankhya["data"] as $pedido_sankhya)
        {
            $current_nunota = $pedido_sankhya["NUNOTA"] ?? 0;
            if(!$current_nunota)
            {
                continue;
            }

            $gateway_payment_id_atual = trim((string)($pedido_sankhya["AD_ECOMMERCE_GATEWAY_PAYMENT_ID"] ?? ""));
            if(strlen($gateway_payment_id_atual) > 0)
            {
                continue;
            }

            try
            {
                $ret_update = $sankhya->pedidos->update_pedido(
                    $current_nunota,
                    array("AD_ECOMMERCE_GATEWAY_PAYMENT_ID" => $gateway_payment_id),
                    $sankhya->login["jsessionid"]
                );

                if(isset($ret_update["success"]) && $ret_update["success"] == 0)
                {
                    $update_errors++;
                    logMsg("Falha ao atualizar gateway_payment_id. order_number: $current_order_number | NUNOTA: $current_nunota | erro: " . ($ret_update["message"] ?? "sem mensagem"), "error", "sincronizar_gateway_payment_id.log");
                    continue;
                }

                $orders_updated++;
                print("Gateway_payment_id atualizado com sucesso: $current_order_number | NUNOTA: $current_nunota | gateway_payment_id: $gateway_payment_id<br>");
            }
            catch(Exception $ex)
            {
                $update_errors++;
                logMsg("Excecao ao atualizar gateway_payment_id. order_number: $current_order_number | NUNOTA: $current_nunota | erro: " . $ex->getMessage(), "error", "sincronizar_gateway_payment_id.log");
            }
        }
    }
}
catch(Exception $ex)
{
    $update_errors++;
    logMsg("Erro geral na sincronizacao do gateway_payment_id. order_number: $current_order_number | NUNOTA: $current_nunota | erro: " . $ex->getMessage(), "error", "sincronizar_gateway_payment_id.log");
}
finally
{
    if(isset($sankhya->login["jsessionid"]) && $sankhya->login["jsessionid"])
    {
        $sankhya->Logout();
    }
}

print("Total pedidos Shopify: $total_orders<br>");
print("Pedidos com gateway_payment_id: $orders_with_gateway_payment_id<br>");
print("Pedidos sem correspondencia no Sankhya: $orders_not_found_in_sankhya<br>");
print("Registros atualizados no Sankhya: $orders_updated<br>");
print("Pedidos ignorados: $orders_skipped<br>");
print("Erros: $update_errors<br>");

