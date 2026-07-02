<?php

namespace App\Models;

use App\Jobs\AutoAssignNumbers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Nova\Actions\Actionable;

class Order extends Model
{
    use Actionable, SoftDeletes, HasFactory;

    const  ORDER_TYPE_BUY = 'BUY';
    const ORDER_TYPE_SELL = 'SELL';

    const STATUS_PENDING = 'PENDING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_REFUNDED = 'REFUNDED';

    const CURRENCY_USD = 'USD';



    protected $fillable = [
        'reference',
        'user_id',
        'carrier_id',
        'city_id',
        'order_type',
        'area_id',
        'reject_qty',
        'success_qty',
        'is_refunded',
        'total_qty',
        'price',
        'subtotal',
        'currency',
        'total',
        'status',
        'refunded_at',
        'notes',
        'pending_notes',
    ];

    public static function GET_STATUS()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function numbers()
    {
        return $this->belongsToMany(Number::class, OrderItem::class, 'order_id', 'number_id');
    }

    public function scopeisRemaining($query)
    {
        return $query->whereRaw('COALESCE(total_qty, 0) - COALESCE(success_qty, 0) > 0');
    }

    public function scopeisRefunded($query)
    {
        return $query->where('is_refunded', true);
    }


    public function scopeisNotRefunded($query)
    {
        return $query->where('is_refunded', false);
    }


