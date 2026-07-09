<?php




use PHPSankhyaAPI\DBExplorer;
use \PHPSankhyaAPI\SankhyaAPI;
require dirname(__DIR__, 1) . '/Braspag/BraspagAPIService.php';
require dirname(__DIR__, 1) . '/MercadoPago/MercadoPagoAPIService.php';
require dirname(__DIR__, 1) . '/Paypal/PaypalAPIService.php';
require dirname(__DIR__, 1) . '/PagBrasil/PagBrasilAPIService.php';
require 'IntegracaoShopifyHelpers.php';

class IntegracaoSankhyaShopifyController
{
    public static function pedido_exists($num_pedido, $sankhya, $sequencia = 0)
    {

        $pedidos = $sankhya->pedidos->get($expression="AD_NUMPEDEMCOMMERCE=$num_pedido" . ($sequencia ? "AND AD_SEQPEDIDOSITE = $sequencia" : ""), "NUNOTA,CODEMP,CODPARC,DTNEG,STATUSNFE, STATUSNFE, NUNOTA");
      
        if($pedidos["success"] && $pedidos["total"] > 0){
            return true;
        }
        return false;
    }

    public static function verifica_pedido($num_pedido, $sankhya, $sequencia = 0)
    {

        $pedidos = $sankhya->pedidos->get($expression="AD_NUMPEDEMCOMMERCE=$num_pedido" . ($sequencia ? "AND AD_SEQPEDIDOSITE = $sequencia" : ""), "NUNOTA,CODEMP,CODPARC,DTNEG,STATUSNFE, STATUSNOTA, NUMNOTA, TIPMOV, CODTIPOPER, AD_NUNOTAPEDIDO");
       
        if($pedidos["success"] && $pedidos["total"] > 0)
        {
            
            foreach($pedidos["data"] as $pedido)
            {

                if($pedido["CODTIPOPER"] == "1006" ) 
                {
                    //caso o pedido seja do tipo "1006 - PEDIDO DE VENDA - ECOMMERCE FINANCEIRO", não será verificado o status da nota
                    //processamento será feito manualmente pelo financeiro.
                    break;
                }

                //caso o status da NOTA seja "A" (orçamento), marcar como "L" (Liberado) para que o pedido possa ser faturado
                if($pedido["STATUSNOTA"] == 'A')
                {
                    $ret = $sankhya->pedidos->confirmar_pedido($pedido["NUNOTA"], $sankhya->login["jsessionid"]);

                    if(isset($ret["success"]) && $ret["success"] == 0)
                    {
                        logMsg("Erro ao confirmar pedido: " . $ret["message"], 'error', 'verifica_pedido.log');
                    }
                
                }
                else if($pedido["STATUSNOTA"] == 'L' && $pedido["TIPMOV"] == 'P' ) //TIPMOV 'P' é Pedido
                {
                  
                    $enderecos_distribuicao =  $sankhya->db_explorer->execute_query("SELECT c.endereco as ENDERECO_ENTREGA, f.ENDERECO as ENDERECO_FATURAMENTO from dtcdist as d LEFT JOIN VGFDTCCONTATO as c on d.CODPARCDEST = c.CODPARC and c.CODCONTATO = d.CODENDENTREGA LEFT JOIN VGFDTCEND as f on d.CODPARCDEST = f.CODPARC WHERE NUNOTA = " . $pedido["NUNOTA"] . " AND CODPARCDEST = " . $pedido["CODPARC"] . " AND SEQDIST = 1;");

                    if(count($enderecos_distribuicao) > 0)
                    {   
                        $enderecos_distribuicao = $enderecos_distribuicao[0];
                         // Extrai os CEPs dos endereços usando regex
                        $cep_entrega = '';
                        $cep_faturamento = '';
                        if (preg_match('/\b\d{5}-?\d{3}\b/', $enderecos_distribuicao["ENDERECO_ENTREGA"], $match1)) {
                            $cep_entrega = str_replace('-', '', $match1[0]);
                        }
                        if (preg_match('/\b\d{5}-?\d{3}\b/', $enderecos_distribuicao["ENDERECO_FATURAMENTO"], $match2)) {
                            $cep_faturamento = str_replace('-', '', $match2[0]);
                        }
                        // Compara apenas os CEPs
                        if($cep_entrega !== '' && $cep_faturamento !== '' && $cep_entrega === $cep_faturamento)
                        {
                            $ret = $sankhya->pedidos->faturar_pedido($pedido["NUNOTA"], 1100, 4,$sankhya->login["jsessionid"], false);
                            if(isset($ret["success"]) && $ret["success"] == 0)
                            {
                                logMsg("Erro ao faturar pedido: " . $ret["message"] . " <br> Destinatario: " .  " <br> Ret: " . print_r($ret, true), 'error', 'verifica_pedido.log');
                            }else
                            {
                                print_r("<br>Vinculando nota venda ao destinatario. Faturando Pedido: " . $pedido["NUNOTA"]);
                                self::vincular_nota_venda_destinatario($pedido, $sankhya);
                            }


                        }
                    }
                }

                if($pedido["TIPMOV"] == 'P')  
                {   
                    print_r("<br>Vinculando nota venda ao destinatario. Pedido: " . $pedido["NUNOTA"]);
                    self::vincular_nota_venda_destinatario($pedido, $sankhya);
                }
            }
        }
    }

