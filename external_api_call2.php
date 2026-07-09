<?php

$url = "http://172.23.0.15/external_api_call.php";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Erro: ' . curl_error($ch);
} else {
    echo "Resposta da API:\n";
    echo $response;
}

curl_close($ch);