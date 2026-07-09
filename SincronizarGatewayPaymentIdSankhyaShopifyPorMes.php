<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require './utils.php';

try
{
    if(isset($_GET["mes"]) && isset($_GET["ano"]))
    {
        $mes = str_pad($_GET["mes"], 2, '0', STR_PAD_LEFT);
        $ano = $_GET["ano"];
    }
    else
    {
        echo "Parâmetros 'mes' e 'ano' não fornecidos.";
        return;
    }

    foreach(range(1, 31) as $day)
    {
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        $data_inicial = date("$ano-$mes-$day");
        $data_final = date("$ano-$mes-$day");

        if($data_final > date('Y-m-t', strtotime($data_inicial)))
        {
            print("Data: $data_final\n");
            break;
        }

        print("Data Inicial: $data_inicial\n");
        print("Data Final: $data_final\n");

        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $url .= "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['REQUEST_URI']) . "/SincronizarGatewayPaymentIdSankhyaShopify.php?created_at_min={$data_inicial}&created_at_max={$data_final}";

        echo $url;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST , false);

        $response = curl_exec($ch);

        if(curl_errno($ch))
        {
            throw new Exception("Erro na requisição: " . curl_error($ch));
        }
        else
        {
            echo "URL Processada: " . $url . PHP_EOL;
            logMsg($url . PHP_EOL . $response, 'info', 'processamento/' . $data_final . '_gatewaypaymentid_log.txt');
        }

        curl_close($ch);
    }
}
catch(Exception $ex)
{
    echo $ex;
}
catch(Error $e)
{
    echo "Erro: " . $e->getMessage();
}

