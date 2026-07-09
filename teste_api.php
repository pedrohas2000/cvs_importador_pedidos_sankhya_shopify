<?php

//require 'Braspag/BraspagAPIService.php';
//require 'MercadoPago/MercadoPagoAPIService.php';
require 'PagBrasil/PagBrasilAPIService.php';

$pagBrasil = new PagBrasilAPIService();
$detalhes_pagamento = $pagBrasil->GetPaymentsByID('29613053509731');

if($detalhes_pagamento)
{
    print_r($detalhes_pagamento);
    print($detalhes_pagamento->order);
    print(PagBrasilPaymentToSankhyaTipoNegociacao($detalhes_pagamento));

}



function PagBrasilPaymentToSankhyaTipoNegociacao($payment)
{

    $payment_methods = [
        "C" => "Cartão de Crédito",
        "D" => "Cartão de Débito",
        "B" => "Boleto Bancario",
        "F" => "Boleto Flash",
        "X" => "Pix",
    ];

    $credit_card_brands = [
        "M" => "Mastercard",
        "V" => "Visa",
        "D" => "Diners",
        "A" => "Amex",
        "H" => "Hipercard",
        "E" => "Elo",
    ];

    $type = strtoupper($payment->payment_method);

    $payment_type_text = strtoupper($payment_methods[$type]);

    $tipo_pagamento = "PAGBRASIL";

    if($type == "C"){
        $brand_char =   strtoupper($payment->cc_brand);
        $brand = strtoupper($credit_card_brands[$brand_char]);
        $tipo_pagamento .= " - " . $brand;

        if($payment->cc_installments == 1)
            $tipo_pagamento .= " - " . $payment->cc_installments . " Parcela" . ($payment->cc_installments > 1 ? "s" : "");
        else
            $tipo_pagamento .= " - " . $payment->cc_installments . " Parcelas";
    }
    else
        $tipo_pagamento .= " - " . $payment_type_text;


    return strtoupper($tipo_pagamento);
}

/*
$braspagService = new BraspagAPIService();
$detalhes_pagamento = $braspagService->GetPaymentsByMerchantOrderID(25699733176419);

if($detalhes_pagamento)
{
   print_r($detalhes_pagamento);
}

*/
