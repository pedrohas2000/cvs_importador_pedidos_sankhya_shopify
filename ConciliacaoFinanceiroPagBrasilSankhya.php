<?php

header("Access-Control-Allow-Origin: *");

ini_set('memory_limit', '-1');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'PagBrasil/PagBrasilAPIService.php';
require 'vendor/autoload.php';
require './PHPSankhyaAPI/SankhyaAPI.php';
require './utils.php';
use \PHPSankhyaAPI\SankhyaAPI;

// get request method
$method = $_SERVER['REQUEST_METHOD'];
$headers = getallheaders();



echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload de Arquivo</title>
    <!-- Adicionando Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h2 class="mb-0">Upload de Arquivo</h2>
                            <h4>Envie o relatório .CSV da PagBrasil, para converter para .OFX</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="arquivo">Selecione um arquivo</label>
                                <input type="file" class="form-control-file" id="arquivo" name="arquivo">
                            </div>
                            <button type="submit" class="btn btn-success btn-block" name="submit">Enviar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Adicionando Bootstrap JS (opcional, necessário para certos componentes do Bootstrap) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

';


if ($method == 'POST') {


    $variables = [];
    foreach ($variables as $var) {
        if(!isset($_GET[$var])){
            http_response_code(400);
            print_r(json_encode(["success" => false, "message" => "Parâmetro obrigatório não informado: " . $var ]));
            return;
        }
    }

    $arquivo = $_FILES["arquivo"]["tmp_name"];

    $config_sankhya  = include "sankhya_config.php";
    $sankhya = new SankhyaAPI($config_sankhya["host"]);



    try
    {
        $pagBrasilAPIService = new PagBrasilAPIService();
        $total_pages = 1;
        $aquirer = 1;
        $page_size = 200;

        $transactions = array();

        $handle = fopen($arquivo, 'r');
        $cont = 1;
        if ($handle !== FALSE)
        {
            while (($linha = fgetcsv($handle, 0,';')) !== FALSE)
            {


                $item["order"] = $linha[0];
                $item["submission_date"] = $linha[1];
                $item["payment_method"] = $linha[2];
                $item["order_status"] = $linha[3];
                $item["customer_name"] = $linha[4];
                $item["customer_email"] = $linha[5];
                $item["product_name"] = $linha[6];
                $item["amount_brl"] = $linha[7];
                $item["amount_paid"] = $linha[8];
                $item["payment_date"] = $linha[9];
                $item["refund_date"] = $linha[10];
                $item["amount_refunded"] = $linha[11];
                $item["processing_fee_brl"] = $linha[12];
                $item["param_url"] = $linha[13];
                $item["recurring"] = $linha[14];
                $item["cc_installments"] = $linha[15];
                $item["customer_taxid"] = $linha[16];
                $item["Authentication"] = $linha[17];
                $item["fixed_fee"] = $linha[18];
                $item["variable_fee"] = $linha[19];
                $item["anticipation_fee"] = $linha[20];
                $item["taxes"] = $linha[21];

                $transactions[] = (object)$item;


            }


        }
        else
        {
            throw new Exception("Erro ao abrir o arquivo CSV.");
        }

        if(count($transactions) > 0){
            array_splice($transactions, 0, 1);
        }

        /*
        function compararData($a, $b) {
            return strtotime($a->submission_date) - strtotime($b->submission_date);
        }

        usort($transactions, 'compararData');
        */

        $date_exploded = explode("/", end($transactions)->submission_date);
        $date_exploded[1] = "01";

        $date_start=date_create(implode("/", $date_exploded));
        $date_end=date_create(end($transactions)->submission_date);

        $file_name =  "extrato_pagbrasil_" . date_format($date_start,"d-m-Y") . "_" . date_format($date_end,"d-m-Y")  . ".ofx";

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
                        "FI" => array("ORG" => "PAGBRASIL", "FID" => "PAGBRASIL"),
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
        foreach ($transactions as $transaction) {
            if (in_array($transaction->order_status, ["PC", "RP", "CB"])) {
                $order_id = $transaction->order;

                $nfes = $sankhya->db_explorer->execute_query("SELECT * FROM TGFCAB AS nfe 
                                                                                       WHERE  nfe.AD_ECOMMERCE_GATEWAY_PAYMENT_ID = '$order_id' OR nfe.AD_ECOMMERCE_CHECKOUTID = '$order_id' AND TIPMOV = 'P'; ");


                $payment_type = PagBrasilAPIService::PAYMENT_METHODS[$transaction->payment_method];

                if ($transaction->order_status == "PC") {
                    $date_event = date_create($transaction->payment_date);
                    $netAmount = $transaction->amount_paid;

                } else if (in_array($transaction->order_status, ["CB", "RP"])) {
                    $date_event = date_create($transaction->refund_date);
                    $netAmount = $transaction->amount_refunded * -1;
                }

                $netAmountStr = str_replace(".", ",", $netAmount);
                $balance += $netAmount;

                $stmttrn = ["STMTTRN" => array(
                    "TRNTYPE" => $netAmount < 0 ? "DEBIT" : "OTHER",
                    "DTPOSTED" => date_format($date_event, "YmdHis") . "[-3:GMT]",
                    "TRNAMT" => $netAmountStr,
                    "FITID" => $order_id,
                    "CHECKNUM" => count($nfes) > 0 ? $nfes[0]["NUNOTA"] : "",
                    "PAYEEID" => 0,
                    "MEMO" => date_format($date_event, "d/m/Y") . " - " . ($netAmount > 0 ? "Valor recebido pela PagBrasil, relativo a transacao Numero: $order_id" : "Valor Extornado ($payment_type), relativo a transacao Numero: $order_id"),
                )];

                $dados_ofx["OFX"]["BANKMSGSRSV1"]["STMTTRNRS"]["STMTRS"]["BANKTRANLIST"][] = $stmttrn;

            }
        }

        $balanceStr = str_replace(".", ",", $balance);
        $dados_ofx["OFX"]["BANKMSGSRSV1"]["STMTTRNRS"]["STMTRS"]["LEDGERBAL"] = array("BALAMT" => $balanceStr, "DTASOF" => date_format(date_create(), "YmdHis"));


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
    } finally {
        fclose($handle);
    }
}




?>


				
		
		
	
	