    public static function vincular_nota_venda_destinatario($pedido, $sankhya, $num_nota_venda = 0)
    {
        $query = "SELECT NUNOTAVEN, CODPARCDEST, SEQDIST, CODENDENTREGA FROM DTCDIST WHERE NUNOTA = " . $pedido["NUNOTA"] . " AND CODPARCDEST = " . $pedido["CODPARC"] . " AND SEQDIST = 1;";
        $destinatarios =  $sankhya->db_explorer->execute_query($query);

        if(count($destinatarios) > 0)
        {

            $num_nota_venda_atual = $destinatarios[0]["NUNOTAVEN"];
            if($num_nota_venda_atual <= 0)
            {
                if($num_nota_venda <= 0)
                {
                    $tgfVar = $sankhya->db_explorer->execute_query("SELECT top 1 NUNOTA FROM TGFVAR VAR  WHERE VAR.NUNOTAORIG = " . $pedido["NUNOTA"] . " AND SEQUENCIA = 1; ");
                    $num_nota_venda = count($tgfVar) > 0 ? $tgfVar[0]["NUNOTA"] : "";
                }

                if($num_nota_venda > 0)
                {
                    try
                    {
                        $destinatario = array("NUNOTAVEN" => $num_nota_venda);
                        $pk = array("NUNOTA" => $pedido["NUNOTA"], "CODPARCDEST" => $pedido["CODPARC"], "SEQDIST" => 1);
                        $sankhya->destinatarios->update($pk, $destinatario);
                    }
                    catch(Exception $ex)
                    {
                        logMsg("Erro ao atualizar NUNOTAVEN em DTCDIST. Pedido: " . $pedido["NUNOTA"] . " Nota venda: " . $num_nota_venda . " Erro: " . $ex->getMessage(), 'error', 'verifica_pedido.log');
                    }
                }
            }
        }
    }



