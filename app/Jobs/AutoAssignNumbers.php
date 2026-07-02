<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoAssignNumbers implements ShouldQueue
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
        \App\Models\Order::query()
            ->isRemaining()
            ->isPending()
            ->isBuying()
            ->isNotRefunded()
            ->orderBy('id', 'asc')
            ->chunk(300, function ($orders) {
                $this->processOrderChunk($orders);
            });
    }


    /**
     * Process a chunk of orders
     */
    private function processOrderChunk($orders): void
    {
        $messages = [];
        foreach ($orders as $order) {
            $remaining = $order->total_qty - $order->success_qty;

            if ($remaining <= 0) {
                continue;
            }

            $numberOwnerUserId = null;
            $buyer = User::find($order->user_id);

            $parent = null;
            if ($buyer) {

                // Case 1: buyer ka parent hai
                if ($buyer->parent_user_id) {

                    $parent = User::find($buyer->parent_user_id);

                    //  NEW CONDITION
                    // agar parent bhi buyer hai (ya self parent case)
                    if (
                        $parent &&
                        (
                            $parent->role === User::USER_ROLE ||
                            $parent->id === $buyer->id
                        )
                    ) {
                        //  super admin ke numbers
                        $superAdmin = User::IsSuperAdminRole()->first();
                        $numberOwnerUserId = $superAdmin?->id;
                    } else {
                        //  seller ke numbers
                        $numberOwnerUserId = $buyer->parent_user_id;
                    }
                } else {
                    //  Case 2: no parent → super admin
                    $superAdmin = User::IsSuperAdminRole()->first();
                    $numberOwnerUserId = $superAdmin?->id;
                }
            }
            $scopeMethod = ($parent?->role == User::SELLER_ROLE) ? 'isSellerNotUsed' : 'isNotUsed';

            $numberIds = \App\Models\Number::$scopeMethod()
                ->where('carrier_id', $order->carrier_id)
                ->where('area_id', $order->area_id)
                ->isNotExpired()
                ->where('user_id', $numberOwnerUserId)
                ->orderBy('expiry', 'asc') // Use numbers expiring soonest first to avoid waste
                ->limit($remaining)
                ->pluck('id')
                ->toArray();
            Log::info($numberIds);
            if (count($numberIds) > 0) {

                // Use billing-aware assignment (checks wallet, updates transaction/history)
                $result = $order->tryAssignNumbersWithBilling($numberIds, $parent, $numberOwnerUserId, $buyer);
                if (!empty($result['skipped'])) {
                    $messages[] = "Order #{$order->reference}: " . count($result['assigned']) . " assigned, " . count($result['skipped']) . " skipped (insufficient wallet balance).";
                }
            }
        }
        if (! empty($messages)) {
            Log::warning("Auto assign numbers messages: " . implode(' | ', $messages));
        }
    }
}
