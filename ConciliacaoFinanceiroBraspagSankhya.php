<?php

header("Access-Control-Allow-Origin: *");

ini_set('memory_limit', '-1');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'Braspag/BraspagAgilizaCashflowAPIService.php';
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


$config_payments = include "payments_config.php";
$config_sankhya = include "sankhya_config.php";
$sankhya = new SankhyaAPI($config_sankhya["host"]);

        try
        {
            $braspagHelper = new BraspagAgilizaCashflowAPIService($config_payments['braspag_agiliza']['merchant_id']);
            $total_pages = 1;
            $aquirer = 1;
            $page_size = 200;
            $date_file = $_GET["date"];
            $conciliatedTransactions = array();
            for($i = 1; $i <= $total_pages; $i++)
            {
                $cashFlow = $braspagHelper->GetCashFlow($date_file, $aquirer, $i, $page_size);
                $total_pages = $cashFlow->totalPages;
                foreach ($cashFlow->conciliatedTransactions as $transaction)
                    $conciliatedTransactions[] = $transaction;

            }

            $file_name =  "extrato_braspag_$date_file.ofx";
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


            $date_start=date_create($date_file);
            $date_end=date_create($date_file);



            $dados_ofx = ["OFX" => array(
                "SIGNONMSGSRSV1" =>
                array("SONRS"=>
                    array("STATUS" => array("CODE" => 0, "SEVERITY" => "INFO"),
                    "DTSERVER" => date_format(date_create(),"YmdHis") . "[-3:GMT]",
                    "LANGUAGE" => "ENG",
                    "FI" => array("ORG" => "BRASPAG", "FID" => "BRASPAG"),
                    )

                    ),

               "BANKMSGSRSV1"=>
                    array("STMTTRNRS" => array("TRNUID" => 1, "STATUS" => array("CODE" => 0, "SEVERITY" => "INFO"),
                        "STMTRS" => array("CURDEF" => "BRC",
                            "BANKACCTFROM" => array("BANKID" => 033, "ACCTID" => 2076130000304, "ACCTTYPE" => "CHECKING"),
                            "BANKTRANLIST" => array("DTSTART" => date_format($date_start,"YmdHis") . "[-3:GMT]", "DTEND" => date_format($date_end,"YmdHis") . "[-3:GMT]"),
                        )
                    ))
            )];


                $balance = 0;
               foreach ($conciliatedTransactions as $transaction)
               {
                   foreach ($transaction->accountingEvents as $events)
                   {

                       if ($events->type == "Realized")
                       {

                           $order_id = 0;
                           if(isset($transaction->saleData))
                           {
                               $order_id = $transaction->saleData->orderId;
                           }
                           else if(isset($transaction->acquirerData))
                           {
                               $order_id = $transaction->acquirerData->orderId;
                           }
                           else
                           {
                               echo "saleData não encontrado" + $transaction->acquirerData->transactionId;
                               continue;
                           }


                           $nfes = $sankhya->db_explorer->execute_query("SELECT * FROM TGFCAB AS nfe 
                                                                                           WHERE  nfe.AD_ECOMMERCE_CHECKOUTID = '$order_id' AND TIPMOV = 'P'; ");


                           $category = $events->category;
                           $date_event=date_create($events->eventDate);
                           $netAmount = $events->netAmount / 100;
                           $netAmountStr = str_replace(".", ",", $netAmount);
                           $balance += $netAmount;

                           $stmttrn = ["STMTTRN" => array(
                               "TRNTYPE" => $netAmount < 0 ? "DEBIT" : "OTHER",
                               "DTPOSTED" => date_format( $date_event,"YmdHis") . "[-3:GMT]",
                               "TRNAMT" => $netAmountStr,
                               "FITID" => $order_id,
                               "CHECKNUM" => count($nfes) > 0 ? $nfes[0]["NUNOTA"] : "",
                               "PAYEEID" => 0,
                               "MEMO" => date_format( $date_event,"d/m/Y") . " - " . ($netAmount > 0 ? "Valor recebido pela Braspag, relativo a transacao Numero: $order_id" : "Valor Extornado ($category), relativo a transacao Numero: $order_id"),
                           )];

                           $dados_ofx["OFX"]["BANKMSGSRSV1"]["STMTTRNRS"]["STMTRS"]["BANKTRANLIST"][] = $stmttrn;
                       }
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


				
		
		
	
	