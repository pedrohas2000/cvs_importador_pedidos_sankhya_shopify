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

        $data_inicial = date("$ano-$mes-01"); // Primeiro dia do mês
  

        foreach(range(1, 31) as $day){
    
            //if($day > 1){break; }

            $day = str_pad($day, 2, '0', STR_PAD_LEFT);
            $data_inicial = date("$ano-$mes-$day");
            $data_final = date("$ano-$mes-$day");
            if($data_final > date('Y-m-t')){
                print("Data: $data_final\n"); 
                break;
            }
          


            print("Data Inicial: $data_inicial\n"); 
            print("Data Final: $data_final\n");  

            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $url .= "://{$_SERVER['HTTP_HOST']}" .  dirname($_SERVER['REQUEST_URI']) . "/ImportarDadosShopify.php?data_inicial={$data_inicial}&data_final={$data_final}";

            echo $url;
            // Inicializa o cURL
            $ch = curl_init();

            // Configurações do cURL
            curl_setopt($ch, CURLOPT_URL, $url); // Define a URL
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna o resultado como string
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desativa verificação SSL (cuidado com segurança)

            // Executa a requisição
            $response = curl_exec($ch);

            // Verifica erros
            if (curl_errno($ch)) {
                throw new Exception("Erro na requisição: " . curl_error($ch));
            } else {
                // Exibe a resposta
                echo "URL Processada: " . $url . PHP_EOL;
                logMsg($url . PHP_EOL . $response, 'info', 'processamento/' . $data_final . '_log.txt');
            }

            // Fecha a conexão cURL
            curl_close($ch);

        }
       
    
}  
catch(Exception $ex)
{
   echo $ex;
}
catch (Error $e) {
    echo "Erro: " . $e->getMessage();
}  
	
	