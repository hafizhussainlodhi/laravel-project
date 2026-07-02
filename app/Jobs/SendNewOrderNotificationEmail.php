<?php

namespace App\Jobs;

use App\Mail\NewOrderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewOrderNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly string $orderUrl,
        public readonly string $userName,
    ) {}

    public function handle(): void
    {
        Log::info("". $this->orderUrl ." " . $this->userName ." ");
        Mail::send(new NewOrderNotification($this->orderUrl, $this->userName));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Order notification email failed', [
            'order_url' => $this->orderUrl,
            'error'     => $exception->getMessage(),
        ]);
    }
}