    public function scopeisPending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }


    public function scopeIsSelling($query)
    {
        return $query->where('order_type', self::ORDER_TYPE_SELL);
    }

    public function scopeIsBuying($query)
    {
        return $query->where('order_type', self::ORDER_TYPE_BUY);
    }


    public function transaction()
    {
        return $this->hasOne(Transaction::class, 'order_id');
    }

    /**
     * Get the effective unit price for a number on this order.
     * Uses seller's rate (UserCarrier) for the number's owner + this order's carrier if set, otherwise carrier default price.
     */
    public function getEffectiveUnitPriceForNumber(Number $number): float
    {
        $carrierId = $this->carrier_id;
        if (! $carrierId) {
            return 0;
        }
        $carrier = $this->carrier ?? Carrier::find($carrierId);
        $defaultPrice = $carrier ? (float) $carrier->price : 0;

        $pivot = UserCarrier::where('user_id', $number->user_id)
            ->where('carrier_id', $carrierId)
            ->first();

        if ($pivot && $pivot->rate !== null && (float) $pivot->rate > 0) {
            return (float) $pivot->rate;
        }

        return $defaultPrice;
    }

    /**
     * Recalculate subtotal from assigned numbers (sum of effective unit prices) and save.
     * Used when numbers are assigned/added (CreateOrder, AutoAssignNumbers, AutoAssignRemainingNumbers).
     */
    // public function recalculateSubtotalAndTotal(): void
    // {
    //     $sum = $this->numbers()->get()->sum(function (Number $number) {
    //         return $this->getEffectiveUnitPriceForNumber($number);
    //     });
    //     $this->subtotal = round($sum, 2);
    //     $remainingQuantity = $this->total_qty - $this->success_qty;
    //     $remainingPrice = $remainingQuantity * $this->price;
    //     $this->total = $remainingPrice + $this->subtotal;
    //     $this->save();
    // }

    public function recalculateSubtotalAndTotal(): void
    {
        // Buyer ka effective rate nikalo
        $userCarrier = UserCarrier::where('user_id', $this->user_id)
            ->where('carrier_id', $this->carrier_id)
            ->first();

        $buyerRate = ($userCarrier && $userCarrier->rate > 0)
            ? (float) $userCarrier->rate
            : (float) $this->price; // fallback: carrier default

        // Subtotal = assigned numbers * buyer rate
        $this->subtotal = round($this->success_qty * $buyerRate, 2);

        // Remaining unfulfilled numbers bhi buyer rate se
        $remainingQuantity = $this->total_qty - $this->success_qty;
        $this->total = round(($remainingQuantity * $buyerRate) + $this->subtotal, 2);

        $this->save();
    }

    /**
     * Try to assign numbers with billing check.
     * Checks if wallet has sufficient balance for additional cost (if seller rates > carrier default).
     * If insufficient, skips those numbers and adds pending_notes.
     *
     * @param array $numberIds IDs of numbers to assign
     * @return array ['assigned' => [...], 'skipped' => [...], 'additional_charged' => float]
     */
    public function tryAssignNumbersWithBilling(array $numberIds, $parent, $numberOwnerUserId, $buyer): array
    {
        if (empty($numberIds)) {
            return ['assigned' => [], 'skipped' => [], 'additional_charged' => 0];
        }

        $numbers = Number::whereIn('id', $numberIds)->get();
        $wallet = $this->user?->wallet;
        // $defaultPrice = (float) $this->price;

        $userCarrier = \App\Models\UserCarrier::where('user_id', $this->user_id)
            ->where('carrier_id', $this->carrier_id)
            ->first();

        $defaultPrice = ($userCarrier && $userCarrier->rate > 0)
            ? (float) $userCarrier->rate
            : (float) $this->price; // fallback

        $assigned = [];
        $skipped = [];
        $totalAdditionalCost = 0;
        $availableBalance = $wallet ? (float) $wallet->available : 0;

        // Process each number individually to handle partial assignments
        foreach ($numbers as $number) {
            $effectivePrice = $this->getEffectiveUnitPriceForNumber($number);
            $reservedPrice = $defaultPrice; // What was charged upfront for this slot
            $additionalCost = max(0, $effectivePrice - $reservedPrice);

            if ($additionalCost > 0 && $additionalCost > $availableBalance) {
                // Insufficient funds for this number
                $skipped[] = $number->id;
            } else {
                // Can assign this number
                $assigned[] = $number->id;
                $totalAdditionalCost += $additionalCost;
                $availableBalance -= $additionalCost;
            }
        }

        $actuallyAssigned = [];
        $actualAdditionalCost = 0;

        // If we have numbers to assign
        if (! empty($assigned)) {
            // Get existing number IDs to avoid duplicates
            $existingIds = $this->numbers()->pluck('numbers.id')->toArray();

            // Filter out any IDs that are already attached (safety check)
            $newIds = array_values(array_diff($assigned, $existingIds));

            // Only proceed if there are actually new numbers to attach
            // if (! empty($newIds)) {
            //     // Attach new numbers only (doesn't remove existing)
            //     $this->numbers()->attach($newIds);

            //     // Mark new numbers as used
            //     Number::whereIn('id', $newIds)->update(['is_used' => true]);

            //     // Update order quantities (only count truly new numbers)
            //     $this->success_qty = $this->success_qty + count($newIds);
            //     if ($this->total_qty == $this->success_qty) {
            //         $this->status = self::STATUS_COMPLETED;
            //     }

            //     // Calculate additional cost for only the new numbers
            //     $newNumbers = Number::whereIn('id', $newIds)->get();
            //     foreach ($newNumbers as $num) {
            //         $actualAdditionalCost += max(0, $this->getEffectiveUnitPriceForNumber($num) - $defaultPrice);
            //     }

            //     // Charge additional cost if any
            //     if ($actualAdditionalCost > 0 && $wallet) {
            //         $this->chargeAdditionalAmount($actualAdditionalCost);
            //     }

            //     // Recalculate subtotal and total
            //     $this->recalculateSubtotalAndTotal();

            //     $actuallyAssigned = $newIds;
            // }

            if (! empty($newIds)) {
                $this->numbers()->attach($newIds);

                if ($parent && $parent->id == $numberOwnerUserId) {
                    Number::whereIn('id', $newIds)->update(['seller_is_used' => true]);
                } else {

                    if ($buyer->role != User::USER_ROLE) {
                        Number::whereIn('id', $numberIds)->update([
                            'user_id' => $buyer->id,
                            'is_used' => true
                        ]);

                        AutoAssignNumbers::dispatch();
                    }

                    Number::whereIn('id', $newIds)->update(['is_used' => true]);
                }

                $this->success_qty = $this->success_qty + count($newIds);
                if ($this->total_qty == $this->success_qty) {
                    $this->status = self::STATUS_COMPLETED;
                }

                // Buyer se additional charge nahi — wo pehle se pay kar chuka hai
                // recalculate karo buyer rate se
                $this->recalculateSubtotalAndTotal();

                $actuallyAssigned = $newIds;
            }
        }

        // Update pending_notes if any numbers were skipped
        if (! empty($skipped)) {
            $skippedCount = count($skipped);
            $shortfall = 0;
            foreach (Number::whereIn('id', $skipped)->get() as $num) {
                $shortfall += max(0, $this->getEffectiveUnitPriceForNumber($num) - $defaultPrice);
            }
            $note = "[" . now()->format('Y-m-d H:i') . "] {$skippedCount} number(s) not assigned: insufficient wallet balance. Additional $" . number_format($shortfall, 2) . " required.";
            $this->pending_notes = $this->pending_notes
                ? $this->pending_notes . "\n" . $note
                : $note;
            $this->save();
        } elseif ($this->status === self::STATUS_COMPLETED && $this->pending_notes) {
            // Clear pending notes if order is now complete
            $this->pending_notes = null;
            $this->save();
        }

        return [
            'assigned' => $actuallyAssigned,
            'skipped' => $skipped,
            'additional_charged' => $actualAdditionalCost,
        ];
    }

    /**
     * Charge additional amount to wallet and update existing transaction/history.
     */
    private function chargeAdditionalAmount(float $amount): void
    {
        $wallet = $this->user?->wallet;
        if (! $wallet || $amount <= 0) {
            return;
        }

        // Update wallet balance
        $wallet->available -= $amount;
        $wallet->used += $amount;
        $wallet->save();

        // Update existing transaction (increase charged_price)
        $transaction = $this->transaction;
        if ($transaction) {
            $transaction->charged_price = (float) $transaction->charged_price + $amount;
            $transaction->save();
        }

        // Update existing wallet history for this order (the DEBIT entry)
        $walletHistory = WalletHistory::where('model_id', $this->id)
            ->where('model_type', self::class)
            ->where('type', WalletHistory::TYPE_DEBIT)
            ->orderBy('id', 'desc')
            ->first();

        if ($walletHistory) {
            $walletHistory->amount = (float) $walletHistory->amount + $amount;
            $walletHistory->description = 'Order ' . $this->reference . ' (updated: +$' . number_format($amount, 2) . ' for additional numbers)';
            $walletHistory->save();
        } else {
            // Fallback: create new history if none exists
            WalletHistory::create([
                'wallet_id' => $wallet->id,
                'user_id' => $this->user_id,
                'amount' => $amount,
                'type' => WalletHistory::TYPE_DEBIT,
                'description' => 'Order ' . $this->reference . ' (additional numbers)',
                'currency' => $this->currency,
                'model_id' => $this->id,
                'model_type' => self::class,
                'status' => WalletHistory::STATUS_APPROVED,
            ]);
        }
    }
}
