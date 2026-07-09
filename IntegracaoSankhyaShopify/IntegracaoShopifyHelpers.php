<?php

class IntegracaoShopifyHelpers {

public static function get_endereco_from_shopify_address($order_address){

    $explode_address = explode(",", $order_address["address1"]);
    $logradouro = isset($explode_address[0]) ? trim($explode_address[0]) : "";

    if(!$logradouro)
        $logradouro = trim($explode_address[1]);

    $logradouro = extrairLogradouro($logradouro);

    $tipo = explode(" ", $logradouro)[0];
    $nome_endereco = trim(str_replace($tipo, '', $logradouro));
    $logradouro = trim(mb_strimwidth($logradouro,0,60, ""));
  
    $params_endereco  = array(
        "NOMEEND" => ["$"=> $nome_endereco],
        "DESCRICAOCORREIO" => ["$"=> $logradouro],
        "TIPO" => ["$"=> $tipo], 
    );

    return $params_endereco;

}

public static function get_endereco_from_viacep($viacep_address){

    $logradouro = $viacep_address["logradouro"] ?? "";

    $explode_address = explode(",", $logradouro);
    $logradouro = isset($explode_address[0]) ? trim($explode_address[0]) : "";
   
    if(!$logradouro)
        $logradouro = trim($logradouro);
    
    $logradouro = extrairLogradouro($logradouro);

    $tipo = explode(" ", $logradouro)[0];
    $nome_endereco = trim(str_replace($tipo, '', $logradouro));
    $logradouro = trim(mb_strimwidth($logradouro,0,60, ""));
  
    $params_endereco  = array(
        "NOMEEND" => ["$"=> $nome_endereco],
        "DESCRICAOCORREIO" => ["$"=> $logradouro],
        "TIPO" => ["$"=> $tipo], 
    );

    return $params_endereco;

}

public static function get_bairro_from_viacep($viacep_address, $cod_regiao = 0){

       $bairro = $viacep_address["bairro"] ?? "";

        $params_bairro =  array(
            "CODREG" => ["$"=> $cod_regiao],
            "NOMEBAI" => ["$"=> $bairro],
            "DESCRICAOCORREIO" => ["$"=> $bairro],
        );

    return $params_bairro;

}

public static function get_bairro_from_shopify_address($order_address, $cod_regiao = 0){

    $address2 = $order_address["address2"];
    $pos = stripos($address2, "bairro");
    $bairro = $pos !== false ?  substr($address2, $pos + 6) : "";
    $bairro = trim(mb_strimwidth($bairro,0,20, ""));
    $bairro = preg_replace('/[0-9]/', '', $bairro);

    $params_bairro =  array(
        "CODREG" => ["$"=> $cod_regiao],
        "NOMEBAI" => ["$"=> $bairro],
        "DESCRICAOCORREIO" => ["$"=> $bairro],
    );

    return $params_bairro;

}


public static function get_cidade_from_viacep($viacep_address){

    $cidade = $viacep_address["localidade"] ?? "";


 return $cidade;

}

public static function get_cidade_from_shopify_address($order_address){

    $cidade = trim($order_address["billing_address"]["city"]);
    $cidade = preg_replace('/[0-9]/', '', $cidade);

    if(strtoupper($cidade) == "SP")
        $cidade = "São Paulo";

 return $cidade;

}

public static function get_numero_endereco_from_shopify_address($order_address){

    $numero_endereco = extrairNumeroEndereco($order_address["address1"]);

    if (!$numero_endereco) {
          $numero_endereco = extrairNumeroEndereco($order_address["address2"]);
    }
    return $numero_endereco;
}

public static function get_complemento_endereco_from_shopify_address($order_address){

    $complemento_endereco =  trim(mb_strimwidth($order_address["address2"],0,20, ""));
    return $complemento_endereco;

}

public static function get_telefone_from_shopify_address($order_address) {
    $telefone = isset($order_address['phone']) ? $order_address['phone'] : '';
    $telefone = str_replace("+55", "", trim($telefone));
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    return $telefone;
}

public static function get_cep_from_shopify_address($order_address) {
    $cep = isset($order_address['zip']) ? $order_address['zip'] : '';
    $cep = preg_replace('/[^0-9]/', '', $cep);
    return $cep;
}


public static function get_cpf_from_shopify_address($order_address) {

    $cpf_cnpj = preg_replace('/[^0-9]/', '', $order_address["company"]);
    return isValidCpfCnpj($cpf_cnpj) ? $cpf_cnpj : "";
}

public static function get_cpf_from_shopify_order($order) {

    $cpf_cnpj = "";
    foreach ($order['note_attributes'] as $notes)
    {
        if ($notes['name'] == 'payment_additional_taxid') {$cpf_cnpj = preg_replace('/[^0-9]/', '', $notes['value']); }
    }
    return $cpf_cnpj;
}

public static function get_cpf_from_graphql($shopify, $order_id)
{
    if(!$shopify || empty($order_id))
    {
        return "";
    }

    try
    {
        $query = '{
            order(id: "gid://shopify/Order/' . $order_id . '") {
                localizationExtensions(first: 10) {
                    edges {
                        node {
                            title
                            value
                        }
                    }
                }
            }
        }';

        $dados_adicionais = $shopify->GraphQL->post($query);
        $edges = $dados_adicionais["data"]["order"]["localizationExtensions"]["edges"] ?? [];

        foreach($edges as $edge)
        {
            $title = strtoupper(trim($edge["node"]["title"] ?? ""));
            if($title === "CPF/CNPJ" || $title === "CPF_CNPJ")
            {
                return preg_replace('/[^0-9]/', '', ($edge["node"]["value"] ?? ""));
            }
        }
    }
    catch(Exception $ex)
    {
        logMsg("Falha ao buscar CPF/CNPJ via GraphQL (order_id: $order_id): " . $ex->getMessage(), "error", "pedido.log");
    }

