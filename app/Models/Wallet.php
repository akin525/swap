<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $guarded = [];

    protected $table = "wallets";

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions for the wallet.
     */
    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Get formatted balance with currency symbol
     */
    public function getFormattedBalanceAttribute()
    {
        $symbols = [
            'NGN' => '₦',
            'GHS' => '₵',
            'ZAR' => 'R',
            'USD' => '$',
        ];

        $symbol = $symbols[$this->currency] ?? '';
        return $symbol . number_format((float)$this->balance, 2);
    }
}
