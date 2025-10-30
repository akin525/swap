<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\ReloadlyServices;

class GiftCardController extends Controller
{
    protected $reloadly;

    public function __construct(ReloadlyServices $reloadly)
    {
        $this->reloadly = $reloadly;
    }

    public function purchase()
    {
        try {
            $orderData =$this->reloadly->createOrderData(
                productId: 10,
                countryCode: 'US',
                quantity: 2,
                unitPrice: 5,
                recipientEmail: 'anyone@email.com',
                customIdentifier: 'obucks10',
                senderName: 'John Doe',
                recipientPhoneDetails: [
                    'countryCode' => 'US',
                    'phoneNumber' => '657829900'
                ]
            );

            $result =$this->reloadly->purchaseGiftCard($orderData);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
