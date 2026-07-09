<?php

header("Access-Control-Allow-Origin: *");

ini_set('memory_limit', '-1');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'MercadoPago/MercadoPagoAPIService.php';
require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './utils.php';
use \PHPSankhyaAPI\SankhyaAPI;

// get request method
$method = $_SERVER['REQUEST_METHOD'];
$headers = getallheaders();


if ($method == 'GET') {

    $variables = ["date"];
    foreach ($variables as $var) {
        if(!isset($_GET[$var])){
            http_response_code(400);
            print_r(json_encode(["success" => false, "message" => "Parâmetro obrigatório não informado: " . $var ]));
            return;
        }
    }


$config_sankhya  = include "sankhya_config.php";
$sankhya = new SankhyaAPI($config_sankhya["host"]);


        try
        {
            $mercadoPagoHelper = new MercadoPagoAPIService();


            $total_pages = 1;
            $aquirer = 1;
            $page_size = 200;
            $money_release_date = $_GET["date"];
            $conciliatedTransactions = array();
            for($i = 0; $i < $total_pages; $i++)
            {
	            $cash_flow = $mercadoPagoHelper->GetPaymentsByMoneyReleaseDate($_GET["date"], $i);
                    $total_pages = $cash_flow->paging->total;
                    foreach ($cash_flow->results as $transaction)
                    {
                        if(!isset($conciliatedTransactions[$transaction->id]))
                            $conciliatedTransactions[$transaction->id] = $transaction;
                    }

            }


            $file_name =  "extrato_mercado_pago_$money_release_date.ofx";
            $path  = tempnam(sys_get_temp_dir(), 'ofx');

            file_put_contents($path, "OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE");


            $date_start=date_create($money_release_date);
            $date_end=date_create($money_release_date);



            $dados_ofx = ["OFX" => array(
                "SIGNONMSGSRSV1" =>
                array("SONRS"=>
                    array("STATUS" => array("CODE" => 0, "SEVERITY" => "INFO"),
                    "DTSERVER" => date_format(date_create(),"YmdHis") . "[-3:GMT]",
                    "LANGUAGE" => "ENG",
                    "FI" => array("ORG" => "MERCADOPAGO", "FID" => "MERCADOPAGO"),
                    )

                    ),

               "BANKMSGSRSV1"=>
                    array("STMTTRNRS" => array("TRNUID" => 1, "STATUS" => array("CODE" => 0, "SEVERITY" => "INFO"),
                        "STMTRS" => array("CURDEF" => "BRC",
                            "BANKACCTFROM" => array("BANKID" => 336, "ACCTID" => 2076130000304, "ACCTTYPE" => "CHECKING"),
                            "BANKTRANLIST" => array("DTSTART" => date_format($date_start,"YmdHis") . "[-3:GMT]", "DTEND" => date_format($date_end,"YmdHis") . "[-3:GMT]"),
                        )
                    ))
            )];


               $balance = 0;


               foreach ($conciliatedTransactions as $transaction)
               {
                       if ($transaction->operation_type == "regular_payment")
                       {
                           $checkout_id = "";
                           $order_ids = [];

                           if(strlen($transaction->external_reference) == 14)
                           {
                               $checkout_id = $transaction->external_reference;
                           }

                           if(strlen($checkout_id) == 0)
                           {
                               $pattern = "/PED\d+/i";
                               $regex_search_results = [];
                               preg_match_all($pattern, $transaction->description, $regex_search_results);
                               foreach($regex_search_results[0] as $result)
                               {
                                   $order_ids[] = preg_replace('/[^0-9]/', '', $result);
                               }
                           }

                           if(!$checkout_id && count($order_ids) == 0)
                           {

                               echo "Checkout_id ou número do pedido não foi encontrado" + $transaction->id;
                               continue;
                           }


                           $nfes = $sankhya->db_explorer->execute_query("SELECT * FROM TGFCAB AS nfe WHERE  (nfe.AD_ECOMMERCE_CHECKOUTID = '$checkout_id'" . (count($order_ids) > 0 ? "OR nfe.AD_NUMPEDEMCOMMERCE = '" . $order_ids[0] : "") . "') AND TIPMOV = 'P'; ");

                           $numero_transacao_string = $checkout_id ? "Checkout ID $checkout_id " : "";
                           $numero_transacao_string = $numero_transacao_string && count($order_ids) > 0 ? "Pedido(s) Numero: " . join( ', ', $order_ids) : "";

                           $payment_method = $transaction->payment_method->type . " - " .$transaction->payment_method->id;
                           $date_event=date_create($transaction->money_release_date);
                           $netAmount = $transaction->transaction_details->net_received_amount;
                           $netAmountStr = str_replace(".", ",", $netAmount);
                           $balance += $netAmount;

                           $timeZoneName = intval(substr($date_event->getTimezone()->getName(), 3));
                           $memo = date_format($date_event,"d/m/Y") . " - " . $transaction->description  . " - " . ($netAmount > 0 ? "Valor recebido pelo mercado pago, relativo a transacao com $numero_transacao_string" : "Valor Extornado ($payment_method), relativo a transacao com $numero_transacao_string");
                           $stmttrn = ["STMTTRN" => array(
                               "TRNTYPE" => $netAmount < 0 ? "DEBIT" : "OTHER",
                               "DTPOSTED" => date_format( $date_event,"YmdHis") . "[" . $timeZoneName  . ":GMT]",
                               "TRNAMT" => $netAmountStr,
                               "FITID" => $transaction->id,
                               "CHECKNUM" => count($nfes) > 0 ? $nfes[0]["NUNOTA"] : "",
                               "PAYEEID" => 0,
                               "MEMO" => htmlspecialchars($memo, ENT_XML1, 'UTF-8'),
                           )];

                           $dados_ofx["OFX"]["BANKMSGSRSV1"]["STMTTRNRS"]["STMTRS"]["BANKTRANLIST"][] = $stmttrn;
                       }
               }


            $balanceStr = str_replace(".", ",", $balance);
            $dados_ofx["OFX"]["BANKMSGSRSV1"]["STMTTRNRS"]["STMTRS"]["LEDGERBAL"] = array("BALAMT" => $balanceStr, "DTASOF" => date_format(date_create(),"YmdHis"));



            $xml = arrayToXml($dados_ofx);


            $xml = str_replace("<?xml version=\"1.0\" encoding=\"UTF-8\"?>", "", $xml);
            $xml = str_replace("<root>", "", $xml);
            $xml = str_replace("</root>", "", $xml);
            $xml = str_replace("</CODE>", "", $xml);
            $xml = str_replace("</SEVERITY>", "", $xml);
            $xml = str_replace("</DTSERVER>", "", $xml);
            $xml = str_replace("</LANGUAGE>", "", $xml);
            $xml = str_replace("</ORG>", "", $xml);
            $xml = str_replace("</FID>", "", $xml);
            $xml = str_replace("</BANKID>", "", $xml);
            $xml = str_replace("</ACCTID>", "", $xml);
            $xml = str_replace("</ACCTTYPE>", "", $xml);
            $xml = str_replace("</DTSTART>", "", $xml);
            $xml = str_replace("</DTEND>", "", $xml);
            $xml = str_replace("</TRNTYPE>", "", $xml);
            $xml = str_replace("</DTPOSTED>", "", $xml);
            $xml = str_replace("</TRNAMT>", "", $xml);
            $xml = str_replace("</FITID>", "", $xml);
            $xml = str_replace("</CHECKNUM>", "", $xml);
            $xml = str_replace("</PAYEEID>", "", $xml);
            $xml = str_replace("</MEMO>", "", $xml);
            $xml = str_replace("</TRNUID>", "", $xml);
            $xml = str_replace("</CURDEF>", "", $xml);
            $xml = str_replace("</BALAMT>", "", $xml);
            $xml = str_replace("</DTASOF>", "", $xml);


            file_put_contents($path, $xml, FILE_APPEND);

            if (file_exists($path)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=$file_name");
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($path));
                ob_clean();
                flush();
                readfile($path);
                exit;
            }
            die();



        }catch (GuzzleHttp\Exception\ClientException $e){

            $response = $e->getResponse();
            http_response_code($response->getStatusCode());
            $responseBodyAsString = $response->getBody()->getContents();
            print($responseBodyAsString);

        }catch(Exception $ex)
        {
            http_response_code(500);
            print($ex->getMessage());
        }
}



?>


				
		
		
	
	