    return "";
}


public static function create_pedido($client_id, $data_pedido, $tipo_operacao,
                                     $cod_tipo_negociacao, $ordem_number,  $authorization_code, $gateway_payment_id, $checkout_id, $itens_pedido,
                                     $sequencia = 1, $data_previsao_entrega, $extra_fields = []){


    $cabecalhoNota = array("cabecalho" =>
        array(
        "NUNOTA" =>  ["$"=> ""],
        "CODPARC" => ["$"=> $client_id],
        "DTNEG" => ["$"=> $data_pedido],
        "CODTIPOPER" => ["$"=> $tipo_operacao],
        "CODTIPVENDA" => ["$"=>  $cod_tipo_negociacao],
        "CODNAT" => ["$"=>  "1010300"],
        "CODCENCUS" => ["$"=>  "5001004"], //Centro de Resultado
        "CODVEND" => ["$"=> "2"],
        "CODEMP" => ["$"=> "2"],
        "TIPMOV" => ["$"=> "P"],
        "NUMPEDIDO2" => ["$"=> $ordem_number],
        "AD_NUMPEDEMCOMMERCE" => ["$"=> $ordem_number],
        "AD_ECOMMERCE_CHECKOUTID" => ["$"=> $checkout_id],
        "AD_ECOMMERCE_GATEWAY_PAYMENT_ID" => ["$"=> $gateway_payment_id],
        "AD_SEQPEDIDOSITE" => ["$"=> $sequencia],
        "AD_PAYMENT_AUTHORIZATION_CODE" => ["$"=> $authorization_code],
        "DTPREVENT" => ["$"=> $data_previsao_entrega],
        "AD_REGISTROTESTE" => ["$"=> "Sim"], //TODO - remover esse campo, é apenas para facilitar a identificação de pedidos de teste no ambiente de homologação do Sankhya
    ),  "itens" =>  array("INFORMARPRECO" => "True", "item" => $itens_pedido)
    );

    foreach (array_keys($extra_fields)as $field)
        $cabecalhoNota["cabecalho"][$field] = $extra_fields[$field];

    return $cabecalhoNota;

}

