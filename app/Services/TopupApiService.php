<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TopupApiService
{
    protected string $baseUrl;
    protected string $merchantId;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl    = rtrim((string)config('services.topup.base_url'), '/');
        $this->merchantId = (string)config('services.topup.merchant_id');
        $this->apiKey     = (string)config('services.topup.api_key');
    }

    /**
     * Helper membuat HTTP client dengan Authorization: Bearer <API_KEY>
     */
    protected function client()
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson();
    }

    /**
     * 1. Cek Profil & Sisa Saldo
     * POST /api/profile
     * Header: Authorization: Bearer <token>
     */
    public function getProfile(): array
    {
        try {
            return $this->client()
                ->timeout(15)
                ->post("{$this->baseUrl}/api/profile")
                ->json() ?? [];
        } catch (Exception $e) {
            Log::error("Topup getProfile Error: " . $e->getMessage());
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 2. Ambil Daftar Layanan & Harga
     * POST /api/service
     * Header: Authorization: Bearer <token>
     */
    public function getServices(): array
    {
        try {
            return $this->client()
                ->timeout(20)
                ->post("{$this->baseUrl}/api/service")
                ->json() ?? [];
        } catch (Exception $e) {
            Log::error("Topup getServices Error: " . $e->getMessage());
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 3. Ambil Daftar Kategori
     * POST /api/category
     * Header: Authorization: Bearer <token>
     */
    public function getCategories(): array
    {
        try {
            return $this->client()
                ->timeout(15)
                ->post("{$this->baseUrl}/api/category")
                ->json() ?? [];
        } catch (Exception $e) {
            Log::error("Topup getCategories Error: " . $e->getMessage());
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 4. Cek ID Akun (Game User ID / E-Wallet Number)
     * POST /api/check
     * Header: Authorization: Bearer <token>
     */
    public function checkAccount(string $code, string $userId, ?string $zoneId = ''): array
    {
        $payload = [
            'code'    => $code,
            'user_id' => $userId,
            'zone_id' => $zoneId ?? '',
        ];

        try {
            return $this->client()
                ->timeout(15)
                ->post("{$this->baseUrl}/api/check", $payload)
                ->json() ?? [];
        } catch (Exception $e) {
            Log::error("Topup checkAccount Error: " . $e->getMessage(), $payload);
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 5. Buat Pesanan (Order)
     * POST /api/order
     * Headers:
     *   - Authorization: Bearer <token>
     *   - X-Order-Signature: hash_hmac('sha256', MERCHANT_ID . ':' . REFF_ID, API_KEY)
     */
    public function order(
        string $reffId,
        string $skuCode,
        string $targetId,
        ?string $targetServer = '',
        ?string $targetServer2 = ''
    ): array {
        $signature = hash_hmac('sha256', $this->merchantId . ':' . $reffId, $this->apiKey);

        $payload = [
            'reff_id'         => $reffId,
            'sku_code'        => $skuCode,
            'target_id'       => $targetId,
            'target_server'   => $targetServer ?? '',
            'target_server_2' => $targetServer2 ?? '',
        ];

        try {
            $response = $this->client()
                ->withHeaders([
                    'X-Order-Signature' => $signature,
                ])
                ->timeout(30)
                ->post("{$this->baseUrl}/api/order", $payload);

            return [
                'http_status' => $response->status(),
                'success'     => $response->successful(),
                'data'        => $response->json() ?? [],
            ];
        } catch (Exception $e) {
            Log::error("Topup Order Timeout/Exception: " . $e->getMessage(), ['reff_id' => $reffId]);

            return [
                'http_status' => 500,
                'success'     => false,
                'data'        => [
                    'status' => 'Pending',
                    'msg'    => 'Koneksi timeout/error: status diset PENDING',
                ],
            ];
        }
    }

    /**
     * 6. Cek Status Pesanan
     * POST /api/status
     * Header: Authorization: Bearer <token>
     */
    public function checkStatus(?string $reffId = null, ?string $invoiceNumber = null): array
    {
        $payload = [];
        if ($invoiceNumber) {
            $payload['invoice_number'] = $invoiceNumber;
        } elseif ($reffId) {
            $payload['reff_id'] = $reffId;
        }

        try {
            return $this->client()
                ->timeout(15)
                ->post("{$this->baseUrl}/api/status", $payload)
                ->json() ?? [];
        } catch (Exception $e) {
            Log::error("Topup checkStatus Error: " . $e->getMessage(), $payload);
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 7. Topup Saldo / Deposit Bank
     * POST /api/deposit
     * Header: Authorization: Bearer <token>
     */
    public function deposit(string $reffId, string $kodeBank, int|string $nominal): array
    {
        $payload = [
            'reff_id'   => $reffId,
            'kode_bank' => $kodeBank,
            'nominal'   => (string)$nominal,
        ];

        try {
            return $this->client()
                ->timeout(20)
                ->post("{$this->baseUrl}/api/deposit", $payload)
                ->json() ?? [];
        } catch (Exception $e) {
            Log::error("Topup deposit Error: " . $e->getMessage(), $payload);
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 8. Snap Deposit (Virtual Account / QRIS / E-Wallet Payment Gateway)
     * POST /api/deposit-snap
     * Header: Authorization: Bearer <token>
     */
    public function depositSnap(
        string $reffId,
        int|string $nominal,
        array $options = []
    ): array {
        $payload = array_merge([
            'reff_id'              => $reffId,
            'nominal'              => (int)$nominal,
            'email_costumer_snap'  => $options['email'] ?? '',
            'phone_costumer_snap'  => $options['phone'] ?? '',
            'product'              => $options['product'] ?? 'Deposit Saldo',
            'note'                 => $options['note'] ?? 'Topup saldo via snap',
            'redirect_url'         => $options['redirect_url'] ?? '',
        ], $options);

        try {
            return $this->client()
                ->timeout(20)
                ->post("{$this->baseUrl}/api/deposit-snap", $payload)
                ->json() ?? [];
        } catch (Exception $e) {
            Log::error("Topup depositSnap Error: " . $e->getMessage(), $payload);
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 9. Transfer / Penarikan Saldo
     * POST /api/transfer
     * Headers:
     *   - Authorization: Bearer <token>
     *   - X-Transfer-Signature: hash_hmac('sha256', MERCHANT_ID . ':' . TARGET_ID . ':' . NOMINAL, API_KEY)
     */
    public function transfer(string $targetId, int|string $nominal, string $nama, string $bankTujuan): array
    {
        $signature = hash_hmac('sha256', $this->merchantId . ':' . $targetId . ':' . $nominal, $this->apiKey);

        $payload = [
            'nominal'     => (string)$nominal,
            'nama'        => $nama,
            'bank_tujuan' => $bankTujuan,
            'target_id'   => $targetId,
        ];

        try {
            return $this->client()
                ->withHeaders([
                    'X-Transfer-Signature' => $signature,
                ])
                ->timeout(30)
                ->post("{$this->baseUrl}/api/transfer", $payload)
                ->json() ?? [];
        } catch (Exception $e) {
            Log::error("Topup Transfer Error: " . $e->getMessage(), $payload);
            return ['status' => false, 'msg' => $e->getMessage()];
        }
    }
}
