<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TopupWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rute untuk integrasi API Topup
|
*/

// --- Opsi 1: Rute Terproteksi Authorization Bearer (cth: Laravel Sanctum) ---
// Jika aplikasi / frontend Anda ingin mengakses endpoint ini dengan Authorization: Bearer <token_user>
Route::middleware('auth:sanctum')->prefix('topup')->group(function () {
    Route::post('/order', [OrderController::class, 'store']);
    Route::post('/check-status', [OrderController::class, 'checkStatus']);
    Route::post('/check-id', [OrderController::class, 'checkId']); // Cek ID Game / E-Wallet (#8025143f-d117-41f3-999d-1c6db9a64162)
});

// --- Opsi 2: Rute Publik / Tanpa Sanctum (jika belum mengaktifkan sanctum) ---
// Route::prefix('topup')->group(function () {
//     Route::post('/order', [OrderController::class, 'store']);
//     Route::post('/check-status', [OrderController::class, 'checkStatus']);
//     Route::post('/check-id', [OrderController::class, 'checkId']);
// });

// --- Rute Webhook & Callback Provider (WAJIB PUBLIK) ---
// 1. Webhook status pesanan produk (verifikasi via X-Webhook-Signature)
Route::post('/topup/webhook', [TopupWebhookController::class, 'handle']);

// 2. Callback status pembayaran topup saldo / deposit (verifikasi via X-Callback-Signature)
// Ref Postman: #8d77c040-82d6-4963-8bef-437c2f47f549
Route::post('/topup/callback', [TopupWebhookController::class, 'handlePaymentCallback']);
