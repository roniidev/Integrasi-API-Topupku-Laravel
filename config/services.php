<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Konfigurasi integrasi API Topup PPOB / Game Gateway
    |
    */

    'topup' => [
        'base_url'    => env('TOPUP_BASE_URL'),
        'merchant_id' => env('TOPUP_MERCHANT_ID'),
        'api_key'     => env('TOPUP_API_KEY'),
    ],

];