public static function create_item_pedido($sku, $qty, $cod_volume, $preco, $valorDesconto = 0, $percentagemDesconto = 0){

    return array(
        "NUNOTA" => ["$"=> ""],
        "CODPROD" => ["$"=> $sku],
        "QTDNEG" => ["$"=> $qty],
        "CODLOCALORIG" => ["$"=> "10000000"],
        "CODVOL" => ["$"=>  $cod_volume],
        "VLRUNIT" => ["$"=>  $preco],
        "VLRTOT" => ["$"=> $preco * $qty],
        "VLRDESC" => ["$"=> $valorDesconto],
        "PERCDESC" => ["$"=>  $percentagemDesconto],
        "CODBENEFNAUF" => ["$"=> null],
    );

}

public static function create_destinatario($nunota, $cod_parceiro, $numero_pedido_externo, $cod_endereco_entrega, 
$quantidade, $cod_tipo_negociacao, $tipo_faturamento, $observacao, $extra_fields = []){


    $destinatario = array(
            "NUNOTA" =>  ["$"=> $nunota],
            "CODPARCDEST" => ["$"=> $cod_parceiro],
            "CODENDENTREGA" => ["$"=> $cod_endereco_entrega],
            "QTD" => ["$"=> $quantidade],
            "SEQCFGV" => ["$"=>  $cod_tipo_negociacao],
            "OBSERVACAO" => ["$"=>  $observacao],
            "TIPOFAT" => ["$"=> $tipo_faturamento],
            "NUMPEDCLIENTE" => ["$"=> $numero_pedido_externo],
         );

    foreach (array_keys($extra_fields)as $field)
        $destinatario[$field] = $extra_fields[$field];

    return $destinatario;

}


public static function create_item_brinde($sku, $sankhya){
    $products = $sankhya->produtos->get($expression="CODPROD= $sku", "CODPROD, AD_SKUECOMMERCE, DESCRPROD, CODVOL, LOCAL,MARCA");
    if($products["success"] && $products["total"] == 0)
        throw new Exception("Produto ou Brinde Inexistente, por favor verifique: SKU: $sku");

    $produto = $products["data"][0];

    $item_pedido = array(
        "NUNOTA" => ["$"=> ""],
        "CODPROD" => ["$"=> $sku],
        "QTDNEG" => ["$"=> 1],
        "CODLOCALORIG" => ["$"=> "10000000"],
        "CODVOL" => ["$"=>  $produto["CODVOL"]],
        "VLRUNIT" => ["$"=>  "0.00"],
        "VLRTOT" => ["$"=>  "0.00"],
        "VLRDESC" => ["$"=> "0"],
        "PERCDESC" => ["$"=>  "0"],
    );

    return $item_pedido;
}


private static function PagBrasilPaymentToSankhyaTipoNegociacao($payment){

         

    $type = strtoupper($payment->payment_method);

    $payment_type_text = strtoupper( PagBrasilAPIService::PAYMENT_METHODS[$type]);

    $tipo_pagamento = "PAGBRASIL";

    /* CÓDIGO FOI COMENTADO, PEDIRAM PARA DEIXAR SOMENTE COMO PAGBRASIL (TIPO)
    if($type == "C"){
        $brand_char =  strtoupper($payment->cc_brand);
        $brand = strtoupper(PagBrasilAPIService::CREDIT_CARD_BRANDS[$brand_char]);
        $tipo_pagamento .= " - " . $brand;

        if($payment->cc_installments == 1)
            $tipo_pagamento .= " - " . $payment->cc_installments . " Parcela" . ($payment->cc_installments > 1 ? "s" : "");
        else
            $tipo_pagamento .= " - " . $payment->cc_installments . " Parcelas";
    }
    else
        $tipo_pagamento .= " - " . $payment_type_text;
    */


    return strtoupper($tipo_pagamento);
}


