<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\WalletHistory;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OrderRefundedJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $orderRefundTime = SettingService::getOrderRefundTime();
        $orderRefundedByHours = SettingService::getOrderRefundedByHours();
        //Log::info('Refund process started.');
        $datetime = Carbon::now();
        if ($orderRefundedByHours) {
            $datetime = $datetime->subHours($orderRefundTime);
        } else {
            $datetime = $datetime->subDays($orderRefundTime);
        }
        Order::isNotRefunded()
            ->isPending()
            ->isBuying()
            ->where('created_at', '<=', $datetime)
            ->chunk(300, function ($orders) {
                foreach ($orders as $order) {
                    try {
                        $wallet = $order->user->wallet;

                        // Buyer ka effective rate nikalo (superadmin ne jo set kiya tha)
                        $userCarrier = \App\Models\UserCarrier::where('user_id', $order->user_id)
                            ->where('carrier_id', $order->carrier_id)
                            ->first();

                        $effectiveRate = ($userCarrier && $userCarrier->rate > 0)
                            ? (float) $userCarrier->rate
                            : (float) $order->price; // fallback: carrier default price

                        // Fulfilled numbers ka charge buyer ke effective rate se
                        $successPrice = $order->success_qty * $effectiveRate;

                        // Jo numbers nahi mile unka refund
                        $remainingPrice = max(0, $order->total - $successPrice);

                        if ($remainingPrice <= 0) {
                            continue;
                        }
                        // Update wallet balances
                        $wallet->available += $remainingPrice;
                        $wallet->used -= $remainingPrice;
                        $wallet->save();

                        // Create wallet history record
                        WalletHistory::create([
                            'wallet_id' => $wallet->id,
                            'user_id' => $order->user_id,
                            'amount' => $remainingPrice,
                            'status' => WalletHistory::STATUS_APPROVED,
                            'currency' => $order->currency,
                            'model_id' => $order->id,
                            'model_type' => Order::class,
                            'type' => WalletHistory::TYPE_REFUND,
                            'description' => 'Order has been refunded by admin',
                        ]);

                        // Calculate rejected quantity
                        $rejectQty = ($order->success_qty !== null && $order->success_qty > 0)
                            ? max(0, $order->total_qty - $order->success_qty)
                            : $order->total_qty;

                        $transaction = $order->transaction;
                        if ($transaction) {
                            $transaction->status = Transaction::CANCELLED;
                            $transaction->save();
                        }

                        // Update order status
                        $order->update([
                            'status' => Order::STATUS_REFUNDED,
                            'is_refunded' => true,
                            'refunded_at' => now(),
                            'rejected_at' => null,
                            'reject_qty' => $rejectQty,
                            'subtotal' => $successPrice,
                            'total' => $successPrice,
                            'notes' => 'Order has been refunded by admin',
                        ]);

                        //  Log::info("Order ID: {$order->id} updated successfully.");
                    } catch (\Exception $e) {
                        Log::error("Error processing order ID: {$order->id}. Error: " . $e->getMessage());
                    }
                }
            });

        // Log::info('Refund process completed.');
    }
}
