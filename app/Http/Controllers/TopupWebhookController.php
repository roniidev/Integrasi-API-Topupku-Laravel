<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TopupWebhookController extends Controller
{
    /**
     * 1. Webhook Status Pesanan Produk (Order)
     * POST /api/topup/webhook
     * Headers:
     *   X-Webhook-Signature = hash_hmac('sha256', MERCHANT_ID . ':' . invoice_number, API_KEY)
     *   X-Webhook-v2-Signature = hash_hmac('sha256', MERCHANT_ID . ':' . reff_id, API_KEY)
     */
    public function handle(Request $request)
    {
        $merchantId = config('services.topup.merchant_id');
        $apiKey     = config('services.topup.api_key');

        $data = $request->input('data');
        if (!$data) {
            return response()->json(['result' => false, 'msg' => 'Payload kosong atau tidak sesuai'], 400);
        }

        $invoiceNumber = $data['invoice_number'] ?? '';
        $reffId        = $data['reff_id'] ?? '';
        $status        = $data['status'] ?? ''; // 'Sukses', 'Gagal', 'Pending', 'Proses', 'Refund'
        $note          = $data['note'] ?? '';

        // Validasi Signature
        $headerSig   = $request->header('X-Webhook-Signature');
        $headerSigV2 = $request->header('X-Webhook-v2-Signature');

        $expectedSigV1 = hash_hmac('sha256', $merchantId . ':' . $invoiceNumber, $apiKey);
        $expectedSigV2 = hash_hmac('sha256', $merchantId . ':' . $reffId, $apiKey);

        $isValid = ($headerSig && hash_equals($expectedSigV1, $headerSig)) ||
                   ($headerSigV2 && hash_equals($expectedSigV2, $headerSigV2));

        if (!$isValid) {
            Log::warning('Topup Webhook Order: Signature tidak valid!', [
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
            ]);
            return response()->json(['result' => false, 'msg' => 'Signature tidak valid'], 403);
        }

        // TODO: Update status transaksi pada database Anda berdasarkan $reffId atau $invoiceNumber
        Log::info("Webhook Order diterima untuk pesanan {$reffId} ({$invoiceNumber}): Status {$status}. Note: {$note}");

        return response()->json([
            'success' => true,
            'message' => 'Webhook pesanan berhasil diproses',
        ]);
    }

    /**
     * 2. Callback Status Pembayaran Topup Saldo / Deposit
     * POST /api/topup/callback
     * Postman Ref: #8d77c040-82d6-4963-8bef-437c2f47f549
     * Headers:
     *   X-Callback-Signature = hash_hmac('sha256', MERCHANT_ID . ':' . total_pembayaran . ':' . reff_id, API_KEY)
     * Response wajib:
     *   {"success": true}
     */
    public function handlePaymentCallback(Request $request)
    {
        $merchantId = config('services.topup.merchant_id');
        $apiKey     = config('services.topup.api_key');

        $data = $request->input('data');
        if (!$data) {
            return response()->json(['success' => false, 'msg' => 'Data tidak ditemukan'], 400);
        }

        $reffId          = $data['reff_id'] ?? '';
        $trxId           = $data['trx_id'] ?? '';
        $status          = $data['status'] ?? ''; // 'Paid', 'Unpaid'
        $totalPembayaran = $data['total_pembayaran'] ?? 0;
        $totalDiterima   = $data['total_diterima'] ?? 0;
        $fee             = $data['fee'] ?? 0;

        // Validasi X-Callback-Signature: hash_hmac('sha256', MERCHANT_ID . ':' . total_pembayaran . ':' . reff_id, API_KEY)
        $headerSig   = $request->header('X-Callback-Signature');
        $expectedSig = hash_hmac('sha256', $merchantId . ':' . $totalPembayaran . ':' . $reffId, $apiKey);

        if (!$headerSig || !hash_equals($expectedSig, $headerSig)) {
            Log::warning('Topup Payment Callback: X-Callback-Signature tidak valid!', [
                'header_signature'   => $headerSig,
                'expected_signature' => $expectedSig,
                'payload'            => $request->all(),
            ]);
            return response()->json(['success' => false, 'msg' => 'Signature tidak valid'], 403);
        }

        // Proses pembayaran topup saldo jika 'Paid'
        if ($status === 'Paid') {
            Log::info("Payment Callback Berhasil: Saldo {$totalDiterima} masuk untuk reff_id {$reffId} (TRX: {$trxId})");
            // TODO: Tambah saldo akun user di database Anda
        } else {
            Log::info("Payment Callback Status {$status} untuk reff_id {$reffId}");
        }

        // Sesuai dokumentasi: Response dari callback WAJIB mengembalikan JSON {"success": true}
        return response()->json([
            'success' => true,
        ]);
    }
}
