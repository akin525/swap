<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $guarded = [];

    protected $table = "wallet_transactions";

    public function business(){
        return $this->belongsTo('App\Models\Business','business_id','id');
    }

//    public function wallet(){
//        return $this->belongsTo('App\Models\Wallet','wallet_id','id');
//    }

    function transaction(){
        return $this->belongsTo(Transaction::class,'reference', 'reference');
    }

    function payout(){
        return $this->belongsTo(Payout::class,'reference', 'reference');
    }

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet that owns the transaction.
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get formatted amount with currency symbol and sign
     */
    public function getFormattedAmountAttribute()
    {
        $symbols = [
            'NGN' => '₦',
            'GHS' => '₵',
            'ZAR' => 'R',
            'USD' => '$',
        ];

        $symbol = $symbols[$this->currency] ?? '';
        $sign = $this->type === 'credit' ? '+' : '-';
        return $sign . $symbol . number_format((float)$this->amount, 2);
    }
}