    public static function insert_pedido($order, $sankhya, $shopify = null)
    {
        $client_id = IntegracaoSankhyaShopifyController::save_client($order, $sankhya, $shopify);

        if($client_id > 0) 
        {
            $contato_entrega = self::save_contato($order, $sankhya, $client_id); //salva o contato do cliente (endereço de entrega)
        }

        $itens_pedido = [];
        $kits = [];
        $data_previsao_entrega = new DateTime($order['created_at']);
        $data_previsao_entrega->modify('+2 days');

        while(!isBusinessDay($data_previsao_entrega)){
            $data_previsao_entrega->modify('+1 days');
        }
        $data_previsao_entrega = $data_previsao_entrega->format("d/m/Y");


        $authorization_code = $order["authorization_code"];
        $gateway_payment_id = $order["gateway_payment_id"];

        foreach($order["line_items"] as $order_item)
        {
            $sku = $order_item["sku"];

            //verifica se o produto contém um SKU diferente do Sankhya, que já era utilizado antes no e-commerce
            $products = $sankhya->produtos->get($expression="AD_SKUECOMMERCE= $sku", "CODPROD, AD_SKUECOMMERCE, DESCRPROD, CODVOL, LOCAL,MARCA, TIPOKIT, AD_PRECO_KIT");

            if($products["success"] && $products["total"] > 0){
                $sku = $products["data"][0]["CODPROD"];
            }
            else
            {
                $products = $sankhya->produtos->get($expression="CODPROD= $sku", "CODPROD, AD_SKUECOMMERCE, DESCRPROD, CODVOL, LOCAL,MARCA, TIPOKIT, AD_PRECO_KIT");
                if($products["success"] && $products["total"] == 0)
                    throw new Exception("Produto Inexistente, por favor verifique: SKU: $sku");
            }

            $cod_volume = $products["data"][0]["CODVOL"];
        
            $is_tipo_kit = strtoupper($products["data"][0]["TIPOKIT"]) == 'D' || strtoupper($products["data"][0]["TIPOKIT"]) == 'P';

            if($is_tipo_kit) //caso o item for um kit, será incluido separadamente
            {
                $preco_kit = (double)$products["data"][0]["AD_PRECO_KIT"];
                $desconto = (double)($preco_kit - (double)$order_item["price"]) * $order_item["quantity"];
                $preco = $preco_kit;

                $kits[] = IntegracaoShopifyHelpers::create_item_pedido($sku,  $order_item["quantity"], $cod_volume, $preco, $desconto);
            }
            else
            {
                $preco_produto = $sankhya->db_explorer->execute_query("SELECT TOP 1  * FROM tgfexc WHERE codprod = $sku ORDER BY NUTAB DESC");
                $preco = count($preco_produto) > 0 && $preco_produto[0]["VLRVENDA"] != "" ? $preco_produto[0]["VLRVENDA"] : 0;

                $desconto =  ((double)$preco - (double)$order_item["price"]) * $order_item["quantity"]; //calcula o valor do desconto pela diferença
                $itens_pedido[] = IntegracaoShopifyHelpers::create_item_pedido($sku,  $order_item["quantity"], $cod_volume, $order_item["price"], $desconto);
            }
        }


        $data_pedido = date_format(date_create($order['created_at']),"d/m/Y");
        $gateway_name = $order["gateway"];
        $cod_tipo_negociacao = IntegracaoShopifyHelpers::GetTipoNegociacao($order, $gateway_name, $sankhya);
        $ordem_number = $order["order_number"];
        $checkout_id = IntegracaoShopifyHelpers::GetCheckoutID($order);





        $num_sequencia = 0;

        //Inserindo os itens do pedido que são (avulsos), diferente de kits
      if(count($itens_pedido) > 0)
      {

          $tipo_operacao = IntegracaoShopifyHelpers::GetTipoOperacao($cod_tipo_negociacao);
          $num_sequencia++;

          if(!self::pedido_exists($ordem_number,  $sankhya, $num_sequencia))//insere caso o pedido não exista
          {
              $pedido = IntegracaoShopifyHelpers::create_pedido($client_id, $data_pedido, $tipo_operacao, $cod_tipo_negociacao, $ordem_number, $authorization_code, $gateway_payment_id, $checkout_id, $itens_pedido, $num_sequencia, $data_previsao_entrega);
              logMsg("Pedido: " . print_r($pedido, true), 'info', 'pedido.log');
              $ret = $sankhya->pedidos->insert_pedido($pedido, $sankhya->login["jsessionid"]);
            
              if(isset($ret["success"]) && $ret["success"] == 0){
                throw new Exception($ret["message"]);
              }
         
            //sleep(5); //aguarda 5 segundos para evitar problemas de concorrência com o Sankhya
            $pedido_id = $ret["data"]["pk"]["NUNOTA"]["$"];
            //atualiza o pedido com o código do cliente, existe um bug no sankhya que não insere o código do cliente correto no pedido
            //$ret = $sankhya->pedidos->update_pedido($pedido_id, ["CODPARC" => $client_id], $sankhya->login["js
            // essionid"]);

            $cod_contato = $contato_entrega["CODCONTATO"];
            $destinatario = IntegracaoShopifyHelpers::create_destinatario($pedido_id, $client_id, $ordem_number,
            $cod_contato, 1, 1, 
            "V", "");
    
            $ret = $sankhya->destinatarios->update(array("NUNOTA" => $pedido_id, 
            "CODPARCDEST" => $client_id,
            "CODENDENTREGA" => $cod_contato), $destinatario);

          }

      }

      $is_brinde_inserido = false;

      foreach ($kits as $kit)  //insere cada kit em um pedido separado
      {

          $num_sequencia++;
          $itens_pedido_kit = null;
          $itens_pedido_kit[] = $kit;
          $tipo_operacao = IntegracaoShopifyHelpers::GetTipoOperacao($cod_tipo_negociacao);
          $num_sequencia++;

          if(self::pedido_exists($ordem_number,  $sankhya, $num_sequencia))//caso o pedido já exista, pula a inserção
              continue;

          $sku = $kit["CODPROD"]["$"];
          $products = $sankhya->produtos->get($expression="CODPROD= $sku", "CODPROD, AD_SKUECOMMERCE, DESCRPROD, CODVOL, LOCAL,MARCA, TIPOKIT");

          if($products["success"] && $products["total"] == 0)
              throw new Exception("Produto, Kit ou Brinde Inexistente, por favor verifique: SKU: $sku");

          $descricao_cesta = $products["data"][0]["DESCRPROD"];
          $total_desconto = 0.00;
          $valor_cesta = 0.00;
          $qtde_cestas = $kit["QTDNEG"]["$"];
          foreach($itens_pedido_kit as $item_kit)
          {
              $total_desconto += (double)$item_kit["VLRDESC"]["$"];
              $valor_cesta += (double)$item_kit["VLRUNIT"]["$"];
          }
          //$valor_cesta_com_desconto = $valor_cesta - $total_desconto;
          $extra_fields =  ["QTDCESTA" =>  ["$"=> $qtde_cestas],
                            "NOMECESTA" =>  ["$"=> trim(mb_strimwidth($descricao_cesta,0,20, ""))],
                            "CODTIPCESTA" =>  ["$"=> (preg_match('/NATAL/i', $descricao_cesta) ? '2' : "1")],
                            "TIPENTCESTA" =>  ["$"=> "EN"],
                            "AD_VLRDESCECOM" => ["$"=> $total_desconto] , //Valor do desconto da cesta
                            //"DTCPERCDESC" => ["$"=> 10.00] , //Valor do desconto da cesta
                            //"VLRCESTA" => ["$"=> $valor_cesta_com_desconto],
                            "OBSERVACAO" => ["$"=> "" ],
                            //"DTCVLRFRETE" => ["$"=> 1.01 ] //Valor do frete da cesta, que é zero

                            ];

          $pedido = IntegracaoShopifyHelpers::create_pedido($client_id, $data_pedido, $tipo_operacao, $cod_tipo_negociacao, $ordem_number, $authorization_code, $gateway_payment_id, $checkout_id, $itens_pedido_kit, $num_sequencia, $data_previsao_entrega, $extra_fields);
          logMsg("Pedido: " . print_r($pedido, true), 'info', 'pedido.log');
          $ret = $sankhya->pedidos->insert_pedido($pedido, $sankhya->login["jsessionid"]);
    
     
                            

          if(isset($ret["success"]) && $ret["success"] == 0){
              throw new Exception($ret["message"] . "\n Pedido:" . print_r($pedido, true));
          }

       // sleep(5); //aguarda 5 segundos para evitar problemas de concorrência com o Sankhya
        $pedido_id = $ret["data"]["pk"]["NUNOTA"]["$"];
        //atualiza o pedido com o código do cliente, existe um bug no sankhya que não insere o código do cliente correto no pedido
        //$ret = $sankhya->pedidos->update_pedido($pedido_id, ["CODPARC" => $client_id], $sankhya->login["jsessionid"]);

 
        $cod_contato = $contato_entrega["CODCONTATO"];
        $destinatario = IntegracaoShopifyHelpers::create_destinatario($pedido_id, $client_id, $ordem_number,
        $cod_contato, 1, 1, 
        "V", "", ["QTD" => $qtde_cestas]);

        $ret = $sankhya->destinatarios->update(array("NUNOTA" => $pedido_id, 
        "CODPARCDEST" => $client_id,
        "CODENDENTREGA" => $cod_contato), $destinatario);
      }

        if(count($kits) > 0)   //inserindo o brinde em caso pedido contenha kits
        {
            $brinde_vigente = $sankhya->db_explorer->execute_query("SELECT top 1 * FROM AD_BRINDEECOMM where DTINI <= GETDATE() and DTFIM >= GETDATE() order by CODIGO desc ;");
            if(count($brinde_vigente) > 0){
                $sku_brinde = $brinde_vigente[0]["CODPROD"];
                $itens_brinde[] = IntegracaoShopifyHelpers::create_item_pedido($sku_brinde, 1, 'UN',0.00);
                $itens_brinde[] = IntegracaoShopifyHelpers::create_item_pedido($sku_brinde, 1, 'UN',0.00);
                $tipo_operacao = IntegracaoShopifyHelpers::GetTipoOperacao($cod_tipo_negociacao, true);
                $num_sequencia++;
                if(!self::pedido_exists($ordem_number,  $sankhya, $num_sequencia))//insere caso o pedido não exista
                {
                    $pedido = IntegracaoShopifyHelpers::create_pedido($client_id, $data_pedido, $tipo_operacao, $cod_tipo_negociacao, $ordem_number, $authorization_code, $gateway_payment_id, $checkout_id, $itens_brinde, $num_sequencia, $data_previsao_entrega);
                    logMsg("Pedido: " . print_r($pedido, true), 'info', 'pedido.log');
                    $ret = $sankhya->pedidos->insert_pedido($pedido, $sankhya->login["jsessionid"]);

                    if (isset($ret["success"]) && $ret["success"] == 0){
                        throw new Exception($ret["message"]);
                    }

                   // sleep(5); //aguarda 5 segundos para evitar problemas de concorrência com o Sankhya
                    $pedido_id = $ret["data"]["pk"]["NUNOTA"]["$"];
                    //atualiza o pedido com o código do cliente, existe um bug no sankhya que não insere o código do cliente correto no pedido
                    //$ret = $sankhya->pedidos->update_pedido($pedido_id, ["CODPARC" => $client_id], $sankhya->login["jsessionid"]);


                    $cod_contato = $contato_entrega["CODCONTATO"];
                    $destinatario = IntegracaoShopifyHelpers::create_destinatario($pedido_id, $client_id, $ordem_number,
                    $cod_contato, 1, 3, 
                    "V", "");
            
                    $ret = $sankhya->destinatarios->update(array("NUNOTA" => $pedido_id, 
                    "CODPARCDEST" => $client_id,
                    "CODENDENTREGA" => $cod_contato), $destinatario);

                }
            }
        }


        return true;
    }



   

