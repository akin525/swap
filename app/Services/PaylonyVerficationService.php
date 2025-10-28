<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class PaylonyVerficationService
{
    protected $apiKey;
    protected $encryptionKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('SPRINTCHECK_API_KEY');
        $this->encryptionKey = env('SPRINTCHECK_ENCRYPTION_KEY');
        $this->baseUrl = env('SPRINTCHECK_BASE_URL');
    }

    public function verifyBVN($bvn, $identifier)
    {
        $payload = [
            'number' => $bvn,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/bvn', $payload);
    }

    public function verifyNIN($nin, $identifier)
    {
        $payload = [
            'number' => $nin,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/nin', $payload);
    }
    public function verifyVoters($vocters, $identifier)
    {
        $payload = [
            'number' => $vocters,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/voters', $payload);
    }
    public function verifyPassport($firstname, $lastname, $dob, $number, $identifier)
    {
        $payload = [
            'first_name' => $firstname,
            'last_name' => $lastname,
            'dob' => $dob,
            'number' => $number,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/passport', $payload);
    }
    public function verifyDriverLi($firstname, $lastname, $dob, $number, $identifier)
    {
        $payload = [
            'first_name' => $firstname,
            'last_name' => $lastname,
            'dob' => $dob,
            'number' => $number,
            'identifier' => $identifier,
        ];

        return $this->sendRequest('/drivers-license', $payload);
    }

    protected function sendRequest($endpoint, array $payload)
    {
        try {
            Log::info("Request sent to Paylony", ['request' => $payload]);
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $signature = hash_hmac('sha512', $jsonPayload, $this->encryptionKey);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $this->apiKey,
                'signature' => $signature,
            ])->post("{$this->baseUrl}{$endpoint}", $payload);

            $data = $response->json();
            Log::info("Paylony response", ['response' => $data]);
            if (isset($data['success']) && $data['success'] == 1) {
                // Update user verification info if authenticated
                if (auth()->check()) {
                    $user = auth()->user();
                    $user->verification_status = 'identity_verify';
                    $user->verification_number = $payload['number'] ?? null;
                    // $user->verification_type = $payload['type'] ?? null; // Uncomment if needed
                    $user->save();
                }

                return response()->json([
                    'status' => true,
                    'message' => $data['message'] ?? 'Verification successful.',
                    'data' => $data['data'] ?? [],
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => $data['message'] ?? 'Verification failed.',
                'data' => $data['data'] ?? [],
            ]);

        } catch (\Throwable $e) {
            log::critical('Paylony API request failed.', [
                'error' => $e->getMessage(),
                'request' => $payload,
                'response' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
