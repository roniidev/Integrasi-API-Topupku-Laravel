# Integrasi API Topupku (Laravel)

Modul integrasi API Game & PPOB Topup Gateway untuk Laravel Framework, berdasarkan dokumentasi Postman resmi.

---

## 📌 Fitur yang Didukung

- **Profil & Saldo** (`POST /api/profile`) — Menggunakan `Authorization: Bearer <token>`
- **Daftar Layanan & Harga** (`POST /api/service`) — Menggunakan `Authorization: Bearer <token>`
- **Daftar Kategori** (`POST /api/category`) — Menggunakan `Authorization: Bearer <token>`
- **Cek ID Game / Nomor Akun** (`POST /api/check`) — Menggunakan `Authorization: Bearer <token>`
- **Order Produk** (`POST /api/order`) — `Authorization: Bearer <token>` + `X-Order-Signature`
- **Cek Status Transaksi** (`POST /api/status`) — Menggunakan `Authorization: Bearer <token>`
- **Topup Saldo / Deposit** (`POST /api/deposit` & `POST /api/deposit-snap`)
- **Penarikan / Transfer Saldo** (`POST /api/transfer`) — `Authorization: Bearer <token>` + `X-Transfer-Signature`
- **Webhook Notifikasi Pesanan** (`POST /api/topup/webhook`) — Verifikasi `X-Webhook-Signature`
- **Callback Pembayaran Topup Saldo** (`POST /api/topup/callback`) — Verifikasi `X-Callback-Signature` (Ref: `#8d77c040-82d6-4963-8bef-437c2f47f549`)

---

## ⚙️ Persyaratan & Setup

1. Salin konfigurasi environment:
   ```bash
   cp .env.example .env
   ```
2. Isi kredensial di file `.env`:
   ```env
   TOPUP_BASE_URL="https://domain-provider.com"
   TOPUP_MERCHANT_ID="ID_MERCHANT_ANDA"
   TOPUP_API_KEY="API_KEY_ANDA"
   ```
3. Pastikan `config/services.php` memuat konfigurasi `topup`:
   ```php
   'topup' => [
       'base_url'    => env('TOPUP_BASE_URL'),
       'merchant_id' => env('TOPUP_MERCHANT_ID'),
       'api_key'     => env('TOPUP_API_KEY'),
   ],
   ```

---

## 🛡️ Otentikasi & Keamanan

### 1. Authorization Bearer Token
Semua request ke API provider menggunakan standar Laravel HTTP Client:
```php
Http::withToken($bearerToken)->post(...)
```
Header yang dikirim otomatis:
```http
Authorization: Bearer <token>
Content-Type: application/json
```

### 2. Tanda Tangan Digital (Signature)
1. **Order Produk (`POST /api/order`)**:
   ```php
   $signature = hash_hmac('sha256', MERCHANT_ID . ':' . REFF_ID, API_KEY);
   // Dikirim di header: X-Order-Signature
   ```
2. **Callback Pembayaran Topup Saldo (`POST /api/topup/callback`)** *(Ref: `#8d77c040-82d6-4963-8bef-437c2f47f549`)*:
   ```php
   $expectedSig = hash_hmac('sha256', MERCHANT_ID . ':' . total_pembayaran . ':' . reff_id, API_KEY);
   // Diverifikasi dari header: X-Callback-Signature
   ```
   *Wajib mengembalikan response JSON:*
   ```json
   {
       "success": true
   }
   ```
3. **Webhook Status Pesanan (`POST /api/topup/webhook`)**:
   ```php
   $expectedSig = hash_hmac('sha256', MERCHANT_ID . ':' . invoice_number, API_KEY);
   // Diverifikasi dari header: X-Webhook-Signature
   ```
4. **Transfer / Penarikan Saldo (`POST /api/transfer`)**:
   ```php
   $signature = hash_hmac('sha256', MERCHANT_ID . ':' . TARGET_ID . ':' . NOMINAL, API_KEY);
   // Dikirim di header: X-Transfer-Signature
   ```

---

## 🚀 Daftar Rute API

| Method | Endpoint | Fungsi |
|---|---|---|
| `POST` | `/api/topup/check-id` | Cek ID akun game / nomor e-wallet (Cek Nickname/Nama) |
| `POST` | `/api/topup/order` | Membuat pesanan baru |
| `POST` | `/api/topup/check-status` | Cek status pesanan lokal |
| `POST` | `/api/topup/webhook` | Menerima webhook status pesanan |
| `POST` | `/api/topup/callback` | Menerima callback status pembayaran saldo |
