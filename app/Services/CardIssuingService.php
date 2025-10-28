<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CardIssuingService
{
    protected $baseUrl;
    protected $apiKey;
    protected $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.card_issuer.url');
        $this->apiKey = config('services.card_issuer.api_key');
        $this->secretKey = config('services.card_issuer.secret_key');
    }

    /**
     * Create a virtual card
     *
     * @param array $data
     * @return array
     */
    public function createVirtualCard(array $data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/cards/virtual', [
                'name' => $data['name'],
                'address' => $data['address'],
                'date_of_birth' => $data['date_of_birth'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'currency' => $data['currency'],
                'design' => $data['design'],
                'user_id' => $data['user_id'],
                'reference' => $data['reference']
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // For demo purposes, if using a mock service that doesn't return all fields
                if (!isset($responseData['data']['card_number'])) {
                    // Generate mock card data
                    return $this->generateMockCardData($data);
                }

                return $responseData['data'];
            } else {
                Log::error('Card issuing service error: ' . $response->body());
                throw new \Exception('Failed to create virtual card: ' . $response->json()['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            Log::error('Card issuing service exception: ' . $e->getMessage());

            // For demo purposes, return mock data if service is unavailable
            if (app()->environment('local', 'development', 'testing')) {
                return $this->generateMockCardData($data);
            }

            throw $e;
        }
    }

    /**
     * Update card settings
     *
     * @param array $data
     * @return array
     */
    public function updateCardSettings(array $data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->put($this->baseUrl . '/cards/' . $data['card_id'] . '/settings', [
                'freeze_physical_card' => $data['freeze_physical_card'],
                'disable_web_purchase' => $data['disable_web_purchase'],
                'disable_contactless' => $data['disable_contactless'],
                'disable_card' => $data['disable_card']
            ]);

            if ($response->successful()) {
                return $response->json()['data'];
            } else {
                Log::error('Card issuing service error: ' . $response->body());
                throw new \Exception('Failed to update card settings: ' . $response->json()['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            Log::error('Card issuing service exception: ' . $e->getMessage());

            // For demo purposes, return the input data if service is unavailable
            if (app()->environment('local', 'development', 'testing')) {
                return $data;
            }

            throw $e;
        }
    }

    /**
     * Fund a card
     *
     * @param array $data
     * @return array
     */
    public function fundCard(array $data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/cards/' . $data['card_id'] . '/fund', [
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'reference' => $data['reference']
            ]);

            if ($response->successful()) {
                return $response->json()['data'];
            } else {
                Log::error('Card issuing service error: ' . $response->body());
                throw new \Exception('Failed to fund card: ' . $response->json()['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            Log::error('Card issuing service exception: ' . $e->getMessage());

            // For demo purposes, return mock data if service is unavailable
            // For demo purposes, return mock data if service is unavailable
            if (app()->environment('local', 'development', 'testing')) {
                // Get current card balance from cache or default to 0
                $currentBalance = Cache::get('card_balance_' . $data['card_id'], 0);
                $newBalance = bcadd($currentBalance, $data['amount'], 2);

                // Store new balance in cache
                Cache::put('card_balance_' . $data['card_id'], $newBalance, now()->addDays(30));

                return [
                    'balance_before' => $currentBalance,
                    'balance_after' => $newBalance,
                    'currency' => $data['currency'],
                    'reference' => $data['reference'],
                    'status' => 'success'
                ];
            }

            throw $e;
        }
    }

    /**
     * Withdraw from a card
     *
     * @param array $data
     * @return array
     */
    public function withdrawFromCard(array $data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/cards/' . $data['card_id'] . '/withdraw', [
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'reference' => $data['reference']
            ]);

            if ($response->successful()) {
                return $response->json()['data'];
            } else {
                Log::error('Card issuing service error: ' . $response->body());
                throw new \Exception('Failed to withdraw from card: ' . $response->json()['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            Log::error('Card issuing service exception: ' . $e->getMessage());

            // For demo purposes, return mock data if service is unavailable
            if (app()->environment('local', 'development', 'testing')) {
                // Get current card balance from cache or default to 1000
                $currentBalance = Cache::get('card_balance_' . $data['card_id'], 1000);

                if (bccomp($currentBalance, $data['amount'], 2) < 0) {
                    throw new \Exception('Insufficient card balance');
                }

                $newBalance = bcsub($currentBalance, $data['amount'], 2);

                // Store new balance in cache
                Cache::put('card_balance_' . $data['card_id'], $newBalance, now()->addDays(30));

                return [
                    'balance_before' => $currentBalance,
                    'balance_after' => $newBalance,
                    'currency' => $data['currency'],
                    'reference' => $data['reference'],
                    'status' => 'success'
                ];
            }

            throw $e;
        }
    }

    /**
     * Generate mock card data for testing/development
     *
     * @param array $data
     * @return array
     */
    private function generateMockCardData(array $data)
    {
        $cardNumber = '2584' . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);
        $expiryMonth = rand(1, 12);
        $expiryYear = date('Y') + rand(1, 5);
        $cvv = rand(100, 999);

        return [
            'card_id' => 'card_' . uniqid(),
            'card_number' => $cardNumber,
            'expiry_month' => $expiryMonth,
            'expiry_year' => $expiryYear,
            'cvv' => $cvv,
            'card_type' => 'virtual',
            'issuer' => 'Swappay',
            'currency' => $data['currency'],
            'status' => 'active'
        ];
    }
}
