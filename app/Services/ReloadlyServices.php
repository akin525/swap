<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class ReloadlyServices
{
    protected $Baseurl;
    protected $Client_id;
    protected $Client_secret;
    protected $authUrl;
    protected $audience;

    public function __construct()
    {
        $this->Baseurl = config('services.reloadly.baseurl');
        $this->Client_id = config('services.reloadly.client_id');
        $this->Client_secret = config('services.reloadly.client_secret');
        $this->authUrl = config('services.reloadly.auth_url', 'https://auth.reloadly.com/oauth/token');
        $this->audience = config('services.reloadly.audience', 'https://giftcards-sandbox.reloadly.com');
    }

    /**
     * Get access token from Reloadly
     * Caches the token for its expiry duration
     */
    public function getAccessToken()
    {
        // Check if token exists in cache
        $cachedToken = Cache::get('reloadly_access_token');

        if ($cachedToken) {
            return $cachedToken;
        }

        // Request new token
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->authUrl, [
                'client_id' => $this->Client_id,
                'client_secret' => $this->Client_secret,
                'grant_type' => 'client_credentials',
                'audience' => $this->audience,
            ]);

            if ($response->successful()) {
                $data =$response->json();
                $accessToken =$data['access_token'];
                $expiresIn =$data['expires_in'] ?? 86400;

                // Cache token for slightly less than expiry time (5 minutes buffer)
                Cache::put('reloadly_access_token', $accessToken, now()->addSeconds($expiresIn - 300));

                return $accessToken;
            }

            throw new Exception('Failed to get access token: ' . $response->body());
        } catch (Exception $e) {
            throw new Exception('Authentication error: ' . $e->getMessage());
        }
    }

    /**
     * Purchase a gift card
     *
     * @param array $orderData
     * @return array
     */
    public function purchaseGiftCard(array $orderData)
    {
        try {
            $token =$this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($this->Baseurl . '/orders', $orderData);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Gift card purchase failed: ' . $response->body());
        } catch (Exception $e) {
            throw new Exception('Purchase error: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to create order data structure
     *
     * @param int $productId
     * @param string $countryCode
     * @param int $quantity
     * @param float $unitPrice
     * @param string $recipientEmail
     * @param string|null $customIdentifier
     * @param string|null $senderName
     * @param array|null $recipientPhoneDetails
     * @return array
     */
    public function createOrderData(
        int $productId,
        string $countryCode,
        int $quantity,
        float $unitPrice,
        string $recipientEmail,
        ?string $customIdentifier = null,
        ?string $senderName = null,
        ?array $recipientPhoneDetails = null
    ) {
        $orderData = [
            'productId' => $productId,
            'countryCode' => $countryCode,
            'quantity' => $quantity,
            'unitPrice' => $unitPrice,
            'recipientEmail' => $recipientEmail,
        ];

        if ($customIdentifier) {
            $orderData['customIdentifier'] = $customIdentifier;
        }

        if ($senderName) {
            $orderData['senderName'] = $senderName;
        }

        if ($recipientPhoneDetails) {
            $orderData['recipientPhoneDetails'] = $recipientPhoneDetails;
        }

        return $orderData;
    }

    /**
     * Get all available products/gift cards
     *
     * @return array
     */
    public function getProducts()
    {
        try {
            $token =$this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->get($this->Baseurl . '/products');

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to get products: ' . $response->body());
        } catch (Exception $e) {
            throw new Exception('Products error: ' . $e->getMessage());
        }
    }

    /**
     * Get product by ID
     *
     * @param int $productId
     * @return array
     */
    public function getProductById(int $productId)
    {
        try {
            $token =$this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->get($this->Baseurl . '/products/' . $productId);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to get product: ' . $response->body());
        } catch (Exception $e) {
            throw new Exception('Product error: ' . $e->getMessage());
        }
    }

    /**
     * Get transaction by ID
     *
     * @param int $transactionId
     * @return array
     */
    public function getTransaction(int $transactionId)
    {
        try {
            $token =$this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->get($this->Baseurl . '/orders/transactions/' . $transactionId);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to get transaction: ' . $response->body());
        } catch (Exception $e) {
            throw new Exception('Transaction error: ' . $e->getMessage());
        }
    }

    /**
     * Get account balance
     *
     * @return array
     */
    public function getBalance()
    {
        try {
            $token =$this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->get($this->Baseurl . '/accounts/balance');

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to get balance: ' . $response->body());
        } catch (Exception $e) {
            throw new Exception('Balance error: ' . $e->getMessage());
        }
    }
}
