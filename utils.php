<?php

function logMsg( $msg, $level = 'info', $file = 'main.log' )
{
    // variável que vai armazenar o nível do log (INFO, WARNING ou ERROR)
    $levelStr = '';

    // verifica o nível do log
    switch ( $level )
    {
        case 'info':
            // nível de informação
            $levelStr = 'INFO';
            break;

        case 'warning':
            // nível de aviso
            $levelStr = 'WARNING';
            break;

        case 'error':
            // nível de erro
            $levelStr = 'ERROR';
            break;
    }

    // data atual
    $date = date( 'Y-m-d H:i:s' );

    // formata a mensagem do log
    // 1o: data atual
    // 2o: nível da mensagem (INFO, WARNING ou ERROR)
    // 3o: a mensagem propriamente dita
    // 4o: uma quebra de linha
    $msg = sprintf( "[%s] [%s]: %s%s", $date, $levelStr, $msg, PHP_EOL );

    
    $fullPath = __DIR__ . '/logs' . (str_starts_with($file,"/") ? $file : '/' . $file);
    print_r($fullPath);


    $path = dirname($fullPath) . DIRECTORY_SEPARATOR;
    
    // Cria a pasta de logs caso não exista
    if (!is_file($fullPath) && !is_dir($path)) {
        print_r($path);
        mkdir( $path, 0755, true);
    }
    // Verifica se o arquivo existe, caso contrário, cria um novo
    if (!file_exists($fullPath)) {
        touch($fullPath);
        chmod($fullPath, 755);
    }
    // escreve o log no arquivo
    // é necessário usar FILE_APPEND para que a mensagem seja escrita no final do arquivo, preservando o conteúdo antigo do arquivo
    file_put_contents( $fullPath, $msg, FILE_APPEND );
        print_r($msg) ;
}


function arrayToXml($array, $rootElement = null, $xml = null) {
    $_xml = $xml;

    // If there is no Root Element then insert root
    if ($_xml === null) {
        $_xml = new SimpleXMLElement($rootElement !== null ? $rootElement : '<root/>');
    }

    // Visit all key value pair
    foreach ($array as $k => $v) {

        // If there is nested array then
        if (is_array($v)) {

            // Call function for nested array
            if( is_numeric($k) ){
                arrayToXml($v, $k, $_xml);
            }
            else
                arrayToXml($v, $k, $_xml->addChild($k));
        }

        else {

            // Simply add child element.
            $_xml->addChild($k, $v);
        }
    }

    $dom = dom_import_simplexml($_xml)->ownerDocument;
    $dom->formatOutput = true;
    $dom->encoding = "UTF-8";

    return $dom->saveXML();
}


