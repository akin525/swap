<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Wallet;
use App\Services\CashonrailsService;
use App\Services\PaylonyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateWalletsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $paylonyService = new PaylonyService();
        $result = $paylonyService->createVirtualAccount($this->user);
        Log::info($result);

        if($result['success']) {


        }


    }
}
