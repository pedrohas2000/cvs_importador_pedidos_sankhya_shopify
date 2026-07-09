<?php


class ShopifyController
{

    public static function get_order_by_number($shopify, $order_number){

        $params = array(
            'financial_status' => 'paid',
            'order_number' => $order_number,
            'status' => 'open',
            'limit' => 1
        );

        $orders = $shopify->Order->get($params);
        return count($orders) > 0 ? $orders[0] : null;
    }

    public static function get_order_by_id($shopify, $id){

        $order = $shopify->Order($id)->get();
        return $order;
    }


    public static function get_order_by_order_number($clientShopifyGraphql, $shopify,  $orderNumber)
    {
                $queryString = <<<QUERY
                        {orders (first: 1, query: "name:#$orderNumber") { edges { node { id }}}}
                        QUERY;

                $response = $clientShopifyGraphql->query($queryString);
                $orders = json_decode($response->getBody()->getContents(), true);


                $qty = count($orders["data"]["orders"]["edges"]);
                $order_id = 0;
                if($qty > 0)
                    $order_id = preg_replace("/[^0-9]/", "", $orders["data"]["orders"]["edges"][0]["node"]["id"]);

                return $order_id > 0 ? self::get_order_by_id($shopify, $order_id) : null;
    }



    public static function create_fulfillment($shopify, $order, $status, $message, $location_id, $tracking_number = "", $tracking_url = "", $tracking_company = "Other")
    {
        /*
         *         $fulfillment_params = array(
            "order_id" => $order['id'],
            "shipment_status"=> $status,
            "message"=> $message,
            "notify_customer"=> true,
            "line_items_by_fulfillment_order" : [{"fulfillment_order_id" : 6388513669225}]
            'tracking_company' =>"Other",
            'tracking_number' => $tracking_number,
            'tracking_url' => $tracking_url,
            'location_id' => $location_id,
            //'tracking_urls' => [""],
        );

         */

        $fulfillment_order = $shopify->Order($order["id"])->FulfillmentOrder()->get();

        $fulfillment_params = array(
            'company' => $tracking_company,
            'number' => $tracking_number,
            'url' => $tracking_url,
            'location_id' => $location_id,
            "message"=> $message,
            "notify_customer"=> true,
            "line_items_by_fulfillment_order" => [["fulfillment_order_id" => $fulfillment_order[0]["id"]]]
        );

        $retorno = $shopify->Fulfillment->post($fulfillment_params);

        return $retorno;
    }

    public static function update_tracking_fulfillment($shopify, $order, $tracking_number, $tracking_url, $tracking_company = "Other")
    {
        $fulfillments = $shopify->Order($order['id'])->Fulfillment->get();

        if(count($fulfillments) > 0)
        {
            //adicionando evento com o status
            $fulfillment = $fulfillments[0];
            $trackingParams = array(
                "fulfillment" => [
                    "notify_customer"=> true,
                    "tracking_info" => [
                    'company' => $tracking_company,
                    'number' => $tracking_number,
                    'url' => $tracking_url
                    ]
              ]
            );

            if($fulfillment["tracking_url"] == $tracking_url){
                return true;
            }
            else
            {
               $fulfillment = $shopify->Fulfillment($fulfillment['id'])->update_tracking($trackingParams);
                return true;
            }
        }
    }


    public static function update_status_fulfillment($shopify, $order, $status, $message)
    {
        $fulfillments = $shopify->Order($order['id'])->Fulfillment->get();
        if(count($fulfillments) > 0)
        {
            //adicionando evento com o status
            $fulfillment = $fulfillments[0];
            $fulfillmentEvent = array(
                "status" => $status,
                "message" => $message
            );
            if($fulfillment["shipment_status"] == $status)
                return true;
            else
                $shopify->Order($fulfillment['order_id'])->Fulfillment($fulfillment['id'])->Event->post($fulfillmentEvent);
        }
    }


    public static function get_product_id_by_sku($clientShopifyGraphql, $sku){

        $queryString = <<<QUERY
                        {products (first: 1, query: "sku:$sku") { edges { node { id title }}}}
            QUERY;

        $response = $clientShopifyGraphql->query($queryString);
        $products_shopify = json_decode($response->getBody()->getContents(), true);
        $is_found = count($products_shopify["data"]["products"]["edges"]) > 0;

        return $is_found ? preg_replace("/[^0-9]/", "", $products_shopify["data"]["products"]["edges"][0]["node"]["id"]) : 0;

    }

    public static function get_product_by_id($shopify, $product_id)
    {
        return $shopify->Product($product_id)->get();
    }

    public static function get_product_metafield($shopify, $product_id, $namespace, $key)
    {

        $metafields = $shopify->Product($product_id)->Metafield()->get(["key" => $key, "namespace" => $namespace]);
        return count($metafields) > 0 ? $metafields[0] : null;
    }

    public static function update_product_metafield($shopify, $product_id, $namespace, $key, $type, $value)
    {
        //Atualiza Data alteração no shopify
        $meta_field_params = ["namespace" => $namespace, "key" => $key, "type" => $type, "value" => $value];
        $metafield = self::get_product_metafield($shopify, $product_id, $namespace, $key);

        if($metafield)
        {
            $shopify->Metafield($metafield["id"])->put($meta_field_params);
        }
        else
        {
            $shopify->Product($product_id)->Metafield()->post($meta_field_params);
        }


    }





}