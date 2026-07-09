<?php

return [
    'pagbrasil' => [
        'host' => 'https://connect.pagbrasil.com',
        'token' => 'SEU_TOKEN_PAGBRASIL',
        'secret' => 'SEU_SECRET_PAGBRASIL',
    ],
    'braspag' => [
        'host' => 'https://apiquery.braspag.com.br',
        'version' => 'v2',
        'merchant_id' => 'SEU_MERCHANT_ID',
        'merchant_key' => 'SEU_MERCHANT_KEY',
    ],
    'braspag_agiliza' => [
        'host' => 'https://agiliza.braspag.com.br/api',
        'host_auth' => 'https://auth.braspag.com.br/oauth2/token',
        'basic_authorization' => 'Basic SEU_BASE64_CLIENT_ID_SECRET',
        'merchant_id' => 0,
    ],
    'mercadopago' => [
        'host' => 'https://api.mercadopago.com',
        'version' => 'v1',
        'access_token' => 'SEU_ACCESS_TOKEN_MERCADOPAGO',
    ],
    'paypal' => [
        'host' => 'https://api-m.paypal.com/v1',
        'host_auth' => 'https://api-m.paypal.com/v1/oauth2/token',
        'basic_authorization' => 'Basic SEU_BASE64_CLIENT_ID_SECRET',
    ],
];