private static function MercadoPagoPaymentToSankhyaTipoNegociacao($payment){

    $type = strtoupper($payment->payment_method->type);
    $payment_type_id = strtoupper($payment->payment_method->id);
    $tipo_pagamento = "MERCADO PAGO";

    if($type == "CREDIT_CARD"){
        $tipo_pagamento .= " - " . $payment_type_id;

        if($payment->installments == 1)
            $tipo_pagamento .= " - " . $payment->installments . " Parcela";
        else
            $tipo_pagamento .= " - " . $payment->installments . " Parcelas";
    }
    else
    {
        if($payment_type_id == "BOLBRADESCO")
            $tipo_pagamento .= " - BRADESCO - BOLETO";
        else
            $tipo_pagamento .= strlen($payment_type_id) > 0 ?  " - " . $payment_type_id : "";
    }
    return strtoupper($tipo_pagamento);
}


 private static function BraspagPaymentToSankhyaTipoNegociacao($payment){
     $provider = preg_replace("/\d/u", "", $payment->Provider);
     $tipo_pagamento = "BRASPAG - " .  $provider;

     if(isset($payment->CreditCard)){
         $tipo_pagamento .= " - " . $payment->CreditCard->Brand;
         if($payment->Installments == 1)
             $tipo_pagamento .= " - " . $payment->Installments . " Parcela";
         else
             $tipo_pagamento .= " - " . $payment->Installments . " Parcelas";
     }else{
         $type = str_replace("DebitCard", "", $payment->Type);
         $tipo_pagamento .= strlen($type) > 0 ?  " - " . $type : "";
     }
     return strtoupper($tipo_pagamento);
}


private static function PaypalPaymentToSankhyaTipoNegociacao($payment){

    $tipo_pagamento = "PAYPAL";
    return strtoupper($tipo_pagamento);

}

public static function GetCheckoutID($order)
{

    $checkout_id = null;

    if(str_contains(strtolower($order["gateway"]), 'pagbrasil'))
    {
        foreach ($order['note_attributes'] as $notes)
        {
            if(strtolower($notes['name']) == 'pagstream_recurring_order' && strlen($notes['value']) > 0) { $checkout_id = $notes['value']; }
        }

    }

    if(!$checkout_id)
    {
        $checkout_id = $order["checkout_id"];
    }


    return $checkout_id;

}

public static function GetGatewayPaymentID($order)
{
    $gateway_payment_id = $order["gateway_payment_id"] ?? "";

    if(isset($order["note_attributes"]) && is_array($order["note_attributes"]))
    {
        foreach ($order["note_attributes"] as $notes)
        {
            $note_name = strtolower($notes["name"] ?? "");
            $note_value = trim($notes["value"] ?? "");

            if(strlen($note_value) > 0 && ($note_name == "payment_additional_order" || $note_name == "payment_gateway_id"))
            {
                $gateway_payment_id = $note_value;
                break;
            }
        }
    }
    print_r("Gateway Payment ID: $gateway_payment_id" . PHP_EOL);
    return $gateway_payment_id;
}


