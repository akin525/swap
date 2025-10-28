<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';
    protected $guarded=[];

    // function merchant(){
    //     return $this->belongsTo(Business::class,'business_id', 'id');
    // }

    // function customer(){
    //     return $this->belongsTo(Customer::class,'customer_id', 'id');
    // }

    // function customerinfo(){
    //     return $this->belongsTo(Customer::class,'customer_id', 'id')->select('id', 'account_name','first_name','last_name','email','phone', 'customer_code','domain','status','created_at');
    // }

    // function cards(){
    //     return $this->hasMany(Card::class,'reference', 'reference');
    // }

    // function card(){
    //     return $this->hasMany(Card::class,'reference', 'reference')->latest()->limit(1);
    // }

    // function lastCard(){
    //     return $this->hasOne(Card::class,'reference', 'reference')->latest();
    // }

    // function lastUsedCard(){
    //     return $this->hasOne(Card::class,'reference', 'reference')->where('extra', NULL)->latest();
    // }

    // function timeline(){
    //     return $this->hasMany(TransactionTimeline::class,'reference', 'reference');
    // }

    // function timeline3(){
    //     return $this->hasMany(TransactionTimeline::class,'reference', 'reference')->latest()->limit(3);
    // }

    // function timeline1(){
    //     return $this->hasMany(TransactionTimeline::class,'reference', 'reference')->latest()->limit(1);
    // }

    // function refund()
    // {
    //     return $this->hasMany(Refund::class, 'transaction_id', 'id');
    // }

    // public function business()
    // {
    //     return $this->belongsTo('App\Models\Business', 'business_id', 'id');
    // }

    // public function subaccount()
    // {
    //     return $this->belongsTo('App\Models\SubAccount', 'split_code', 'sa_code');
    // }

    // public function transferDetails()
    // {
    //     return $this->belongsTo(WemaWebhook::class, 'reference', 'paymentReference');
    // }

    // function paymentlinkpay(){
    //     return $this->hasOne(Paymentlinkpay::class,'reference', 'trx_id')->first();
    // }

}