    public static function insert_pedido_avulso($order, $sku, $client_id, $sankhya)
    {

        $products = $sankhya->produtos->get($expression="CODPROD= $sku", "CODPROD, AD_SKUECOMMERCE, DESCRPROD, CODVOL, LOCAL,MARCA");
        if($products["success"] && $products["total"] == 0)
            throw new Exception("Produto ou Brinde Inexistente, por favor verifique: SKU: $sku");

        $produto = $products["data"][0];

        $item_pedido[] = array(
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

        $itens_pedido = array("INFORMARPRECO" => "True", "item" => $item_pedido);
        $data_pedido = date_format(date_create($order['created_at']),"d/m/Y");

        $tipo_operacao = "1003";

        $pedido = array("cabecalho" => array(
            "NUNOTA" =>  ["$"=> ""],
            "CODPARC" => ["$"=> $client_id],
            "DTNEG" => ["$"=> $data_pedido],
            "CODTIPOPER" => ["$"=> $tipo_operacao],
            "CODTIPVENDA" => ["$"=>  1],
            "CODNAT" => ["$"=>  "1010300"],
            "CODVEND" => ["$"=> "2"],
            "CODEMP" => ["$"=> "1"],
            "TIPMOV" => ["$"=> "P"],
            "NUMPEDIDO2" => ["$"=> $order["order_number"]],
            "AD_NUMPEDEMCOMMERCE" => ["$"=> $order["order_number"]],
            "AD_ECOMMERCE_CHECKOUTID" => ["$"=> IntegracaoShopifyHelpers::GetCheckoutID($order)],

        ),  "itens" =>  $itens_pedido
        );

        $ret = $sankhya->pedidos->insert_pedido($pedido, $sankhya->login["jsessionid"]);
        $descricao_produto = strtoupper($products["data"][0]["DESCRPROD"]);

        if(isset($ret["success"]) && $ret["success"] == false){
            throw new Exception("Erro ao inserir pedido avulso: " . $ret["message"]);
        }

        //sleep(5); //aguarda 5 segundos para evitar problemas de concorrência com o Sankhya
        $pedido_id = $ret["data"]["pk"]["NUNOTA"]["$"];
        //atualiza o pedido com o código do cliente, existe um bug no sankhya que não insere o código do cliente correto no pedido
        //$ret = $sankhya->pedidos->update_pedido($pedido_id, ["CODPARC" => $client_id], $sankhya->login["jsessionid"]);

    }

    public static function save_client($order, $sankhya, $shopify = null)
    {

        $nome_razao_social = trim($order['customer']['first_name']) . " " . trim($order['customer']['last_name']);
        $cpf_cnpj = "";
        $observacao_entrega = "";
        $instituicao_doacao = "";
        $tipo_pagamento = "";
        $numero_endereco_faturamento = "";
        $logradouro_endereco_faturamento = "";
        $bairro_endereco_faturamento = "";
        $cidade_endereco_faturamento = "";
        $complemento_endereco_faturamento = "";

        foreach ($order['note_attributes'] as $notes)
        {
            if ($notes['name'] == 'aditional_info_extra_billing_cpfcnpj') {$cpf_cnpj = preg_replace('/[^0-9]/', '', $notes['value']); }
            if(strtolower($notes['name']) == 'observacao_entrega' && strlen($notes['value']) > 0) { $observacao_entrega = $notes['value']; }
            if(strtolower($notes['name']) == 'instituicao_doacao' && strlen($notes['value']) > 0) { $instituicao_doacao = $notes['value']; }
            if(strtolower($notes['name']) == 'shipping_payment_type' && strlen($notes['value']) > 0) { $tipo_pagamento = $notes['value']; }
            if(strtolower($notes['name']) == 'billing_additional_street_number' && strlen($notes['value']) > 0) { $numero_endereco_faturamento = preg_replace('/[^0-9]/', '',$notes['value']); }
            if(strtolower($notes['name']) == 'billing_additional_street_number_supplement' && strlen($notes['value']) > 0) { $complemento_endereco_faturamento = $notes['value']; }
        }

        $cpf_cnpj = IntegracaoShopifyHelpers::get_cpf_from_shopify_address($order['billing_address']); 

        if(!isValidCpfCnpj($cpf_cnpj))
        {
            $cpf_cnpj = IntegracaoShopifyHelpers::get_cpf_from_shopify_order($order);
        }

        if(!isValidCpfCnpj($cpf_cnpj))
        {
            $cpf_cnpj = IntegracaoShopifyHelpers::get_cpf_from_graphql($shopify, $order["id"] ?? 0);
        }

        if(!isValidCpfCnpj($cpf_cnpj))
        {
            throw new Exception("CPF/CNPJ Inválido: " . $cpf_cnpj);
        }



        $numero_endereco_faturamento =  IntegracaoShopifyHelpers::get_numero_endereco_from_shopify_address($order['billing_address']);
        $complemento_endereco_faturamento = IntegracaoShopifyHelpers::get_complemento_endereco_from_shopify_address($order['billing_address']);


        $tipo_pessoa = strlen($cpf_cnpj) > 11 ? "J" : "F";

        $email = trim($order['email']);
        $telefone = IntegracaoShopifyHelpers::get_telefone_from_shopify_address($order['billing_address']);
        $cep = IntegracaoShopifyHelpers::get_cep_from_shopify_address($order['billing_address']);
        $latitude = $order['billing_address']['latitude'] ?? "";
        $longitude = $order['billing_address']['longitude'] ?? "";


        $clientes = $sankhya->clientes->get("this.CGC_CPF = '$cpf_cnpj' AND CLIENTE = 'S'", "CODPARC,NOMEPARC,FORNECEDOR,CLIENTE,CODCID,CLIENTE,CLASSIFICMS, ATIVO");

        $cod_cliente = $clientes["success"] && $clientes["total"] > 0 ? $clientes["data"][0]["CODPARC"] : 0;


        $params = array(
                "TIPPESSOA" => ["$"=> $tipo_pessoa],
                "NOMEPARC" => ["$"=> mb_strimwidth($nome_razao_social,0,40, "")],
                "RAZAOSOCIAL" => ["$"=> mb_strimwidth($nome_razao_social,0,40, "")],
                "NUMEND" => ["$"=> mb_strimwidth($numero_endereco_faturamento,0,6, "")],
                "COMPLEMENTO" => ["$"=>  mb_strimwidth($complemento_endereco_faturamento,0,30, "")],
                "CGC_CPF" => ["$"=> $cpf_cnpj],
                "TELEFONE" => ["$"=> $telefone],
                "FAX" => ["$"=> ""],
                "EMAIL" => ["$"=> $email],
                "CEP" => ["$"=> $cep],
                "ATIVO" => ["$"=> "S"],
                "CLIENTE" => ["$"=> "S"],
                "CLASSIFICMS" => ["$"=> "C"],
                "LATITUDE" => ["$"=> "$latitude"],
                "LONGITUDE" => ["$"=> "$longitude"],
            );

            if($tipo_pessoa == "J")
            {
                $params["CLASSIFICMS"] = ["$"=> "C"];
            }

            $endereco_viacep = buscarEnderecoPorCep($cep);
        
            if($endereco_viacep)
            {
              
                $codigo_ibge = $endereco_viacep["ibge"] ?? "";
                $codigo_siaf = $endereco_viacep["siafi"] ?? "";
                $cidade_endereco_faturamento = IntegracaoShopifyHelpers::get_cidade_from_viacep($endereco_viacep);
                $cidades = $sankhya->cidades->get("CODMUNFIS = '$codigo_ibge' OR CODMUNSIAFI = '$codigo_siaf' OR NOMECID LIKE '" . str_replace("'", "''", $cidade_endereco_faturamento) . "'", "CODCID,NOMECID,UF, CODREG");
            }
            else
            {
                $cidade_endereco_faturamento = IntegracaoShopifyHelpers::get_cidade_from_shopify_address($order['billing_address']);
                $cidades = $sankhya->cidades->get("NOMECID LIKE '" . str_replace("'", "''", $cidade_endereco_faturamento) . "'", "CODCID,NOMECID,UF, CODREG");
            }

            $cod_cidade = isset($cidades["total"]) && $cidades["total"] > 0 ? $cidades["data"][0]["CODCID"] : 0;
         

            if($cod_cidade > 0)
                $params["CODCID"] = ["$"=> "$cod_cidade"];
            else
                throw new Exception("Cidade inválida ou não cadastrada, Valor: $cidade_endereco_faturamento");


            if($endereco_viacep)
            {
                $params_bairro = IntegracaoShopifyHelpers::get_bairro_from_viacep($endereco_viacep, $cidades["data"][0]["CODREG"]);
            }
            else
            {
                $params_bairro = IntegracaoShopifyHelpers::get_bairro_from_shopify_address($order['billing_address'], $cidades["data"][0]["CODREG"]);  
            }

            $bairro = $params_bairro['NOMEBAI']["$"];

            if($bairro == ""){
                $bairro = "Não Informado";
            }

            $bairros = $sankhya->bairros->get("NOMEBAI like '" . str_replace("'", "''", $bairro) . "'", "NOMEBAI, CODBAI");

            $cod_bairro = isset($bairros["total"]) && $bairros["total"] > 0 ? $bairros["data"][0]["CODBAI"] : 0;

            if($cod_bairro <= 0 && $cod_cidade > 0)
            { 
                $bairros = $sankhya->bairros->post($params_bairro, "NOMEBAI, CODBAI");
                $cod_bairro = isset($bairros["total"]) && $bairros["total"]  > 0 ? $bairros["data"][0]["CODBAI"] : 0;
            }


            if($cod_bairro > 0)
                $params["CODBAI"] = ["$"=> "$cod_bairro"];
            else
                throw new Exception("Bairro inválido ou não cadastrado, Valor: $bairro");



            if ($endereco_viacep){
                $params_endereco = IntegracaoShopifyHelpers::get_endereco_from_viacep($endereco_viacep);
                if($params_endereco['NOMEEND']["$"] == ""){
                    $params_endereco = IntegracaoShopifyHelpers::get_endereco_from_shopify_address($order['billing_address']);
                }

            }
            else
            {
                $params_endereco = IntegracaoShopifyHelpers::get_endereco_from_shopify_address($order['billing_address']);
            }



            $logradouro_endereco_faturamento = $params_endereco['NOMEEND']["$"];

            if(!$logradouro_endereco_faturamento)
            {
                $logradouro_endereco_faturamento = "Não Informado";
            }

            $tipo_endereco = $params_endereco['TIPO']["$"];

            $tipos = $sankhya->db_explorer->execute_query("SELECT TOP 1 * from TSITEND where TIPO LIKE '$tipo_endereco' OR DESCRICAO LIKE '$tipo_endereco'; ");
            $tipo_endereco = count($tipos) > 0 ? $tipos[0]["TIPO"] : "";
            
            $params_endereco['TIPO']["$"] = $tipo_endereco == "" ? "R" : $tipo_endereco;


            $enderecos = $sankhya->enderecos->get("TIPO = '$tipo_endereco' AND NOMEEND like '" . str_replace("'", "''", $logradouro_endereco_faturamento) . "'", "NOMEEND, CODEND, TIPO");
            $cod_endereco = isset($enderecos["total"]) && $enderecos["total"] > 0 ? $enderecos["data"][0]["CODEND"] : 0;
            if($cod_endereco <= 0)
            {
                //inserindo endereço no sankhya
                $enderecos = $sankhya->enderecos->post($params_endereco, "NOMEEND, CODEND, TIPO");

                if(isset($enderecos["success"]) && $enderecos["success"] == false)
                    throw new Exception($enderecos["message"]);

                $cod_endereco = count($enderecos["data"]) > 0 ? $enderecos["data"][0]["CODEND"] : 0;
            }


            if($cod_endereco > 0)
                $params["CODEND"] = ["$"=> "$cod_endereco"];

         
            if($cod_cliente > 0)
            {
                try
                {
                    $pk = array("CODPARC" => $cod_cliente, "CGC_CPF" => $cpf_cnpj);
                    $clientes = $sankhya->clientes->update($pk, $params);
                }catch(Exception $e)
                {
                    logMsg("Erro ao atualizar cliente: " . $e->getMessage() . " Params: " . print_r($params, true), 'error', 'parceiro.log');
                }
                return $cod_cliente;
            }
            else
            {
                $clientes = $sankhya->clientes->post($params, "CODPARC,NOMEPARC,FORNECEDOR,CLIENTE,CODCID,CLIENTE,CLASSIFICMS, ATIVO");
            }


            if(isset($clientes["success"]) && $clientes["success"] == false)
                throw new Exception($clientes["message"] . "Params: " . print_r($params, true));

            $clientes = $sankhya->clientes->get("this.CGC_CPF = '$cpf_cnpj' AND CLIENTE = 'S'", "CODPARC,NOMEPARC,FORNECEDOR,CLIENTE,CODCID,CLIENTE,CLASSIFICMS,");

            $cod_cliente = $clientes["total"] > 0 ? $clientes["data"][0]["CODPARC"] : 0;


            return $cod_cliente;
        
    }


    private static function save_destinatarios_entrega ($order, $sankhya, $cod_cliente)
    {


    }

    private static function save_contato($order, $sankhya, $cod_cliente)
    {       
       
                $nome_razao_social = $order['shipping_address']['name']  ?? "";
                $complemento_endereco = IntegracaoShopifyHelpers::get_complemento_endereco_from_shopify_address($order['shipping_address']);
                $numero_endereco = IntegracaoShopifyHelpers::get_numero_endereco_from_shopify_address($order['shipping_address']);
                $cpf_cnpj = IntegracaoShopifyHelpers::get_cpf_from_shopify_address($order['shipping_address']);
                $tipo_pessoa = strlen($cpf_cnpj) > 11 ? "J" : "F";
                $telefone = IntegracaoShopifyHelpers::get_telefone_from_shopify_address($order['shipping_address']);
                $email = trim($order['email']);
                $cep = IntegracaoShopifyHelpers::get_cep_from_shopify_address($order['shipping_address']);
                $latitude = $order['shipping_address']['latitude'] ?? "";
                $longitude = $order['shipping_address']['longitude'] ?? "";

                $endereco_viacep = buscarEnderecoPorCep($cep);

                if($endereco_viacep)
                {
                
                    $codigo_ibge = $endereco_viacep["ibge"] ?? "";
                    $codigo_siaf = $endereco_viacep["siafi"] ?? "";
                    $cidade_endereco_faturamento = IntegracaoShopifyHelpers::get_cidade_from_viacep($endereco_viacep);
                    $cidades = $sankhya->cidades->get("CODMUNFIS = '$codigo_ibge' OR CODMUNSIAFI = '$codigo_siaf' OR NOMECID LIKE '" . str_replace("'", "''", $cidade_endereco_faturamento) . "'", "CODCID,NOMECID,UF, CODREG");
                }
                else
                {
                    $cidade_endereco_faturamento = IntegracaoShopifyHelpers::get_cidade_from_shopify_address($order['shipping_address']);
                    $cidades = $sankhya->cidades->get("NOMECID LIKE '" . str_replace("'", "''", $cidade_endereco_faturamento) . "'", "CODCID,NOMECID,UF, CODREG");
                }

                $cod_cidade = isset($cidades["total"]) && $cidades["total"] > 0 ? $cidades["data"][0]["CODCID"] : 0;
                $cod_regiao = isset($cidades["total"]) && $cidades["total"] > 0 ? $cidades["data"][0]["CODREG"] : 0;

                if($cod_cidade <= 0)
                    throw new Exception("Cidade inválida ou não cadastrada, Valor: $cidade_endereco_faturamento");


                if($endereco_viacep)
                {
                    $params_bairro = IntegracaoShopifyHelpers::get_bairro_from_viacep($endereco_viacep, $cidades["data"][0]["CODREG"]);
                    if($params_bairro['NOMEBAI']["$"] == "")
                    {
                         $params_bairro = IntegracaoShopifyHelpers::get_bairro_from_shopify_address($order['shipping_address'], $cidades["data"][0]["CODREG"]);  
                    }
                }
                else
                {
                    $params_bairro = IntegracaoShopifyHelpers::get_bairro_from_shopify_address($order['shipping_address'], $cidades["data"][0]["CODREG"]);  
                }

                $bairro = $params_bairro['NOMEBAI']["$"];
                if($bairro == ""){
                    $bairro = "Não Informado";
                }
               
                $bairros = $sankhya->bairros->get("NOMEBAI like '" . str_replace("'", "''", $bairro) . "'", "NOMEBAI, CODBAI");

                $cod_bairro = isset($bairros["total"]) && $bairros["total"] > 0 ? $bairros["data"][0]["CODBAI"] : 0;

                if($cod_bairro <= 0 && $cod_cidade > 0)
                { 
                    $bairros = $sankhya->bairros->post($params_bairro, "NOMEBAI, CODBAI");
                    $cod_bairro = isset($bairros["total"]) && $bairros["total"]  > 0 ? $bairros["data"][0]["CODBAI"] : 0;
                }


                if($cod_bairro > 0)
                    $params["CODBAI"] = ["$"=> "$cod_bairro"];
                else
                    throw new Exception("Bairro inválido ou não cadastrado, Valor: $bairro");


                if ($endereco_viacep){
                    $params_endereco = IntegracaoShopifyHelpers::get_endereco_from_viacep($endereco_viacep);
                    if($params_endereco['NOMEEND']["$"] == ""){
                        $params_endereco = IntegracaoShopifyHelpers::get_endereco_from_shopify_address($order['shipping_address']);
                    }

                }
                else
                {
                    $params_endereco = IntegracaoShopifyHelpers::get_endereco_from_shopify_address($order['shipping_address']);
                }



                
   
                $logradouro_endereco_faturamento = $params_endereco['NOMEEND']["$"];

                if(!$logradouro_endereco_faturamento)
                {
                    $logradouro_endereco_faturamento = "Não Informado";
                }

                $tipo_endereco = $params_endereco['TIPO']["$"];

                $tipos = $sankhya->db_explorer->execute_query("SELECT TOP 1 * from TSITEND where TIPO LIKE '$tipo_endereco' OR DESCRICAO LIKE '$tipo_endereco'; ");
                $tipo_endereco = count($tipos) > 0 ? $tipos[0]["TIPO"] : "";
                $params_endereco['TIPO']["$"] = $tipo_endereco == "" ? "R" : $tipo_endereco;

                $enderecos = $sankhya->enderecos->get("TIPO = '$tipo_endereco' AND NOMEEND like '" . str_replace("'", "''", $logradouro_endereco_faturamento) . "'", "NOMEEND, CODEND, TIPO");

                $cod_endereco = isset($enderecos["total"]) && $enderecos["total"] > 0 ? $enderecos["data"][0]["CODEND"] : 0;

                if($cod_endereco <= 0)
                {
                    //inserindo endereço no sankhya
                    $enderecos = $sankhya->enderecos->post($params_endereco, "NOMEEND, CODEND, TIPO");

                    if(isset($enderecos["success"]) && $enderecos["success"] == false)
                        throw new Exception($enderecos["message"]);

                    $cod_endereco = count($enderecos["data"]) > 0 ? $enderecos["data"][0]["CODEND"] : 0;
                }

                //insere o endereço de entrega do cliente
                $params = array(
                    "TIPPESSOA" => ["$"=> $tipo_pessoa],
                    "NOMECONTATO" => ["$"=> mb_strimwidth($nome_razao_social,0,40, "")],
                    "NUMEND" => ["$"=> mb_strimwidth($numero_endereco,0,6, "")],
                    "COMPLEMENTO" => ["$"=>  mb_strimwidth($complemento_endereco,0,30, "")],
                    "TELEFONE" => ["$"=> $telefone],
                    "CELULAR" => ["$"=> $telefone],
                    "EMAIL" => ["$"=> $email],
                    "CEP" => ["$"=> $cep],
                    "ATIVO" => ["$"=> "S"],
                    "CODCID" => ["$"=> "$cod_cidade"],
                    "CODBAI" => ["$"=> "$cod_bairro"],
                    "CODEND" => ["$"=> "$cod_endereco"],
                    "CODREG" => ["$"=> "$cod_regiao"],
                    "CODPARC" => ["$"=> "$cod_cliente"],
                    "LATITUDE" => ["$"=> "$latitude"],
                    "LONGITUDE" => ["$"=> "$longitude"],
                    "DTCENDENTREGA" => ["$"=> "S"],
                );

                if($cpf_cnpj)
                {
                    if($tipo_pessoa == "J")
                    {
                        $params["CNPJ"] = ["$"=> $cpf_cnpj];
                    }
                    else
                    {
                        $params["CPF"] = ["$"=> $cpf_cnpj];
                    }
                }



                $contato = $sankhya->contatos->get("this.CODPARC = '$cod_cliente' AND NOMECONTATO LIKE '%$nome_razao_social%' AND CEP = '$cep' ", "CODPARC, CODCONTATO");
                $cod_contato = 0;

                if($contato["success"] && $contato["total"] > 0)
                {
                    $cod_contato = $contato["data"][0]["CODCONTATO"];
                }

                $pk = array("CODPARC" => "$cod_cliente", "CODCONTATO" => null);
                
                if($cod_contato > 0)
                {
                    $pk["CODCONTATO"] = "$cod_contato";
                }
               
                $ret = $sankhya->contatos->update($pk, $params);
                $data = $ret["data"][0];
                return $data;
    }

   


   



}