private static function GetTextTipoPagamento($shopify_order, $gateway_name)
{
    $gateway_name = strtolower($gateway_name);

    if($gateway_name == "braspag")
    {
        $checkout_reference = self::GetCheckoutID($shopify_order);
        $braspagService = new BraspagAPIService();
        $detalhes_pagamento = $braspagService->GetPaymentsByMerchantOrderID($checkout_reference);

        if($detalhes_pagamento == null)
            throw new Exception("Código de referência no braspag, não foi encontrado. Checkout Reference: $checkout_reference");


        return self::BraspagPaymentToSankhyaTipoNegociacao($detalhes_pagamento->Payment);;
    }
    else if($gateway_name == "pagbrasil" || str_contains($gateway_name, "pagbrasil"))
    {
   
        $checkout_reference = self::GetCheckoutID($shopify_order);

        $pagbrasil = new PagBrasilAPIService();
        $detalhes_pagamento = $pagbrasil->GetPaymentsByID($checkout_reference);

        if(!$detalhes_pagamento)
        {
            foreach ($shopify_order['note_attributes'] as $notes)
            {
                if(strtolower($notes['name']) == 'payment_additional_order' && strlen($notes['value']) > 0) { $checkout_reference = $notes['value']; }
            }

            $detalhes_pagamento = $pagbrasil->GetPaymentsByID($checkout_reference);
        }
       
        if(!$detalhes_pagamento)
            throw new Exception("Código de referência no pagbrasil, não foi encontrado. Checkout Reference: $checkout_reference");


        return self::PagBrasilPaymentToSankhyaTipoNegociacao($detalhes_pagamento);;
    }
    else if($gateway_name == "mercado_pago")
    {
        $checkout_reference = $shopify_order["checkout_token"];
        $mercadoPagoService = new MercadoPagoAPIService();
        $detalhes_pagamento = $mercadoPagoService->GetPaymentsByExternalReference($checkout_reference);

        if(count($detalhes_pagamento->results) == 0)
            throw new Exception("Código de referência no mercado pago, não foi encontrado. Checkout Reference: $checkout_reference");


        return self::MercadoPagoPaymentToSankhyaTipoNegociacao($detalhes_pagamento->results[0]);;
    }
    else if($gateway_name == "paypal")
    {
        if(!isset($shopify_order['transactions']) || count($shopify_order['transactions']) == 0)
            throw new Exception("Transação inexistente no pedido da shopify. Order Name: " . $shopify_order["name"]);

        $transaction = $shopify_order['transactions'][0];
        $paypalService = new PaypalAPIService();
        $transaction_date = $transaction["receipt"]["PaymentInfo"]["PaymentDate"];
        $authorization_code = $transaction["authorization"];

        $detalhes_pagamento = $paypalService->GetPaymentsByTransactionID($authorization_code, $transaction_date, $transaction_date);

        if($detalhes_pagamento->total_items == 0)
            throw new Exception("Código de transação no paypal, não foi encontrado. Authorization code: $authorization_code");


        return self::PaypalPaymentToSankhyaTipoNegociacao($detalhes_pagamento->transaction_details);;
    }
    else if($gateway_name == "mercado pago checkout pro (via talkblue)")
    {
        return "TALKBLUE - VIA MERCADO PAGO";
    }
    else
        return $gateway_name;

}



public static function GetTipoNegociacao($shopify_order, $gateway_name, $sankhya){


    $texto_tipo_negociacao = self::GetTextTipoPagamento($shopify_order, $gateway_name);
    $cod_tipo_negociacao = 0;
    if($texto_tipo_negociacao)
    {

        if(strtolower($texto_tipo_negociacao) == "depósito ou transferência bancária")
        {
            return 4;
        }
        else if(strtolower($texto_tipo_negociacao) == "fake")
        {
            return 62; 
        }
 
        $tipo_negociacao = $sankhya->db_explorer->execute_query("SELECT TOP 1 * FROM SANKHYA.TGFTPV  WHERE DESCRTIPVENDA LIKE '%$texto_tipo_negociacao%';");

        if(count($tipo_negociacao) > 0)
            $cod_tipo_negociacao = $tipo_negociacao[0]["CODTIPVENDA"] ;
        else
        {
            //grava o tipo de pagamento que não foi encontrado
            logMsg(PHP_EOL . $texto_tipo_negociacao . " [order: " . $shopify_order["order_number"] . "]", "info", "tipos_pagamentos.txt");
            throw new Exception("tipo de pagamento inexistente");
        }
    }
   

    return $cod_tipo_negociacao;
}

    /**
     * Retorna o tipo de operação conforme o tipo de negociação e se é brinde.
     * @param int|string $tipo_negociacao Tipo de negociação
     * @param bool $is_brinde Indica se é brinde (padrão: false)
     * @return string
     */
    public static function GetTipoOperacao($tipo_negociacao, $is_brinde = false) {
        if ($is_brinde) {
            return "1003";
        }
        if ((string)$tipo_negociacao === "4") {
            return "1006";
        }
        return "1000";
    }

} // end class