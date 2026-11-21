<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TopupApiService;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    protected TopupApiService $topupService;

    public function __construct(TopupApiService $topupService)
    {
        $this->topupService = $topupService;
    }

    /**
     * Endpoint untuk melakukan pemesanan (Order)
     * POST /api/topup/order
     */
    public function store(Request $request)
    {
        $request->validate([
            'sku_code'        => 'required|string',
            'target_id'       => 'required|string',
            'target_server'   => 'nullable|string',
            'target_server_2' => 'nullable|string',
        ]);

        // Generate Reff ID unik sistem Anda
        $reffId = 'ORD-' . strtoupper(Str::random(10));

        // Panggil service order
        $result = $this->topupService->order(
            reffId: $reffId,
            skuCode: $request->sku_code,
            targetId: $request->target_id,
            targetServer: $request->input('target_server', ''),
            targetServer2: $request->input('target_server_2', '')
        );

        $response = $result['data'];

        // Jika request gagal secara HTTP / network timeout, set status lokal menjadi PENDING
        if (!$result['success'] || !isset($response['status'])) {
            // TODO: Simpan transaksi ke database lokal dengan status = 'Pending'
            return response()->json([
                'status'  => 'Pending',
                'reff_id' => $reffId,
                'message' => 'Pesanan telah dibuat dan berstatus PENDING (menunggu pemrosesan provider).',
            ]);
        }

        // Jika API provider merespons
        if ($response['status'] === true) {
            $orderData = $response['data'];
            // TODO: Simpan invoice_number, status 'Proses' ke database Anda
            return response()->json([
                'status'         => $orderData['status'] ?? 'Proses',
                'invoice_number' => $orderData['invoice_number'] ?? null,
                'reff_id'        => $reffId,
                'message'        => $response['msg'] ?? 'Pesanan berhasil diproses',
            ]);
        }

        // Jika validasi provider menolak (misal saldo kurang, signature salah, dll.)
        return response()->json([
            'status'  => 'Gagal',
            'message' => $response['msg'] ?? 'Gagal membuat pesanan',
        ], 400);
    }

    /**
     * Cek status pesanan
     * POST /api/topup/check-status
     */
    public function checkStatus(Request $request)
    {
        $request->validate([
            'reff_id'        => 'nullable|string',
            'invoice_number' => 'nullable|string',
        ]);

        $statusData = $this->topupService->checkStatus(
            reffId: $request->reff_id,
            invoiceNumber: $request->invoice_number
        );

        return response()->json($statusData);
    }

    /**
     * Cek ID Game / Nomor Akun E-Wallet (CEKID)
     * POST /api/topup/check-id
     * Ref Postman: #8025143f-d117-41f3-999d-1c6db9a64162
     */
    public function checkId(Request $request)
    {
        $request->validate([
            'code'    => 'required|string', // Contoh: "Mobile Legends", "Free Fire", "DANA"
            'user_id' => 'required|string', // ID Akun atau No HP
            'zone_id' => 'nullable|string', // Zone ID / Server jika game memerlukan (cth: MLBB)
        ]);

        $result = $this->topupService->checkAccount(
            code: $request->code,
            userId: $request->user_id,
            zoneId: $request->input('zone_id', '')
        );

        return response()->json($result);
    }
}