function stripAccents($str) {
    return strtr(utf8_decode($str), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
}


function isWeekend($date) {
    // Cria um objeto DateTime a partir da string de data
    if(!($date instanceof DateTime))
        $date = new DateTime($date);
    // Obtém o número do dia da semana (1 para segunda-feira, 7 para domingo)
    $dayOfWeek = $date->format('N');
    // Retorna verdadeiro se for sábado (6) ou domingo (7)
    return ($dayOfWeek >= 6);
}

function isHoliday($date, $holidays) {
    // Converte a data para o formato Y-m-d
    $formattedDate = $date->format('Y-m-d');
    // Verifica se a data está no array de feriados
    return in_array($formattedDate, $holidays);
}

function isBusinessDay($date) {
    // Lista de feriados (exemplo para o Brasil)
    $holidays = [
        '2024-01-01', // Confraternização Universal
        '2024-02-12', // Carnaval
        '2024-02-13', // Carnaval
        '2024-04-21', // Tiradentes
        '2024-05-01', // Dia do Trabalhador
        '2024-09-07', // Independência do Brasil
        '2024-10-12', // Nossa Senhora Aparecida
        '2024-11-02', // Finados
        '2024-11-15', // Proclamação da República
        '2024-12-25', // Natal
    ];

    if(!($date instanceof DateTime))
        $date = new DateTime($date);

    // Verifica se é fim de semana ou feriado
    if (isWeekend($date) || isHoliday($date, $holidays)) {
        return false; // Não é dia útil
    }

    return true; // É dia útil
}



function buscarEnderecoPorCep($cep) {
    // Remove caracteres não numéricos
    $cep = preg_replace('/[^0-9]/', '', $cep);

    if (strlen($cep) !== 8) {
        return "CEP inválido.";
    }

    // Faz a chamada na API ViaCEP
    $url = "https://viacep.com.br/ws/{$cep}/json/";
    $response = file_get_contents($url);
  
    if ($response === FALSE) {
        return "Erro ao buscar o CEP.";
    }

    $data = json_decode($response, true);
    

    if (isset($data['erro']) && $data['erro'] === true) {
        return null;
    }

    return $data;
}


function isValidCpfCnpj($value) {
    // Remove caracteres não numéricos
    $value = preg_replace('/[^0-9]/', '', $value);

    // Verifica se é CPF (11 dígitos) ou CNPJ (14 dígitos)
    if (strlen($value) === 11) {
        return isValidCpf($value);
    } elseif (strlen($value) === 14) {
        return isValidCnpj($value);
    }

    return false;
}

function isValidCpf($cpf) {
    // Remove tudo que não for número
    $cpf = preg_replace('/\D/', '', $cpf);

    // Verifica se tem 11 dígitos
    if (strlen($cpf) !== 11) {
        return false;
    }

    // CPFs inválidos conhecidos (mesmo que passem na conta)
    $invalidos = [
        '12345678909', '00000000000', '11111111111',
        '22222222222', '33333333333', '44444444444',
        '55555555555', '66666666666', '77777777777',
        '88888888888', '99999999999'
    ];
    if (in_array($cpf, $invalidos)) {
        return false;
    }

    // Validação dos dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int)$cpf[$i] * (($t + 1) - $i);
        }

        $digit = $sum % 11;
        $digit = $digit < 2 ? 0 : 11 - $digit;

        if ((int)$cpf[$t] !== $digit) {
            return false;
        }
    }

    return true;
}


function isValidCnpj($cnpj) {
    $cnpj = preg_replace('/\D/', '', $cnpj);

    if (strlen($cnpj) !== 14) {
        return false;
    }

    if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $firstWeights  = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    // Primeiro dígito verificador
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$cnpj[$i] * $firstWeights[$i];
    }
    $d1 = $sum % 11;
    $d1 = $d1 < 2 ? 0 : 11 - $d1;

    // Segundo dígito verificador
    $sum = 0;
    for ($i = 0; $i < 13; $i++) {
        $sum += (int)$cnpj[$i] * $secondWeights[$i];
    }
    $d2 = $sum % 11;
    $d2 = $d2 < 2 ? 0 : 11 - $d2;

    return ((int)$cnpj[12] === $d1) && ((int)$cnpj[13] === $d2);
}


function extrairNumeroEndereco($endereco) {
    // 1. Tenta encontrar número com prefixo: "Nº", "No", "n", etc.
    if (preg_match('/\bN[ºo]?\s*(\d{1,6}(?:[\.,]?\d{0,3})?)/iu', $endereco, $match_pref)) {
        return intval(str_replace(['.', ','], '', $match_pref[1]));
    }

    // 2. Se não encontrar, tenta pegar o número maior (mais provável ser o número do imóvel)
    if (preg_match_all('/\b(\d{1,6}(?:[\.,]?\d{0,3})?)\b/u', $endereco, $matches)) {
        // Transforma em números inteiros (remove . e ,)
        $numeros = array_map(function($n) {
            return intval(str_replace(['.', ','], '', $n));
        }, $matches[1]);

        // Retorna o maior valor (geralmente o número da casa é maior que o do apto)
        return max($numeros);
    }

    return null;
}

function extrairLogradouro($endereco) {
    // Remove vírgula e número ao final (ex: ", 432" ou " 343")
    return trim(preg_replace('/[\s,]+[0-9]+.*$/', '', $endereco));
}