<?php

header("Access-Control-Allow-Origin: *");

ini_set('memory_limit', '-1');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'Paypal/PaypalAPIService.php';
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


$config_sankhya = include "sankhya_config.php";
$sankhya = new SankhyaAPI($config_sankhya["host"]);

        try
        {
            $paypalHelper = new PaypalAPIService();


            $total_pages = 1;
            $page_size = 200;

            $date_start=date_create($_GET["date"]);
            $date_end=date_create($_GET["date"]);

            $conciliatedTransactions = array();
            for($i = 1; $i < $total_pages + 1; $i++)
            {
                $initial_date = date_create($_GET["date"] . "00:00:00+0000");
                $final_date = date_create($_GET["date"] . "23:59:59+0000");

	            $cash_flow = $paypalHelper->GetTransactions( date_format($initial_date, DATE_ATOM),  date_format($final_date, DATE_ATOM), $i);

                    $total_pages = $cash_flow->total_pages;
                    foreach ($cash_flow->transaction_details as $transaction)
                    {
                        if(!isset($conciliatedTransactions[$transaction->transaction_info->transaction_id]))
                            $conciliatedTransactions[$transaction->transaction_info->transaction_id] = $transaction;
                    }

            }


            $file_name =  "extrato_paypal_$money_release_date.ofx";
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






            $dados_ofx = ["OFX" => array(
                "SIGNONMSGSRSV1" =>
                array("SONRS"=>
                    array("STATUS" => array("CODE" => 0, "SEVERITY" => "INFO"),
                    "DTSERVER" => date_format(date_create(),"YmdHis") . "[-3:GMT]",
                    "LANGUAGE" => "ENG",
                    "FI" => array("ORG" => "PAYPAL", "FID" => "PAYPAL"),
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
                   //Verificar se o status é S (transação efetuada com sucesso) ou V (transação revertida)

                       if (in_array($transaction->transaction_info->transaction_status, ["S", "V"]))
                       {
                           $transaction_info = $transaction->transaction_info;
                           $payer_info = $transaction->payer_info;
                           $transaction_id = $transaction_info->transaction_id;

                           $nfes = $sankhya->db_explorer->execute_query("SELECT * FROM TGFCAB AS nfe 
                                                                                           WHERE  nfe.AD_PAYMENT_AUTHORIZATION_CODE = '$transaction_id' AND TIPMOV = 'P'; ");

                           $payment_method = "PAYPAL";
                           $date_event=date_create($transaction_info->transaction_initiation_date);
                           $netAmount = $transaction_info->transaction_amount->value;
                           $netAmountStr = str_replace(".", ",", $netAmount);
                           $balance += $netAmount;

                           $timeZoneName = intval(substr($date_event->getTimezone()->getName(), 3));

                           $numero_transacao_string = "Transaction ID: $transaction_id";
                           $cliente_string = "Cliente: " . $payer_info->payer_name->alternate_full_name;

                           $memo = date_format($date_event,"d/m/Y") . " - " . $transaction_info->transaction_subject  . " - " . ($netAmount > 0 ? "Valor recebido pelo mercado paypal, relativo a transacao com $numero_transacao_string" : "Valor Extornado ($payment_method), relativo a transacao com $numero_transacao_string") . " - " . $cliente_string;
                           $stmttrn = ["STMTTRN" => array(
                               "TRNTYPE" => $netAmount < 0 ? "DEBIT" : "OTHER",
                               "DTPOSTED" => date_format( $date_event,"YmdHis") . "[" . $timeZoneName  . ":GMT]",
                               "TRNAMT" => $netAmountStr,
                               "FITID" => $transaction_id,
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


				
		
		
	
	